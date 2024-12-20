<?php

declare(strict_types=1);

namespace Drupal\ckeditor_ai_agent\Plugin\CKEditor5Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;
use Drupal\ckeditor_ai_agent\Form\AiAgentFormTrait;

/**
 * CKEditor 5 AI Agent plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class AiAgent extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {
  use CKEditor5PluginConfigurableTrait;
  use AiAgentFormTrait {
    getCommonFormElements as getTraitFormElements;
  }

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
    $form = $this->getTraitFormElements(TRUE);
    
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
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addMessage('Form values in validation: ' . print_r($form_state->getValues(), TRUE));
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    $this->configuration['aiAgent'] = [
        // Basic Settings
        'apiKey' => (string) $values['basic_settings']['api_key'],
        'model' => (string) $values['basic_settings']['model'],
        'temperature' => is_numeric($values['advanced_settings']['temperature']) ? (float) $values['advanced_settings']['temperature'] : NULL,
        'endpointUrl' => (string) $values['basic_settings']['endpoint_url'],
        
        // Advanced Settings
        'maxOutputTokens' => !empty($values['advanced_settings']['tokens']['max_output_tokens']) ? (int) $values['advanced_settings']['tokens']['max_output_tokens'] : NULL,
        'maxInputTokens' => !empty($values['advanced_settings']['tokens']['max_input_tokens']) ? (int) $values['advanced_settings']['tokens']['max_input_tokens'] : NULL,
        'contextSize' => !empty($values['advanced_settings']['context']['context_size']) ? (int) $values['advanced_settings']['context']['context_size'] : NULL,
        'editorContextRatio' => is_numeric($values['advanced_settings']['context']['editor_context_ratio']) ? (float) $values['advanced_settings']['context']['editor_context_ratio'] : NULL,
        
        // Performance Settings
        'timeOutDuration' => !empty($values['performance_settings']['timeout_duration']) ? (int) $values['performance_settings']['timeout_duration'] : NULL,
        'retryAttempts' => !empty($values['performance_settings']['retry_attempts']) ? (int) $values['performance_settings']['retry_attempts'] : NULL,
        
        // Behavior Settings
        'debugMode' => !empty($values['behavior_settings']['debug_mode']) ? (bool) $values['behavior_settings']['debug_mode'] : NULL,
        'streamContent' => !empty($values['behavior_settings']['stream_content']) ? (bool) $values['behavior_settings']['stream_content'] : NULL,
        'showErrorDuration' => !empty($values['behavior_settings']['show_error_duration']) ? (int) $values['behavior_settings']['show_error_duration'] : NULL,
        
        // Moderation Settings
        'moderation' => [
            'enable' => !empty($values['moderation_settings']['moderation_enable']) ? (bool) $values['moderation_settings']['moderation_enable'] : NULL,
            'key' => (string) $values['moderation_settings']['moderation_key'],
            'disableFlags' => array_map('intval', (array) $values['moderation_settings']['moderation_disable_flags']),
        ],
        
        // Add prompt settings
        'promptSettings' => [
            'overrides' => [],
            'additions' => [],
        ],
    ];

    // Process each prompt component
    foreach ($this->getPromptComponents() as $component) {
        $this->configuration['aiAgent']['promptSettings']['overrides'][$component] = 
            $values['prompt_settings'][$component]['override'] ?? '';
        $this->configuration['aiAgent']['promptSettings']['additions'][$component] = 
            $values['prompt_settings'][$component]['additions'] ?? '';
    }
  }

  /**
   * Get prompt component keys.
   */
  private function getPromptComponents(): array {
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

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $config = \Drupal::config('ckeditor_ai_agent.settings');
    $editor_config = $this->configuration['aiAgent'] ?? [];

    // Debug logging
    \Drupal::messenger()->addStatus('Configuration inputs: ' . print_r([
        'editor_config' => $editor_config,
        'global_config' => $config->get(),
    ], TRUE));

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

    // Add before the foreach loop
    \Drupal::messenger()->addStatus('Model values: ' . print_r([
        'editor_model' => $editor_config['model'] ?? 'not set',
        'global_model' => $config->get('model'),
        'final_model' => $result['aiAgent']['model'] ?? 'not set'
    ], TRUE));

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

    // Debug final configuration
    \Drupal::messenger()->addStatus('Final configuration: ' . print_r($result, TRUE));

    return $result;
  }

  /**
   * Helper method to get typed configuration values with fallbacks.
   *
   * @param mixed $editor_value
   *   The value from editor configuration.
   * @param mixed $global_value
   *   The value from global configuration.
   * @param string $type
   *   The type to cast to ('int', 'float', or 'bool').
   * @param mixed $default
   *   Optional default value if both editor and global values are NULL.
   *
   * @return mixed
   *   The properly typed value, or NULL if no value is set.
   */
  private function getTypedValue($editor_value, $global_value, string $type, $default = NULL) {
    $value = $editor_value ?? $global_value ?? $default;
    
    if ($value === NULL) {
      return NULL;
    }

    switch ($type) {
      case 'int':
        return (int) $value;

      case 'float':
        return (float) $value;

      case 'bool':
        return (bool) $value;

      default:
        return $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCommonFormElements($include_basic = FALSE): array {
    $config = $this->getConfiguration();
    
    // Initialize elements array
    $elements = [];

    // Get all form elements from the trait using the aliased method
    $trait_elements = $this->getTraitFormElements($include_basic);
    
    // Add our custom basic settings if needed
    if ($include_basic) {
        // Make sure basic_settings exists
        if (!isset($elements['basic_settings'])) {
            $elements['basic_settings'] = [
                '#type' => 'details',
                '#title' => $this->t('Basic Settings'),
                '#open' => TRUE,
            ];
        }

        // Add API key with our specific configuration
        $elements['basic_settings']['api_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('API Key'),
            '#default_value' => $config['aiAgent']['apiKey'] ?? '',
            '#description' => $this->t('Your OpenAI API key. Leave empty to use global settings.'),
            '#weight' => -99,
        ];
    }

    // Merge trait elements with our elements
    $elements = array_merge($elements, $trait_elements);

    // Set default values from our configuration
    if (isset($config['aiAgent'])) {
        if (isset($elements['basic_settings'])) {
            $elements['basic_settings']['model']['#default_value'] = $config['aiAgent']['model'] ?? '';
            $elements['advanced_settings']['temperature']['#default_value'] = $config['aiAgent']['temperature'] ?? '';
            $elements['basic_settings']['endpoint_url']['#default_value'] = $config['aiAgent']['endpointUrl'] ?? '';
        }

        // Set other default values
        if (isset($elements['advanced_settings'])) {
            $elements['advanced_settings']['tokens']['max_output_tokens']['#default_value'] = $config['aiAgent']['maxOutputTokens'] ?? '';
            $elements['advanced_settings']['tokens']['max_input_tokens']['#default_value'] = $config['aiAgent']['maxInputTokens'] ?? '';
            $elements['advanced_settings']['context']['context_size']['#default_value'] = $config['aiAgent']['contextSize'] ?? '';
            $elements['advanced_settings']['context']['editor_context_ratio']['#default_value'] = $config['aiAgent']['editorContextRatio'] ?? '';
        }
    }

    return $elements;
  }

}
