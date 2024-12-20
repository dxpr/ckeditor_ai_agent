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
   */
  public function defaultConfiguration(): array {
    return [
      'aiAgent' => [
        'apiKey' => NULL,
        'model' => NULL,
        'endpointUrl' => NULL,
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
        'moderation' => [
          'enable' => NULL,
          'key' => NULL,
          'disableFlags' => [
            'sexual' => 0,
            'sexual/minors' => 0,
            'harassment' => 0,
            'harassment/threatening' => 0,
            'hate' => 0,
            'hate/threatening' => 0,
            'illicit' => 0,
            'illicit/violent' => 0,
            'self-harm' => 0,
            'self-harm/intent' => 0,
            'self-harm/instructions' => 0,
            'violence' => 0,
            'violence/graphic' => 0,
          ],
        ],
        'promptSettings' => [
          'overrides' => [],
          'additions' => [],
        ],
      ],
      'test_field' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->getConfiguration();
    
    // Get common form elements
    $form = $this->getCommonFormElements(TRUE);
    
    // Set default values for all form elements from our configuration
    if (isset($config['aiAgent'])) {
        // Basic Settings
        $form['basic_settings']['api_key']['#default_value'] = $config['aiAgent']['apiKey'] ?? '';
        $form['basic_settings']['model']['#default_value'] = $config['aiAgent']['model'] ?? '';
        $form['advanced_settings']['temperature']['#default_value'] = $config['aiAgent']['temperature'] ?? '';
        $form['basic_settings']['endpoint_url']['#default_value'] = $config['aiAgent']['endpointUrl'] ?? '';

        // Advanced Settings
        if (isset($form['advanced_settings'])) {
            $form['advanced_settings']['tokens']['max_output_tokens']['#default_value'] = $config['aiAgent']['maxOutputTokens'] ?? '';
            $form['advanced_settings']['tokens']['max_input_tokens']['#default_value'] = $config['aiAgent']['maxInputTokens'] ?? '';
            $form['advanced_settings']['context']['context_size']['#default_value'] = $config['aiAgent']['contextSize'] ?? '';
            $form['advanced_settings']['context']['editor_context_ratio']['#default_value'] = $config['aiAgent']['editorContextRatio'] ?? '';
        }

        // Performance Settings
        if (isset($form['performance_settings'])) {
            $form['performance_settings']['timeout_duration']['#default_value'] = $config['aiAgent']['timeOutDuration'] ?? '';
            $form['performance_settings']['retry_attempts']['#default_value'] = $config['aiAgent']['retryAttempts'] ?? '';
        }

        // Behavior Settings
        if (isset($form['behavior_settings'])) {
            $form['behavior_settings']['debug_mode']['#default_value'] = $config['aiAgent']['debugMode'] ?? '';
            $form['behavior_settings']['stream_content']['#default_value'] = $config['aiAgent']['streamContent'] ?? '';
            $form['behavior_settings']['show_error_duration']['#default_value'] = $config['aiAgent']['showErrorDuration'] ?? '';
        }

        // Moderation Settings
        if (isset($form['moderation_settings'])) {
            $form['moderation_settings']['moderation_enable']['#default_value'] = $config['aiAgent']['moderation']['enable'] ?? '';
            $form['moderation_settings']['moderation_key']['#default_value'] = $config['aiAgent']['moderation']['key'] ?? '';
            if (isset($config['aiAgent']['moderation']['disableFlags'])) {
                $form['moderation_settings']['moderation_disable_flags']['#default_value'] = $config['aiAgent']['moderation']['disableFlags'];
            }
        }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    $this->configuration['aiAgent'] = $this->processConfigValues(
      $values, 
      $this->getConfigMapping(TRUE)
    );
    
    // Handle moderation and prompt settings
    $this->configuration['aiAgent']['moderation'] = $this->processModerationSettings($values);
    $this->configuration['aiAgent']['promptSettings'] = $this->processPromptSettings($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $config = \Drupal::config('ckeditor_ai_agent.settings');
    $editor_config = $this->configuration['aiAgent'] ?? [];

    // Build configuration with proper fallback handling
    $result = ['aiAgent' => []];
    
    // Basic settings
    $settings_map = [
        'apiKey' => 'api_key',
        'model' => 'model',
        'endpointUrl' => 'endpoint_url',
        'temperature' => 'temperature',
        'maxOutputTokens' => 'max_output_tokens',
        'maxInputTokens' => 'max_input_tokens',
        'contextSize' => 'context_size',
        'editorContextRatio' => 'editor_context_ratio',
        'timeOutDuration' => 'timeout_duration',
        'retryAttempts' => 'retry_attempts',
        'debugMode' => 'debug_mode',
        'streamContent' => 'stream_content',
        'showErrorDuration' => 'show_error_duration',
    ];

    foreach ($settings_map as $js_key => $drupal_key) {
        // Only set if either editor config or global config has a non-null value
        if (isset($editor_config[$js_key]) && !empty($editor_config[$js_key])) {
            $result['aiAgent'][$js_key] = $editor_config[$js_key];
        } elseif ($config->get($drupal_key) !== NULL && !empty($config->get($drupal_key))) {
            $result['aiAgent'][$js_key] = $config->get($drupal_key);
        }
    }

    // Moderation settings
    if (isset($editor_config['moderation']) || $config->get('moderation')) {
        $result['aiAgent']['moderation'] = [
            'enable' => $editor_config['moderation']['enable'] ?? $config->get('moderation.enable'),
            'key' => $editor_config['moderation']['key'] ?? $config->get('moderation.key'),
            'disableFlags' => $editor_config['moderation']['disableFlags'] ?? $config->get('moderation.disable_flags'),
        ];
    }

    // Prompt settings
    if (isset($editor_config['promptSettings']) || $config->get('prompt_settings')) {
        $result['aiAgent']['promptSettings'] = [
            'overrides' => [],
            'additions' => [],
        ];

        foreach (['overrides', 'additions'] as $type) {
            $editor_settings = $editor_config['promptSettings'][$type] ?? [];
            $global_settings = $config->get("prompt_settings.$type") ?? [];

            foreach ($this->getPromptComponents() as $component) {
                if (isset($editor_settings[$component])) {
                    $result['aiAgent']['promptSettings'][$type][$component] = $editor_settings[$component];
                } elseif (isset($global_settings[$component])) {
                    $result['aiAgent']['promptSettings'][$type][$component] = $global_settings[$component];
                }
            }
        }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Required by interface, but no validation needed
  }

}
