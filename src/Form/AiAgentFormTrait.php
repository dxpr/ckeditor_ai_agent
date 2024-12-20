<?php

namespace Drupal\ckeditor_ai_agent\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides common form elements for AI Agent configuration.
 */
trait AiAgentFormTrait {

  /**
   * Gets the common form elements for AI Agent configuration.
   *
   * @param bool $is_plugin
   *   Whether this is for the plugin form (TRUE) or settings form (FALSE).
   * @param mixed $config
   *   Configuration object or array.
   *
   * @return array
   *   The form elements.
   */
  protected function getCommonFormElements($is_plugin = FALSE, $config = NULL) {
    $elements = [];
    
    // Initialize config based on context
    if (!$is_plugin) {
        $config = \Drupal::config('ckeditor_ai_agent.settings');
    }

    // Helper function to get config value based on context
    $getConfigValue = function($key, $default = NULL) use ($is_plugin, $config) {
        return $is_plugin 
            ? ($config['aiAgent'][$key] ?? $default)
            : ($config->get($key) ?? $default);
    };

    // Helper function to get select options with optional global settings
    $getSelectOptions = function($options) use ($is_plugin) {
        return $is_plugin 
            ? ['' => $this->t('- Use global settings -')] + $options
            : $options;
    };

    // Basic Settings
    $elements['basic_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Basic Settings'),
        '#open' => TRUE,
    ];

    $elements['basic_settings']['api_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('OpenAI API Key'),
        '#description' => $is_plugin 
            ? $this->t('Enter your OpenAI API key or leave empty to use the <a href="@settings_url">global settings</a>.', [
                '@settings_url' => \Drupal::service('url_generator')->generateFromRoute('ckeditor_ai_agent.settings'),
              ])
            : $this->t('Enter your OpenAI API key. Required for all AI functionality.'),
        '#required' => !$is_plugin,
        '#size' => 100,
        '#maxlength' => 255,
        '#default_value' => $getConfigValue('api_key'),
    ];

    $model_options = [
        'gpt-4o' => $this->t('GPT-4o (Most capable).'),
        'gpt-4o-mini' => $this->t('GPT-4o Mini (Balanced).'),
        'gpt-3.5-turbo' => $this->t('GPT-3.5 Turbo (Fastest).'),
    ];

    $elements['basic_settings']['model'] = [
        '#type' => 'select',
        '#title' => $this->t('AI Model'),
        '#options' => $getSelectOptions($model_options),
        '#description' => $this->t('Select AI model' . ($is_plugin ? ' or use global settings.' : '.')),
        '#default_value' => $getConfigValue('model'),
    ];

    $elements['basic_settings']['endpoint_url'] = [
        '#type' => 'url',
        '#title' => $this->t('API Endpoint URL'),
        '#description' => $this->t('OpenAI API endpoint URL. Only change if using a custom endpoint or proxy.'),
        '#default_value' => $getConfigValue('endpoint_url'),
    ];

