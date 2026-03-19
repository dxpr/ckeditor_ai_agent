<?php

namespace Drupal\ckeditor_ai_agent\Service;

use Drupal\ai\OperationType\Chat\ChatInput;

/**
 * Typed wrapper around an AI provider instance.
 */
final class AiChatProviderAdapter {

  /**
   * The raw provider instance.
   *
   * @var object
   */
  protected object $provider;

  /**
   * The provider's default model ID for chat.
   *
   * @var string
   */
  protected string $defaultModelId;

  /**
   * Constructs a new provider adapter.
   *
   * @param object $provider
   *   The provider instance from ai.provider.
   * @param string $default_model_id
   *   The default model ID configured for chat.
   */
  public function __construct(object $provider, string $default_model_id) {
    $this->provider = $provider;
    $this->defaultModelId = $default_model_id;
  }

  /**
   * Gets the configured default model ID for chat.
   *
   * @return string
   *   The default chat model ID.
   */
  public function getDefaultModelId(): string {
    return $this->defaultModelId;
  }

  /**
   * Sets provider configuration.
   *
   * @param array<string, mixed> $configuration
   *   Provider configuration.
   */
  public function setConfiguration(array $configuration): void {
    $set_configuration = [$this->provider, 'setConfiguration'];
    if (!is_callable($set_configuration)) {
      throw new \RuntimeException('AI provider does not support setConfiguration().');
    }

    call_user_func($set_configuration, $configuration);
  }

  /**
   * Executes a chat operation.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The chat input object.
   * @param string $model_id
   *   The model ID to use.
   * @param array<int, string> $tags
   *   Optional provider tags for the operation.
   *
   * @return \Drupal\ckeditor_ai_agent\Service\AiChatOutputAdapter
   *   Typed wrapper around the provider output.
   */
  public function chat(ChatInput $input, string $model_id, array $tags = []): AiChatOutputAdapter {
    $chat = [$this->provider, 'chat'];
    if (!is_callable($chat)) {
      throw new \RuntimeException('AI provider does not support chat().');
    }

    $output = call_user_func($chat, $input, $model_id, $tags);
    if (!is_object($output)) {
      throw new \RuntimeException('AI provider returned an invalid chat output.');
    }

    return new AiChatOutputAdapter($output);
  }

}
