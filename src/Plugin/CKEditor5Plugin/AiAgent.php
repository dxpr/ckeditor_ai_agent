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
  use AiAgentFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => NULL,
      'model' => NULL,
      'endpoint_url' => NULL,
      'temperature' => NULL,
      'max_output_tokens' => NULL,
      'max_input_tokens' => NULL,
      'context_size' => NULL,
      'editor_context_ratio' => NULL,
      'timeout_duration' => NULL,
      'retry_attempts' => NULL,
      'debug_mode' => NULL,
      'stream_content' => NULL,
      'show_error_duration' => NULL,
      'moderation_enable' => NULL,
      'moderation_key' => NULL,
      'moderation_disable_flags' => NULL,
      'prompt_settings_overrides_responseRules' => NULL,
      'prompt_settings_overrides_htmlFormatting' => NULL,
      'prompt_settings_overrides_contentStructure' => NULL,
      'prompt_settings_overrides_tone' => NULL,
      'prompt_settings_overrides_inlineContent' => NULL,
      'prompt_settings_overrides_imageHandling' => NULL,
      'prompt_settings_overrides_referenceGuidelines' => NULL,
      'prompt_settings_overrides_contextRequirements' => NULL,
      'prompt_settings_additions_responseRules' => NULL,
      'prompt_settings_additions_htmlFormatting' => NULL,
      'prompt_settings_additions_contentStructure' => NULL,
      'prompt_settings_additions_tone' => NULL,
      'prompt_settings_additions_inlineContent' => NULL,
      'prompt_settings_additions_imageHandling' => NULL,
      'prompt_settings_additions_referenceGuidelines' => NULL,
      'prompt_settings_additions_contextRequirements' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Get common form elements
    $form = $this->getCommonFormElements(TRUE);

    // Load default rules from JSON file for prompt settings
    try {
      $module_path = \Drupal::service('extension.path.resolver')->getPath('module', 'ckeditor_ai_agent');
      $default_rules_path = $module_path . '/js/ckeditor5_plugins/aiagent/src/config/default-rules.json';
      
      if (!file_exists($default_rules_path)) {
        throw new \Exception('Default rules file not found');
      }
      
      $default_rules = json_decode(file_get_contents($default_rules_path), TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Invalid JSON in default rules file');
      }

      // Add prompt settings section
      $form['prompt_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Prompt Settings'),
        '#open' => FALSE,
      ];

      $prompt_components = [
        'responseRules' => $this->t('Response Rules'),
        'htmlFormatting' => $this->t('HTML Formatting'),
        'contentStructure' => $this->t('Content Structure'),
        'tone' => $this->t('Tone'),
        'inlineContent' => $this->t('Inline Content'),
        'imageHandling' => $this->t('Image Handling'),
        'referenceGuidelines' => $this->t('Reference Guidelines'),
        'contextRequirements' => $this->t('Context Requirements'),
      ];

      foreach ($prompt_components as $key => $label) {
        $form['prompt_settings'][$key] = [
          '#type' => 'details',
          '#title' => $label,
          '#open' => FALSE,
        ];

        $form['prompt_settings'][$key]['override'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Override Rules'),
          '#default_value' => $this->configuration["prompt_settings_overrides_$key"],
          '#placeholder' => $default_rules[$key] ?? '',
          '#description' => $this->t('Override default rules. Leave empty to use global settings.'),
          '#rows' => 6,
        ];

        $form['prompt_settings'][$key]['additions'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Additional Rules'),
          '#default_value' => $this->configuration["prompt_settings_additions_$key"],
          '#description' => $this->t('Add rules to append to the global settings.'),
          '#rows' => 4,
        ];
      }
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Error loading prompt settings: @error', ['@error' => $e->getMessage()]));
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $temperature = $form_state->getValue('temperature');
    if ($temperature !== '' && !is_null($temperature)) {
      $temperature = (float) $temperature;
      if ($temperature < 0 || $temperature > 2) {
        $form_state->setErrorByName('temperature', $this->t('Temperature must be between 0 and 2.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration = array_merge(
        $this->defaultConfiguration(),
        array_filter(
            [
                'api_key' => $form_state->getValue('api_key'),
                'model' => $form_state->getValue('model'),
                'endpoint_url' => $form_state->getValue('endpoint_url'),
                'temperature' => $form_state->getValue('temperature'),
                'max_output_tokens' => $form_state->getValue('max_output_tokens'),
                'max_input_tokens' => $form_state->getValue('max_input_tokens'),
                'context_size' => $form_state->getValue('context_size'),
                'editor_context_ratio' => $form_state->getValue('editor_context_ratio'),
                'timeout_duration' => $form_state->getValue('timeout_duration'),
                'retry_attempts' => $form_state->getValue('retry_attempts'),
                'debug_mode' => $form_state->getValue('debug_mode'),
                'stream_content' => $form_state->getValue('stream_content'),
                'show_error_duration' => $form_state->getValue('show_error_duration'),
                'moderation_enable' => $form_state->getValue('moderation_enable'),
                'moderation_key' => $form_state->getValue('moderation_key'),
                'moderation_disable_flags' => $form_state->getValue('moderation_disable_flags'),
            ],
            function ($value) {
                return $value !== '' && $value !== NULL;
            }
        )
    );

    // Handle prompt settings
    foreach ($this->getPromptComponents() as $key) {
        $override_value = $form_state->getValue(['prompt_settings', $key, 'override']);
        $additions_value = $form_state->getValue(['prompt_settings', $key, 'additions']);
        
        if ($override_value !== '' && $override_value !== NULL) {
            $this->configuration["prompt_settings_overrides_$key"] = $override_value;
        }
        if ($additions_value !== '' && $additions_value !== NULL) {
            $this->configuration["prompt_settings_additions_$key"] = $additions_value;
        }
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
    // Get global settings.
    $config = \Drupal::config('ckeditor_ai_agent.settings');

    // Helper function to safely cast values
    $safeInt = function ($value) {
        return ($value !== null && $value !== '') ? (int) $value : null;
    };
    $safeFloat = function ($value) {
        return ($value !== null && $value !== '') ? (float) $value : null;
    };
    $safeBool = function ($value) {
        return ($value !== null && $value !== '') ? (bool) $value : null;
    };

    // Build config with global fallbacks.
    return [
      'aiAgent' => [
        'apiKey' => $this->configuration['api_key'] ?? $config->get('api_key'),
        'model' => $this->configuration['model'] ?? $config->get('model') ?? 'gpt-4o',
        'endpointUrl' => $this->configuration['endpoint_url'] ?? $config->get('endpoint_url') ?? 'https://api.openai.com/v1/chat/completions',
        'temperature' => $safeFloat($this->configuration['temperature'] ?? $config->get('temperature')),
        'maxOutputTokens' => $safeInt($this->configuration['max_output_tokens'] ?? $config->get('max_output_tokens')),
        'maxInputTokens' => $safeInt($this->configuration['max_input_tokens'] ?? $config->get('max_input_tokens')),
        'contextSize' => $safeInt($this->configuration['context_size'] ?? $config->get('context_size')),
        'editorContextRatio' => $safeFloat($this->configuration['editor_context_ratio'] ?? $config->get('editor_context_ratio') ?? 0.3),
        'timeoutDuration' => $safeInt($this->configuration['timeout_duration'] ?? $config->get('timeout_duration') ?? 45000),
        'retryAttempts' => $safeInt($this->configuration['retry_attempts'] ?? $config->get('retry_attempts') ?? 1),
        'debugMode' => $safeBool($this->configuration['debug_mode'] ?? $config->get('debug_mode') ?? FALSE),
        'streamContent' => $safeBool($this->configuration['stream_content'] ?? $config->get('stream_content') ?? TRUE),
        'showErrorDuration' => $safeInt($this->configuration['show_error_duration'] ?? $config->get('show_error_duration') ?? 5000),
        'moderation' => [
          'enable' => (bool) ($this->configuration['moderation_enable'] ?? $config->get('moderation.enable') ?? FALSE),
          'key' => $this->configuration['moderation_key'] ?? $config->get('moderation.key'),
          'disableFlags' => $this->configuration['moderation_disable_flags'] ?? $config->get('moderation.disable_flags') ?? [],
        ],
        'promptSettings' => [
          'overrides' => [
            'responseRules' => $this->configuration['prompt_settings_overrides_responseRules'] ?? $config->get('prompt_settings.overrides.responseRules'),
            'htmlFormatting' => $this->configuration['prompt_settings_overrides_htmlFormatting'] ?? $config->get('prompt_settings.overrides.htmlFormatting'),
            'contentStructure' => $this->configuration['prompt_settings_overrides_contentStructure'] ?? $config->get('prompt_settings.overrides.contentStructure'),
            'tone' => $this->configuration['prompt_settings_overrides_tone'] ?? $config->get('prompt_settings.overrides.tone'),
            'inlineContent' => $this->configuration['prompt_settings_overrides_inlineContent'] ?? $config->get('prompt_settings.overrides.inlineContent'),
            'imageHandling' => $this->configuration['prompt_settings_overrides_imageHandling'] ?? $config->get('prompt_settings.overrides.imageHandling'),
            'referenceGuidelines' => $this->configuration['prompt_settings_overrides_referenceGuidelines'] ?? $config->get('prompt_settings.overrides.referenceGuidelines'),
            'contextRequirements' => $this->configuration['prompt_settings_overrides_contextRequirements'] ?? $config->get('prompt_settings.overrides.contextRequirements'),
          ],
          'additions' => [
            'responseRules' => $this->configuration['prompt_settings_additions_responseRules'] ?? $config->get('prompt_settings.additions.responseRules'),
            'htmlFormatting' => $this->configuration['prompt_settings_additions_htmlFormatting'] ?? $config->get('prompt_settings.additions.htmlFormatting'),
            'contentStructure' => $this->configuration['prompt_settings_additions_contentStructure'] ?? $config->get('prompt_settings.additions.contentStructure'),
            'tone' => $this->configuration['prompt_settings_additions_tone'] ?? $config->get('prompt_settings.additions.tone'),
            'inlineContent' => $this->configuration['prompt_settings_additions_inlineContent'] ?? $config->get('prompt_settings.additions.inlineContent'),
            'imageHandling' => $this->configuration['prompt_settings_additions_imageHandling'] ?? $config->get('prompt_settings.additions.imageHandling'),
            'referenceGuidelines' => $this->configuration['prompt_settings_additions_referenceGuidelines'] ?? $config->get('prompt_settings.additions.referenceGuidelines'),
            'contextRequirements' => $this->configuration['prompt_settings_additions_contextRequirements'] ?? $config->get('prompt_settings.additions.contextRequirements'),
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    if (empty($this->configuration)) {
      $this->configuration = $this->defaultConfiguration();
    }
    else {
      $this->configuration = $configuration;
    }
  }

}