    // Advanced Settings
    $elements['advanced_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Advanced Settings'),
        '#open' => FALSE,
    ];

    $elements['advanced_settings']['temperature'] = [
        '#type' => 'number',
        '#title' => $this->t('Response Creativity'),
        '#field_suffix' => $this->t('(0.0 - 2.0)'),
        '#min' => 0,
        '#max' => 2,
        '#step' => 0.1,
        '#description' => $this->t('Controls the creativity of AI responses. Low values (0.0-0.5) produce consistent, deterministic responses ideal for factual content. Medium values (0.6-1.0) offer balanced creativity. High values (1.1-2.0) generate more diverse and unexpected responses.'),
        '#default_value' => $getConfigValue('temperature', 0.7),
    ];

    // Token Settings
    $elements['advanced_settings']['tokens'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Token Limits'),
    ];

    $token_fields = ['max_output_tokens', 'max_input_tokens'];
    foreach ($token_fields as $field) {
        $elements['advanced_settings']['tokens'][$field] = [
            '#type' => 'number',
            '#title' => $this->t(str_replace('_', ' ', ucfirst($field))),
            '#description' => $this->t('Maximum number of tokens for @type. If not set, uses model\'s maximum limit', 
                ['@type' => str_contains($field, 'output') ? 'AI response' : 'combined prompt and context']),
            '#min' => 1,
            '#default_value' => $getConfigValue("tokens.$field"),
        ];
    }

    // Context Settings
    $elements['advanced_settings']['context'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Context Settings'),
    ];

    $context_fields = [
        'context_size' => [
            'title' => $this->t('Context Size'),
            'description' => $this->t('Maximum context window size in tokens. If not set, defaults to 75% of model\'s maximum input token limit.'),
            'min' => 1,
        ],
        'editor_context_ratio' => [
            'title' => $this->t('Editor Context Ratio'),
            'description' => $this->t('Portion of context for editor content. Default: 0.3 (30%).'),
            'min' => 0,
            'max' => 1,
            'step' => 0.1,
            'field_suffix' => $this->t('(0.0 - 1.0)'),
        ],
    ];

    foreach ($context_fields as $field => $settings) {
        $elements['advanced_settings']['context'][$field] = [
            '#type' => 'number',
            '#title' => $settings['title'],
            '#description' => $settings['description'],
            '#min' => $settings['min'],
            '#max' => $settings['max'] ?? NULL,
            '#step' => $settings['step'] ?? NULL,
            '#field_suffix' => $settings['field_suffix'] ?? NULL,
            '#default_value' => $getConfigValue("context.$field"),
        ];
    }

    // Performance Settings
    $elements['performance_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Performance Settings'),
        '#open' => FALSE,
    ];

    $performance_fields = [
        'timeout_duration' => [
            'title' => $this->t('Request Timeout'),
            'description' => $this->t('Maximum wait time for AI response. Default: 45000ms (45s)'),
            'min' => 1000,
            'field_suffix' => 'ms',
        ],
        'retry_attempts' => [
            'title' => $this->t('Retry Attempts'),
            'description' => $this->t('Number of retry attempts for failed requests. Default: 1'),
            'min' => 0,
        ],
    ];

    foreach ($performance_fields as $field => $settings) {
        $elements['performance_settings'][$field] = [
            '#type' => 'number',
            '#title' => $settings['title'],
            '#description' => $settings['description'],
            '#min' => $settings['min'],
            '#field_suffix' => $settings['field_suffix'] ?? NULL,
            '#default_value' => $getConfigValue("performance.$field"),
        ];
    }

    // Behavior Settings
    $elements['behavior_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Behavior Settings'),
        '#open' => FALSE,
    ];

    $boolean_options = ['0' => $this->t('Disabled'), '1' => $this->t('Enabled')];
    $behavior_fields = ['debug_mode', 'stream_content'];

    foreach ($behavior_fields as $field) {
        $elements['behavior_settings'][$field] = [
            '#type' => 'select',
            '#title' => $this->t(str_replace('_', ' ', ucfirst($field))),
            '#options' => $getSelectOptions($boolean_options),
            '#description' => $this->t('@desc', [
                '@desc' => $field === 'debug_mode' 
                    ? 'Enable detailed logging for troubleshooting purposes.'
                    : 'Display AI responses as they are generated. Provides immediate feedback but may appear less polished.'
            ]),
            '#default_value' => $getConfigValue("behavior.$field"),
        ];
    }

    $elements['behavior_settings']['show_error_duration'] = [
        '#type' => 'number',
        '#title' => $this->t('Error Message Duration'),
        '#min' => 1000,
        '#field_suffix' => 'ms',
        '#description' => $this->t('How long to display error messages. Default: 5000ms (5s)'),
        '#default_value' => $getConfigValue('behavior.show_error_duration'),
    ];

    // Moderation Settings
    $elements['moderation_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Content Moderation'),
        '#open' => FALSE,
    ];

    $elements['moderation_settings']['moderation_enable'] = $is_plugin
        ? [
            '#type' => 'select',
            '#title' => $this->t('Content Moderation'),
            '#options' => $getSelectOptions($boolean_options),
            '#description' => $this->t('Enable content safety filtering.'),
            '#default_value' => $getConfigValue('moderation.enable'),
        ]
        : [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Content Moderation'),
            '#description' => $this->t('Filter inappropriate or unsafe content. Recommended for public-facing implementations.'),
            '#default_value' => $getConfigValue('moderation.enable'),
        ];

    $elements['moderation_settings']['moderation_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Moderation API Key'),
        '#description' => $this->t('Separate API key for content moderation service. Required if using a different service than the main AI.'),
        '#default_value' => $getConfigValue('moderation.key'),
        '#states' => [
            'visible' => [
                ':input[name="moderation_enable"]' => ['checked' => TRUE],
            ],
        ],
    ];

    $moderation_flags = [
        'sexual' => $this->t('Sexual content'),
        'sexual/minors' => $this->t('Sexual content involving minors'),
        'harassment' => $this->t('Harassment'),
        'harassment/threatening' => $this->t('Threatening harassment'),
        'hate' => $this->t('Hate speech'),
        'hate/threatening' => $this->t('Threatening hate speech'),
        'illicit' => $this->t('Illicit content'),
        'illicit/violent' => $this->t('Violent illicit content'),
        'self-harm' => $this->t('Self-harm'),
        'self-harm/intent' => $this->t('Self-harm intent'),
        'self-harm/instructions' => $this->t('Self-harm instructions'),
        'violence' => $this->t('Violence'),
        'violence/graphic' => $this->t('Graphic violence'),
    ];

    $elements['moderation_settings']['moderation_disable_flags'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Disabled Safety Filters'),
        '#options' => $moderation_flags,
        '#description' => $this->t('Select content types to exclude from moderation. Use with caution.'),
        '#default_value' => $getConfigValue('moderation.disable_flags', []),
        '#states' => [
            'visible' => [
                ':input[name="moderation_enable"]' => ['checked' => TRUE],
            ],
        ],
    ];

    // Optional: Add back debug messages if needed
    if ($is_plugin) {
        \Drupal::messenger()->addStatus(t('Plugin form temperature config: @temp', 
            ['@temp' => print_r($getConfigValue('temperature', 'not set'), TRUE)]));
    } else {
        \Drupal::messenger()->addStatus(t('Global form temperature config: @temp', 
            ['@temp' => print_r($getConfigValue('temperature', 'not set'), TRUE)]));
    }

    // Add prompt settings
    $this->addPromptSettings($elements, $is_plugin, $config, $getConfigValue);

    return $elements;
  }

  /**
   * Adds prompt settings to the form elements.
   */
  protected function addPromptSettings(&$elements, $is_plugin, $config, $getConfigValue) {
    $elements['prompt_settings'] = [
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

    try {
        $module_path = \Drupal::service('extension.path.resolver')->getPath('module', 'ckeditor_ai_agent');
        $default_rules_path = $module_path . '/js/ckeditor5_plugins/aiagent/src/config/default-rules.json';
        $default_rules = file_exists($default_rules_path) 
            ? json_decode(file_get_contents($default_rules_path), TRUE) ?: []
            : [];

        foreach ($prompt_components as $key => $label) {
            $elements['prompt_settings']["override_$key"] = [
                '#type' => 'textarea',
                '#title' => $this->t('@label Override', ['@label' => $label]),
                '#default_value' => $getConfigValue("prompt_settings.overrides.$key"),
                '#placeholder' => $default_rules[$key] ?? '',
                '#description' => $this->t('Override the default @label rules. Leave empty to use the default values shown above.', ['@label' => strtolower($label)]),
                '#rows' => 6,
            ];

            $elements['prompt_settings']["additions_$key"] = [
                '#type' => 'textarea',
                '#title' => $this->t('@label Additions', ['@label' => $label]),
                '#default_value' => $getConfigValue("prompt_settings.additions.$key"),
                '#description' => $this->t('Add custom @label rules that will be appended to the defaults.', ['@label' => strtolower($label)]),
                '#rows' => 4,
            ];
        }
    }
    catch (\Exception $e) {
        \Drupal::messenger()->addError(t('Error loading prompt settings: @error', ['@error' => $e->getMessage()]));
    }
  }
}