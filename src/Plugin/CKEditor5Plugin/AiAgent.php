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
        $form['basic_settings']['temperature']['#default_value'] = $config['aiAgent']['temperature'] ?? '';
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
        'temperature' => is_numeric($values['basic_settings']['temperature']) ? (float) $values['basic_settings']['temperature'] : NULL,
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
    ];
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
    // Get global settings.
    $config = \Drupal::config('ckeditor_ai_agent.settings');
    $editor_config = $this->configuration['aiAgent'] ?? [];

    // Build config with global fallbacks.
    return [
      'aiAgent' => [
        'apiKey' => $editor_config['apiKey'] ?? $config->get('api_key'),
        'model' => $editor_config['model'] ?? $config->get('model') ?? 'gpt-4o',
        'endpointUrl' => $editor_config['endpointUrl'] ?? $config->get('endpoint_url') ?? 'https://api.openai.com/v1/chat/completions',
        
        // Numeric configurations with type casting.
        'temperature' => $this->getTypedValue($editor_config['temperature'], $config->get('temperature'), 'float'),
        'maxOutputTokens' => $this->getTypedValue($editor_config['maxOutputTokens'], $config->get('max_output_tokens'), 'int'),
        'maxInputTokens' => $this->getTypedValue($editor_config['maxInputTokens'], $config->get('max_input_tokens'), 'int'),
        'contextSize' => $this->getTypedValue($editor_config['contextSize'], $config->get('context_size'), 'int'),
        'editorContextRatio' => $this->getTypedValue($editor_config['editorContextRatio'], $config->get('editor_context_ratio'), 'float', 0.3),
        'timeoutDuration' => $this->getTypedValue($editor_config['timeOutDuration'], $config->get('timeout_duration'), 'int', 45000),
        'retryAttempts' => $this->getTypedValue($editor_config['retryAttempts'], $config->get('retry_attempts'), 'int', 1),
        
        // Boolean configurations.
        'debugMode' => $this->getTypedValue($editor_config['debugMode'], $config->get('debug_mode'), 'bool', FALSE),
        'streamContent' => $this->getTypedValue($editor_config['streamContent'], $config->get('stream_content'), 'bool', TRUE),
        'showErrorDuration' => $this->getTypedValue($editor_config['showErrorDuration'], $config->get('show_error_duration'), 'int', 5000),
        
        // Moderation settings handled separately.
        'moderation' => [
          'enable' => $this->getTypedValue($editor_config['moderation']['enable'] ?? NULL, $config->get('moderation.enable'), 'bool', FALSE),
          'key' => $editor_config['moderation']['key'] ?? $config->get('moderation.key'),
          'disableFlags' => $editor_config['moderation']['disableFlags'] ?? $config->get('moderation.disable_flags') ?? [],
        ],
        'promptSettings' => [
          'overrides' => [
            'responseRules' => $this->configuration['aiAgent']['promptSettings']['overrides']['responseRules'] ?? $config->get('prompt_settings.overrides.responseRules'),
            'htmlFormatting' => $this->configuration['aiAgent']['promptSettings']['overrides']['htmlFormatting'] ?? $config->get('prompt_settings.overrides.htmlFormatting'),
            'contentStructure' => $this->configuration['aiAgent']['promptSettings']['overrides']['contentStructure'] ?? $config->get('prompt_settings.overrides.contentStructure'),
            'tone' => $this->configuration['aiAgent']['promptSettings']['overrides']['tone'] ?? $config->get('prompt_settings.overrides.tone'),
            'inlineContent' => $this->configuration['aiAgent']['promptSettings']['overrides']['inlineContent'] ?? $config->get('prompt_settings.overrides.inlineContent'),
            'imageHandling' => $this->configuration['aiAgent']['promptSettings']['overrides']['imageHandling'] ?? $config->get('prompt_settings.overrides.imageHandling'),
            'referenceGuidelines' => $this->configuration['aiAgent']['promptSettings']['overrides']['referenceGuidelines'] ?? $config->get('prompt_settings.overrides.referenceGuidelines'),
            'contextRequirements' => $this->configuration['aiAgent']['promptSettings']['overrides']['contextRequirements'] ?? $config->get('prompt_settings.overrides.contextRequirements'),
          ],
          'additions' => [
            'responseRules' => $this->configuration['aiAgent']['promptSettings']['additions']['responseRules'] ?? $config->get('prompt_settings.additions.responseRules'),
            'htmlFormatting' => $this->configuration['aiAgent']['promptSettings']['additions']['htmlFormatting'] ?? $config->get('prompt_settings.additions.htmlFormatting'),
            'contentStructure' => $this->configuration['aiAgent']['promptSettings']['additions']['contentStructure'] ?? $config->get('prompt_settings.additions.contentStructure'),
            'tone' => $this->configuration['aiAgent']['promptSettings']['additions']['tone'] ?? $config->get('prompt_settings.additions.tone'),
            'inlineContent' => $this->configuration['aiAgent']['promptSettings']['additions']['inlineContent'] ?? $config->get('prompt_settings.additions.inlineContent'),
            'imageHandling' => $this->configuration['aiAgent']['promptSettings']['additions']['imageHandling'] ?? $config->get('prompt_settings.additions.imageHandling'),
            'referenceGuidelines' => $this->configuration['aiAgent']['promptSettings']['additions']['referenceGuidelines'] ?? $config->get('prompt_settings.additions.referenceGuidelines'),
            'contextRequirements' => $this->configuration['aiAgent']['promptSettings']['additions']['contextRequirements'] ?? $config->get('prompt_settings.additions.contextRequirements'),
          ],
        ],
      ],
    ];
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
            $elements['basic_settings']['temperature']['#default_value'] = $config['aiAgent']['temperature'] ?? '';
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
