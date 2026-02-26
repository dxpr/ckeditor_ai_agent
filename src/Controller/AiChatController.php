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
 * Controller for CKEditor AI Agent chat requests.
 *
 * Proxies AI requests from the CKEditor frontend through the Drupal ai module,
 * eliminating the need to expose API keys to the browser.
 */
class AiChatController extends ControllerBase {

  /**
   * Allowed roles for chat messages.
   */
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
      // Load the DXPR provider.
      $provider = $this->aiProviderManager->createInstance('dxpr');

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

      // Get model from request, validate it's a simple string.
      $model = 'kavya-m1';
      if (isset($data->model) && is_string($data->model) && preg_match('/^[a-zA-Z0-9._-]+$/', $data->model)) {
        $model = $data->model;
      }

      // Build configuration with DXPR-specific fields.
      $config = [];

      // Disable JSON-RPC agent/status messages — they are not compatible with
      // the openai-php/client library used by the JS plugin.
      $config['jsonrpc'] = FALSE;

      // Pass through optional DXPR-specific fields with type validation.
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

      // Set the configuration on the provider.
      $provider->setConfiguration($config);

      $output = $provider->chat($input, $model, ['ckeditor_ai_agent']);
      $response = $output->getNormalized();

      if ($is_streamed && $response instanceof StreamedChatMessageIteratorInterface) {
        return new StreamedResponse(function () use ($response) {
          foreach ($response as $message) {
            // Pass through the full raw response from metadata if available.
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
        // Non-streamed response.
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
