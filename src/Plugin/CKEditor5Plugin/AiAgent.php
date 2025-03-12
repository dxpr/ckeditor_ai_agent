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
        'toneOfVoiceVocabulary' => NULL,
        'tonesDropdown' => [],
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
        // First check for editor-specific key provider
        if (isset($editor_config['key_provider']) && $editor_config['key_provider'] !== '') {
          $result['aiAgent'][$js_key] = $key_service->getKeyValue($editor_config['key_provider']);
        }
        // Then fall back to global key
        else {
          $result['aiAgent'][$js_key] = $key_service->getApiKey();
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
    
    // Add taxonomy-based tones of voice if configured
    $tone_vocabulary = $config->get('toneOfVoiceVocabulary');
    
    if (!empty($tone_vocabulary)) {
      try {
        // Load the terms from the vocabulary, sorted by weight
        $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
        $query = $term_storage->getQuery()
          ->condition('vid', $tone_vocabulary)
          ->sort('weight')
          ->accessCheck(FALSE);
        $tids = $query->execute();
        
        if (!empty($tids)) {
          $terms = $term_storage->loadMultiple($tids);
          $tones_dropdown = [];
          $default_tone = NULL;
          $first_term = NULL;
          
          // Add each taxonomy term as a tone option
          foreach ($terms as $term) {
            $description = $term->getDescription();
            // Only add terms that have a description (tone)
            if (!empty($description)) {
              $tone_item = [
                'label' => $term->label(),
                'tone' => $description,
              ];
              
              $tones_dropdown[] = $tone_item;
              
              // Keep track of the first valid term (lowest weight) to use as default
              if ($first_term === NULL) {
                $first_term = $tone_item;
              }
            }
          }
          
          // Only add the tones to the configuration if we have valid tones
          if (!empty($tones_dropdown)) {
            // Use the exact key expected by the plugin
            $result['aiAgent']['tonesDropdown'] = $tones_dropdown;
            
            // Set the first term (lowest weight) as the default tone
            if ($first_term !== NULL) {
              $result['aiAgent']['defaultTone'] = $first_term;
            }
          }
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('ckeditor_ai_agent')->error('Error loading tone of voice taxonomy terms in AiAgent plugin: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }

    // Handle prompt settings
    $result['aiAgent']['promptSettings'] = [
      'overrides' => [],
      'additions' => [],
    ];

    foreach (['overrides', 'additions'] as $type) {
      $editor_settings = $editor_config['promptSettings'][$type] ?? [];
      $global_settings = $config->get("promptSettings.$type") ?? [];

      foreach ($this->getPromptComponents() as $component) {
        // Special handling for tone when using taxonomy integration
        if ($component === 'tone' && !empty($tone_vocabulary) && !empty($first_term)) {
          // For overrides, use the first term's tone as the tone
          if ($type === 'overrides') {
            $result['aiAgent']['promptSettings'][$type][$component] = $first_term['tone'];
          }
          // Skip additions for tone when using taxonomy
          continue;
        }
        
        if (!empty($editor_settings[$component])) {
          $result['aiAgent']['promptSettings'][$type][$component] = $editor_settings[$component];
        }
        elseif (!empty($global_settings[$component])) {
          $result['aiAgent']['promptSettings'][$type][$component] = $global_settings[$component];
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
      'toneOfVoiceVocabulary' => 'toneOfVoiceVocabulary',
    ];
  }

}
