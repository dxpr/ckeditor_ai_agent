<?php

declare(strict_types=1);

namespace Drupal\ckeditor_ai_agent\Plugin\CKEditor5Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;
use Drupal\ckeditor_ai_agent\Form\AiAgentFormTrait;
use Drupal\ckeditor_ai_agent\Form\ConfigSetterTrait;
use Drupal\ckeditor_ai_agent\Form\ConfigMappingTrait;

/**
 * CKEditor 5 AI Agent plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class AiAgent extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {
  use CKEditor5PluginConfigurableTrait;
  use AiAgentFormTrait;
  use ConfigSetterTrait;
  use ConfigMappingTrait;

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<string, mixed>
   */
  public function defaultConfiguration(): array {
    return [
      'aiAgent' => [
        'apiKey' => NULL,
        'engine' => NULL,
        'model' => NULL,
        'ollamaModel' => NULL,
        'endpointUrl' => NULL,
        'contentScope' => NULL,
        'temperature' => NULL,
        'maxOutputTokens' => NULL,
        'maxInputTokens' => NULL,
        'contextSize' => NULL,
        'editorContextRatio' => NULL,
        'timeOutDuration' => NULL,
        'retryAttempts' => NULL,
        'debugMode' => NULL,
        'streamContent' => NULL,
        'showErrorDuration' => NULL,
        'moderationEnable' => NULL,
        'moderationKey' => NULL,
        'promptSettings' => [
          'overrides' => [],
          'additions' => [],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $form
   * @phpstan-return array<string, mixed>
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $this->getCommonFormElements(TRUE, $this->configuration);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $form
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    
    // Initialize aiAgent configuration
    $this->configuration['aiAgent'] = [];
    
    // Handle basic settings
    foreach ($this->getSettingsMap() as $js_key => $drupal_key) {
      // Special handling for key_provider which is in basic_settings
      if ($js_key === 'apiKey') {
        if (isset($values['basic_settings']['key_provider'])) {
          $this->configuration['aiAgent']['key_provider'] = $values['basic_settings']['key_provider'];
        }
        continue;
      }

      // Handle other settings
      if (isset($values['basic_settings'][$js_key])) {
        $this->configuration['aiAgent'][$js_key] = $values['basic_settings'][$js_key];
      }
    }

    // Handle prompt settings
    if (isset($values['promptSettings'])) {
      $prompt_settings = $this->processPromptSettings($values['promptSettings']);
      $this->configuration['aiAgent']['promptSettings'] = $prompt_settings;
    }

    // Log the final configuration for debugging
    \Drupal::logger('ckeditor_ai_agent')->debug('Submitted configuration: @config', [
      '@config' => print_r($this->configuration, TRUE),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $static_plugin_config
   * @phpstan-return array<string, mixed>
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $config = \Drupal::config('ckeditor_ai_agent.settings');
    $editor_config = $this->configuration['aiAgent'] ?? [];
    $key_service = \Drupal::service('ckeditor_ai_agent.key_service');

    // Build configuration with proper fallback handling.
    $result = ['aiAgent' => []];

    // Basic settings.
    $settings_map = $this->getSettingsMap();
    foreach ($settings_map as $js_key => $drupal_key) {
      // Handle apiKey separately to use key service
      if ($js_key === 'apiKey') {
        // Log the editor configuration for debugging
        \Drupal::logger('ckeditor_ai_agent')->debug('Editor config for @editor_id: @config', [
          '@editor_id' => $editor->id(),
          '@config' => print_r($editor_config, TRUE),
        ]);

        // First check for editor-specific key provider
        if (isset($editor_config['key_provider']) && $editor_config['key_provider'] !== '') {
          \Drupal::logger('ckeditor_ai_agent')->debug('Using editor-specific key provider: @key_provider', [
            '@key_provider' => $editor_config['key_provider'],
          ]);
          $result['aiAgent'][$js_key] = $key_service->getKeyValue($editor_config['key_provider']);
          \Drupal::logger('ckeditor_ai_agent')->debug('Editor-specific key value retrieved: @key_value', [
            '@key_value' => substr($result['aiAgent'][$js_key] ?? '', 0, 10) . '...',
          ]);
        }
        // Then fall back to global key
        else {
          \Drupal::logger('ckeditor_ai_agent')->debug('No editor-specific key found or empty value, falling back to global key');
          $result['aiAgent'][$js_key] = $key_service->getApiKey();
          \Drupal::logger('ckeditor_ai_agent')->debug('Global key value retrieved: @key_value', [
            '@key_value' => substr($result['aiAgent'][$js_key] ?? '', 0, 10) . '...',
          ]);
        }
        continue;
      }

      // Only set if either editor config or global config has a non-null value.
      if (isset($editor_config[$js_key]) && !empty($editor_config[$js_key])) {
        $result['aiAgent'][$js_key] = $editor_config[$js_key];
      }
      elseif ($config->get($drupal_key) !== NULL && !empty($config->get($drupal_key))) {
        $result['aiAgent'][$js_key] = $config->get($drupal_key);
      }
    }

    // Handle engine/model
    $model = $result['aiAgent']['model'] ?? 'openai:gpt-4o';
    if (str_contains($model, ':')) {
      [$engine, $model_name] = explode(':', $model, 2);
      $result['aiAgent']['engine'] = $engine;
      if ($engine === 'ollama') {
        // For Ollama, use the ollamaModel value
        $result['aiAgent']['model'] = $result['aiAgent']['ollamaModel'] ?? $config->get('ollamaModel') ?? '';
      } else {
        $result['aiAgent']['model'] = $model_name;
      }
    } else {
      // Fallback for legacy configurations
      $result['aiAgent']['engine'] = 'openai';
      $result['aiAgent']['model'] = $model ?: 'gpt-4o';
    }

    // Prompt settings.
    if (isset($editor_config['promptSettings']) || $config->get('promptSettings')) {
      $result['aiAgent']['promptSettings'] = [
        'overrides' => [],
        'additions' => [],
      ];

      foreach (['overrides', 'additions'] as $type) {
        $editor_settings = $editor_config['promptSettings'][$type] ?? [];
        $global_settings = $config->get("promptSettings.$type") ?? [];

        foreach ($this->getPromptComponents() as $component) {
          if (!empty($editor_settings[$component])) {
            $result['aiAgent']['promptSettings'][$type][$component] = $editor_settings[$component];
          }
          elseif (!empty($global_settings[$component])) {
            $result['aiAgent']['promptSettings'][$type][$component] = $global_settings[$component];
          }
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $form
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Required by interface, but no validation needed.
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
    ];
  }

}
