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

    // Structure the config to match the aiAgent JS configuration.
    $config = [
      'aiAgent' => [
        'apiKey' => $global_config->get('api_key'),
        'model' => $global_config->get('model'),
        'endpointUrl' => $global_config->get('endpoint_url'),
        'temperature' => $global_config->get('temperature'),
        'maxOutputTokens' => $global_config->get('max_output_tokens'),
        'maxInputTokens' => $global_config->get('max_input_tokens'),
        'contextSize' => $global_config->get('context_size'),
        'editorContextRatio' => $global_config->get('editor_context_ratio'),
        'timeOutDuration' => $global_config->get('timeout_duration'),
        'retryAttempts' => $global_config->get('retry_attempts'),
        'debugMode' => $global_config->get('debug_mode'),
        'showErrorDuration' => $global_config->get('show_error_duration'),
        'moderation' => [
          'enable' => $global_config->get('moderation.enable'),
          'key' => $global_config->get('moderation.key'),
          'disableFlags' => $global_config->get('moderation.disable_flags'),
        ],
        'promptSettings' => [
          'overrides' => $global_config->get('prompt_settings.overrides'),
          'additions' => $global_config->get('prompt_settings.additions'),
        ],
      ],
    ];

    return $config;
  }

}
