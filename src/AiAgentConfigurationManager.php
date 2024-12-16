<?php

namespace Drupal\ckeditor_ai_agent;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\editor\Entity\Editor;

/**
 * Manages configuration for the CKEditor AI Agent plugin.
 */
class AiAgentConfigurationManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new AiAgentConfigurationManager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Gets the CKEditor configuration.
   *
   * @param \Drupal\editor\Entity\Editor|null $editor
   *   The editor entity.
   *
   * @return array
   *   The CKEditor configuration.
   */
  public function getCkEditorConfig(?Editor $editor = NULL): array {
    $global_config = $this->configFactory->get('ckeditor_ai_agent.settings');

    // Start with default configuration.
    $config = [
      'aiAgent' => [
        'apiKey' => $global_config->get('api_key'),
        'model' => $global_config->get('model') ?: 'gpt-4o',
        'endpointUrl' => $global_config->get('endpoint_url') ?: 'https://api.openai.com/v1/chat/completions',
        'temperature' => (float) $global_config->get('temperature'),
        'maxOutputTokens' => (int) $global_config->get('max_output_tokens'),
        'maxInputTokens' => (int) $global_config->get('max_input_tokens'),
        'stopSequences' => $global_config->get('stop_sequences') ?? [],
        'retryAttempts' => (int) ($global_config->get('retry_attempts') ?? 1),
        'contextSize' => (int) ($global_config->get('context_size') ?? 96000),
        'timeOutDuration' => (int) ($global_config->get('timeout_duration') ?? 45000),
        'editorContextRatio' => (float) ($global_config->get('editor_context_ratio') ?? 0.3),
        'debugMode' => (bool) ($global_config->get('debug_mode') ?? FALSE),
        'streamContent' => (bool) ($global_config->get('stream_content') ?? TRUE),
        'showErrorDuration' => (int) ($global_config->get('show_error_duration') ?? 5000),
        'moderation' => [
          'enable' => (bool) ($global_config->get('moderation.enable') ?? FALSE),
          'key' => $global_config->get('moderation.key'),
          'disableFlags' => $global_config->get('moderation.disable_flags') ?? [],
        ],
        'promptSettings' => [
          'overrides' => $global_config->get('prompt_settings.overrides') ?? [],
          'additions' => $global_config->get('prompt_settings.additions') ?? [],
        ],
      ],
    ];

    // Override with format-specific settings if available.
    if ($editor && isset($editor->getSettings()['plugins']['ckeditor_ai_agent_ai_agent'])) {
      $format_config = $editor->getSettings()['plugins']['ckeditor_ai_agent_ai_agent'];

      // Map of PHP config keys to JS config keys with type casting.
      $key_map = [
        'api_key' => ['key' => 'apiKey', 'cast' => 'strval'],
        'model' => ['key' => 'model', 'cast' => 'strval'],
        'temperature' => ['key' => 'temperature', 'cast' => 'floatval'],
        'max_tokens' => ['key' => 'maxTokens', 'cast' => 'intval'],
        'debug_mode' => ['key' => 'debugMode', 'cast' => 'boolval'],
        'stream_content' => ['key' => 'streamContent', 'cast' => 'boolval'],
      ];

      foreach ($key_map as $php_key => $settings) {
        if (isset($format_config[$php_key]) && $format_config[$php_key] !== NULL) {
          $cast_func = $settings['cast'];
          $config['aiAgent'][$settings['key']] = $cast_func($format_config[$php_key]);
        }
      }
    }

    return $config;
  }

}
