<?php

namespace Drupal\ckeditor_ai_agent\Form;

/**
 * Provides configuration mapping constants.
 */
trait ConfigMappingTrait {

  /**
   * Gets the configuration mapping for form fields.
   *
   * @param bool $is_plugin
   *   Whether this is for the plugin configuration.
   */
  protected function getConfigMapping(bool $is_plugin = FALSE): array {
    $base_mapping = [
      'basic_settings.api_key' => [
        'type' => 'string',
        'plugin_key' => 'apiKey',
      ],
      'basic_settings.model' => [
        'type' => 'string',
        'plugin_key' => 'model',
      ],
      'basic_settings.endpoint_url' => [
        'type' => 'string',
        'plugin_key' => 'endpointUrl',
      ],
      'advanced_settings.temperature' => [
        'type' => 'float',
        'plugin_key' => 'temperature',
      ],
      'advanced_settings.tokens.max_output_tokens' => [
        'type' => 'int',
        'plugin_key' => 'maxOutputTokens',
      ],
      'advanced_settings.tokens.max_input_tokens' => [
        'type' => 'int',
        'plugin_key' => 'maxInputTokens',
      ],
      'advanced_settings.context.context_size' => [
        'type' => 'int',
        'plugin_key' => 'contextSize',
      ],
      'advanced_settings.context.editor_context_ratio' => [
        'type' => 'float',
        'plugin_key' => 'editorContextRatio',
      ],
      'performance_settings.timeout_duration' => [
        'type' => 'int',
        'plugin_key' => 'timeOutDuration',
      ],
      'performance_settings.retry_attempts' => [
        'type' => 'int',
        'plugin_key' => 'retryAttempts',
      ],
      'behavior_settings.debug_mode' => [
        'type' => 'bool',
        'plugin_key' => 'debugMode',
      ],
      'behavior_settings.stream_content' => [
        'type' => 'bool',
        'plugin_key' => 'streamContent',
      ],
      'behavior_settings.show_error_duration' => [
        'type' => 'int',
        'plugin_key' => 'showErrorDuration',
      ],
    ];

    if ($is_plugin) {
      // For plugin configuration, use the plugin_key
      $plugin_mapping = [];
      foreach ($base_mapping as $path => $settings) {
        $plugin_mapping[$path] = [
          'type' => $settings['type'],
          'config_key' => $settings['plugin_key'],
        ];
      }
      return $plugin_mapping;
    }

    // For global settings, use the last part of the path as the config key
    $settings_mapping = [];
    foreach ($base_mapping as $path => $settings) {
      $parts = explode('.', $path);
      $settings_mapping[$path] = [
        'type' => $settings['type'],
        'config_key' => end($parts),
      ];
    }
    return $settings_mapping;
  }

  /**
   * Gets the prompt components list.
   */
  protected function getPromptComponents(): array {
    return [
      'responseRules',
      'htmlFormatting',
      'contentStructure',
      'tone',
      'inlineContent',
      'imageHandling',
      'referenceGuidelines',
      'contextRequirements'
    ];
  }
}