<?php

namespace Drupal\ckeditor_ai_assist;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\editor\Entity\Editor;

/**
 * Service for managing CKEditor AI Assist configuration.
 */
class AiAssistConfigurationManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new AiAssistConfigurationManager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Gets the CKEditor configuration array.
   *
   * @param \Drupal\editor\Entity\Editor|null $editor
   *   The editor entity, if available.
   *
   * @return array
   *   The configuration array for CKEditor.
   */
  public function getCkEditorConfig(?Editor $editor = NULL): array {
    $global_config = $this->configFactory->get('ckeditor_ai_assist.settings');

    $config = [
      'aiAssist' => [
        'apiKey' => $global_config->get('api_key'),
        'model' => $global_config->get('model'),
        'endpointUrl' => $global_config->get('endpoint_url'),
        'temperature' => (float) $global_config->get('temperature'),
        'timeOutDuration' => (int) $global_config->get('timeout_duration'),
        'maxTokens' => (int) $global_config->get('max_tokens'),
        'retryAttempts' => (int) $global_config->get('retry_attempts'),
        'debugMode' => (bool) $global_config->get('debug_mode'),
        'streamContent' => (bool) $global_config->get('stream_content'),
      ],
    ];

    return $config;
  }

}
