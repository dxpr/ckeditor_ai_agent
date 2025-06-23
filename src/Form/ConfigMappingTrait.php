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
   *
   * @return array<string, mixed>
   *   The configuration mapping array.
   */
  protected function getConfigMapping(bool $is_plugin = FALSE): array {
    $base_mapping = [
      'basic_settings.apiKey' => [
        'type' => 'string',
      ],
      'basic_settings.model' => [
        'type' => 'string',
      ],
      'basic_settings.endpointUrl' => [
        'type' => 'string',
      ],
      'basic_settings.contentScope' => [
        'type' => 'string',
      ],
      'advanced_settings.temperature' => [
        'type' => 'float',
      ],
      'advanced_settings.tokens.maxOutputTokens' => [
        'type' => 'int',
      ],
      'advanced_settings.tokens.maxInputTokens' => [
        'type' => 'int',
      ],
      'advanced_settings.context.contextSize' => [
        'type' => 'int',
      ],
      'advanced_settings.context.editorContextRatio' => [
        'type' => 'float',
      ],
      'performance_settings.timeOutDuration' => [
        'type' => 'int',
      ],
      'performance_settings.retryAttempts' => [
        'type' => 'int',
      ],
      'behavior_settings.debugMode' => [
        'type' => 'bool',
      ],
      'behavior_settings.streamContent' => [
        'type' => 'bool',
      ],
      'behavior_settings.showErrorDuration' => [
        'type' => 'int',
      ],
      'moderation_settings.moderationEnable' => [
        'type' => 'bool',
      ],
      'moderation_settings.moderationKey' => [
        'type' => 'string',
      ],
      'tone_of_voice.toneOfVoiceVocabulary' => [
        'type' => 'string',
      ],
      'commands.commandsVocabulary' => [
        'type' => 'string',
      ],
    ];

    $settings_mapping = [];
    $settingsMap = $this->getSettingsMap();
    foreach ($base_mapping as $path => $settings) {
      $parts = explode('.', $path);
      $config_key = end($parts);
      // Replace the last part of the path with the mapped key if it exists.
      $config_key = isset($settingsMap[$config_key]) ? $settingsMap[$config_key] : $config_key; 
      $settings_mapping[$path] = [
        'type' => $settings['type'],
        'config_key' => $config_key,
      ];
    }
    return $settings_mapping;
  }

  /**
   * Gets the prompt components list.
   *
   * @return string[]
   *   The prompt components.
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
      'contextRequirements',
    ];
  }

  /**
   * Gets the settings map.
   *
   * @return array<string, string>
   *   The settings map.
   */
  protected function getSettingsMap(): array {
    return [
      'apiKey' => 'key_provider',
      'model' => 'model',
      'ollamaModel' => 'ollamaModel',
      'endpointUrl' => 'endpointUrl',
      'contentScope' => 'contentScope',
      'temperature' => 'temperature',
      'maxOutputTokens' => 'maxOutputTokens',
      'maxInputTokens' => 'maxInputTokens',
      'contextSize' => 'contextSize',
      'editorContextRatio' => 'editorContextRatio',
      'timeOutDuration' => 'timeOutDuration',
      'retryAttempts' => 'retryAttempts',
      'debugMode' => 'debugMode',
      'streamContent' => 'streamContent',
      'showErrorDuration' => 'showErrorDuration',
      'moderationEnable' => 'moderationEnable',
      'moderationKey' => 'moderationKey',
      'toneOfVoiceVocabulary' => 'toneOfVoiceVocabulary',
      'commandsVocabulary' => 'commandsVocabulary',
      'defaultToneOfVoice' => 'defaultToneOfVoice',
    ];
  }

}
