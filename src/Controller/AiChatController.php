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
      $is_streamed = !empty($data->stream) || !isset($data->stream);
      $input->setStreamedOutput($is_streamed);

      $model = (!empty($default['model_id']) && is_string($default['model_id']))
        ? $default['model_id']
        : '';
      if (isset($data->model) && is_string($data->model) && preg_match('~^[a-zA-Z0-9._:/-]+$~', $data->model)) {
        $model = $data->model;
      }

      $config = [];
      $config['jsonrpc'] = FALSE;

      if (isset($data->prediction) && is_object($data->prediction)) {
        $config['prediction'] = (array) $data->prediction;
      }
      if (isset($data->providers) && is_array($data->providers)) {
        $config['providers'] = array_filter($data->providers, 'is_string');
      }
      if (isset($data->allowed_html_tags) && is_string($data->allowed_html_tags)) {
        $config['allowed_html_tags'] = $data->allowed_html_tags;
      }
      if (isset($data->allowed_html_classes) && is_string($data->allowed_html_classes)) {
        $config['allowed_html_classes'] = $data->allowed_html_classes;
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
            $metadata = $message->getMetadata();
            if (!empty($metadata['choices'])) {
              $chunk = $metadata;
            }
            else {
              $chunk = [
                'choices' => [
                  [
                    'delta' => [
                      'content' => $message->getText(),
                    ],
                  ],
                ],
              ];
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
