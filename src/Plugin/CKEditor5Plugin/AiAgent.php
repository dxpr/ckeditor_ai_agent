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
    
    // Get the configuration mapping
    $config_mapping = $this->getConfigMapping(TRUE);
    
    // Initialize aiAgent configuration
    $this->configuration['aiAgent'] = [];
    
    // Handle basic settings
    foreach ($this->getSettingsMap() as $js_key => $drupal_key) {
      if (isset($values[$js_key])) {
        $this->configuration['aiAgent'][$js_key] = $values[$js_key];
      }
      elseif (isset($values['basic_settings'][$js_key])) {
        $this->configuration['aiAgent'][$js_key] = $values['basic_settings'][$js_key];
      }
    }

    // Handle prompt settings
    $prompt_settings = $this->processPromptSettings($values['promptSettings'] ?? []);
    $this->configuration['aiAgent']['promptSettings'] = $prompt_settings;
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

    // Build configuration with proper fallback handling.
    $result = ['aiAgent' => []];

    // Basic settings.
    $settings_map = $this->getSettingsMap();
    foreach ($settings_map as $js_key => $drupal_key) {
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
      'apiKey' => 'apiKey',
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
