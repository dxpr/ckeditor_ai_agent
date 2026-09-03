<?php

namespace Drupal\ckeditor_ai_agent\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Proxies CKEditor AI Agent requests through the Drupal ai module.
 *
 * @phpstan-consistent-constructor
 */
class AiChatController extends ControllerBase {

  private const ALLOWED_ROLES = ['system', 'user', 'assistant'];

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\ai\AiProviderPluginManager $ai_provider_manager
   *   AI Provider manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   */
  public function __construct(
    AiProviderPluginManager $ai_provider_manager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->aiProviderManager = $ai_provider_manager;
    $this->logger = $logger_factory->get('ckeditor_ai_agent');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai.provider'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Handles AI chat requests from CKEditor AI Agent frontend.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Symfony\Component\HttpFoundation\Response
   *   The AI response, streamed as SSE or as a regular response.
   */
  public function chat(Request $request): StreamedResponse|Response {
    $data = json_decode($request->getContent());

    if (!$data || empty($data->messages) || !is_array($data->messages)) {
      return new Response('Invalid request: messages required', Response::HTTP_BAD_REQUEST);
    }

    try {
      $default = $this->aiProviderManager->getDefaultProviderForOperationType('chat');
      if (empty($default['provider_id']) || !is_string($default['provider_id'])) {
        $this->logger->error('No default AI chat provider configured.');
        return new Response(
          json_encode(['error' => ['message' => 'No AI provider configured. Visit /admin/config/ai/settings to set a default chat provider.']]),
          Response::HTTP_SERVICE_UNAVAILABLE,
          ['Content-Type' => 'application/json']
        );
      }

      $provider = $this->aiProviderManager->createInstance($default['provider_id']);

      // Build and validate chat messages from request.
      $chat_messages = [];
      foreach ($data->messages as $message) {
        if (!isset($message->role, $message->content)
          || !is_string($message->role)
          || !is_string($message->content)
          || !in_array($message->role, self::ALLOWED_ROLES, TRUE)) {
          return new Response('Invalid request: invalid message format', Response::HTTP_BAD_REQUEST);
        }
        $chat_messages[] = new ChatMessage($message->role, $message->content);
      }

      $input = new ChatInput($chat_messages);
      $is_streamed = !isset($data->stream) || !empty($data->stream);
      $input->setStreamedOutput($is_streamed);

      $default_model = (!empty($default['model_id']) && is_string($default['model_id']))
        ? $default['model_id']
        : '';
      $model = $default_model;
      $client_supplied_model = FALSE;
      if (isset($data->model) && is_string($data->model) && preg_match('~^[a-zA-Z0-9._:/-]+$~', $data->model)) {
        $model = $data->model;
        $client_supplied_model = TRUE;
      }

      // Fall back to the AI module's default when the client model is
      // disallowed; this endpoint has no user model selector.
      $restrictable = ['kavya-m1', 'kavya-m1-eu', 'kavya-m1-fast'];
      if ($client_supplied_model
        && in_array($model, $restrictable, TRUE)
        && $this->moduleHandler()->moduleExists('ai_provider_dxpr')) {
        $allowed = $this->config('ai_provider_dxpr.settings')->get('allowed_models');
        if (!empty($allowed) && is_array($allowed) && !in_array($model, $allowed, TRUE)) {
          $model = $default_model ?: $model;
        }
      }

      $config = [];
      $config['jsonrpc'] = FALSE;

      if (isset($data->prediction) && is_object($data->prediction)) {
        $config['prediction'] = (array) $data->prediction;
      }
      if (isset($data->providers) && is_array($data->providers)) {
        $config['providers'] = array_filter($data->providers, 'is_string');
      }
      $string_fields = [
        'allowed_html_tags',
        'allowed_html_classes',
        'allowed_html_styles',
        'allowed_html_attributes',
      ];
      foreach ($string_fields as $field) {
        if (isset($data->$field) && is_string($data->$field)) {
          $config[$field] = $data->$field;
        }
      }
      $bool_fields = [
        'allows_all_html_classes',
        'allows_all_html_styles',
        'allows_all_html_attributes',
      ];
      foreach ($bool_fields as $field) {
        if (isset($data->$field) && $data->$field === TRUE) {
          $config[$field] = TRUE;
        }
      }
      if (isset($data->web_search) && $data->web_search === FALSE) {
        $config['web_search'] = FALSE;
      }
      if (isset($data->response_format) && is_object($data->response_format)) {
        $config['response_format'] = (array) $data->response_format;
      }

      $provider->setConfiguration($config);

      $output = $provider->chat($input, $model, ['ckeditor_ai_agent']);
      $response = $output->getNormalized();

      if ($is_streamed && $response instanceof StreamedChatMessageIteratorInterface) {
        return new StreamedResponse(function () use ($response) {
          foreach ($response as $message) {
            $text = $message->getText();
            if ($text === '') {
              continue;
            }
            $chunk = [
              'choices' => [
                [
                  'delta' => [
                    'content' => $text,
                  ],
                ],
              ],
            ];
            $raw = $message->getRaw();
            if (is_array($raw)) {
              foreach (['id', 'object', 'created', 'model', 'original_model', 'usage'] as $key) {
                if (isset($raw[$key])) {
                  $chunk[$key] = $raw[$key];
                }
              }
            }

            echo 'data: ' . json_encode($chunk) . "\n\n";
            if (ob_get_level() > 0) {
              ob_flush();
            }
            flush();
          }
          echo "data: [DONE]\n\n";
          if (ob_get_level() > 0) {
            ob_flush();
          }
          flush();
        }, 200, [
          'Cache-Control' => 'no-cache, must-revalidate',
          'Content-Type' => 'text/event-stream',
          'X-Accel-Buffering' => 'no',
        ]);
      }
      else {
        $content = $response->getText();
        return new Response(json_encode([
          'choices' => [
            [
              'message' => [
                'role' => 'assistant',
                'content' => $content,
              ],
            ],
          ],
        ]), 200, ['Content-Type' => 'application/json']);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('AI chat request failed: @message', ['@message' => $e->getMessage()]);
      return new Response(
        json_encode(['error' => ['message' => 'The request could not be completed.']]),
        Response::HTTP_INTERNAL_SERVER_ERROR,
        ['Content-Type' => 'application/json']
      );
    }
  }

}
