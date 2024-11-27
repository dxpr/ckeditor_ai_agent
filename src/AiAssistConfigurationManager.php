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
        'model' => $global_config->get('model') ?: 'gpt-4o',
        'endpointUrl' => $global_config->get('endpoint_url') ?: 'https://kavya.dxpr.com/v1/chat/completions',
        'temperature' => $global_config->get('temperature') ?: 0.7,
        'timeOutDuration' => $global_config->get('timeout_duration') ?: 45000,
        'maxTokens' => $global_config->get('max_tokens') ?: 4096,
        'retryAttempts' => $global_config->get('retry_attempts') ?: 1,
        'debugMode' => $global_config->get('debug_mode') ?: FALSE,
        'streamContent' => $global_config->get('stream_content') ?? TRUE,
      ],
    ];

    // Override with format-specific settings if available.
    if ($editor) {
      $settings = $editor->getSettings();
      if (isset($settings['plugins']['ckeditor_ai_assist_ai_assist'])) {
        $format_config = $settings['plugins']['ckeditor_ai_assist_ai_assist'];

        // Only override non-empty values.
        foreach ($format_config as $key => $value) {
          if (!empty($value)) {
            $config['aiAssist'][self::toJavaScriptName($key)] = $value;
          }
        }
      }
    }

    return $config;
  }

  /**
   * Converts PHP snake_case to JavaScript camelCase.
   */
  private static function toJavaScriptName(string $name): string {
    return lcfirst(str_replace('_', '', ucwords($name, '_')));
  }

}
