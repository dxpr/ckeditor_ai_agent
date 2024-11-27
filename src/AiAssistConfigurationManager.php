<?php

namespace Drupal\ckeditor_ai_assist;

use Drupal\Core\Config\ConfigFactoryInterface;

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
   * @return array
   *   The configuration array for CKEditor.
   */
  public function getCKEditorConfig() {
    $config = $this->configFactory->get('ckeditor_ai_assist.settings');
    
    return [
      'aiAssist' => [
        'apiKey' => $config->get('api_key'),
        'model' => $config->get('model'),
        'endpointUrl' => $config->get('endpoint_url'),
        'temperature' => (float) $config->get('temperature'),
        'timeOutDuration' => (int) $config->get('timeout_duration'),
        'maxTokens' => (int) $config->get('max_tokens'),
        'retryAttempts' => (int) $config->get('retry_attempts'),
        'debugMode' => (bool) $config->get('debug_mode'),
        'streamContent' => (bool) $config->get('stream_content'),
      ],
    ];
  }
} 