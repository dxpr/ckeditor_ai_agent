<?php

namespace Drupal\ckeditor_ai_agent\Form;

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
   * @return array<string, mixed>
   *   The form elements.
   */
  protected function getCommonFormElements($is_plugin = FALSE, $config = NULL): array {
    $elements = [];

    // Initialize config based on context.
    if (!$is_plugin) {
      $config = \Drupal::config('ckeditor_ai_agent.settings');
    }

    // Helper function to get config value based on context.
    $getConfigValue = function ($key, $default = NULL) use ($is_plugin, $config) {
      if ($is_plugin) {
        $value = $config['aiAgent'] ?? [];
        return $value[$key] ?? $default;
      }
      return $config->get($key) ?? $default;
    };

    // Helper function to get select options with optional global settings.
    $getSelectOptions = function ($options) use ($is_plugin) {
      return $is_plugin
            ? ['' => $this->t('- Use global settings -')] + $options
            : $options;
    };

    // Helper function for formatting field names as titles.
    $formatMachineNameAsTitle = fn($title) => str_replace('_', ' ', ucfirst($title));

    // Basic Settings.
    $elements['basic_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Connection & Model Settings'),
      '#open' => TRUE,
    ];

    $elements['basic_settings']['apiKey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAI API Key'),
      '#description' => $is_plugin
        ? $this->t('Enter your OpenAI API key or leave empty to use the <a href="@settingsUrl">global settings</a>.', [
          '@settingsUrl' => \Drupal::service('url_generator')->generateFromRoute('ckeditor_ai_agent.settings'),
        ])
        : $this->t('Enter your OpenAI API key. Required for all AI functionality.'),
      '#required' => !$is_plugin,
      '#size' => 100,
      '#maxlength' => 255,
      '#default_value' => $getConfigValue('apiKey'),
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
      '#description' => $this->t('@description', [
        '@description' => 'Select AI model' . ($is_plugin ? ' or use global settings.' : '.'),
      ]),
      '#default_value' => $getConfigValue('model'),
    ];

    $elements['basic_settings']['endpointUrl'] = [
      '#type' => 'url',
      '#title' => $this->t('API Endpoint URL'),
      '#description' => $this->t('OpenAI API endpoint URL. Only change if using a custom endpoint or proxy.'),
      '#default_value' => $getConfigValue('endpointUrl'),
    ];

    $elements['basic_settings']['contentScope'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Scope'),
      '#description' => $this->t('CSS selector that extends context gathering to include content from other CKEditor 5 instances found within the first matching ancestor element.'),
      '#default_value' => $getConfigValue('contentScope'),
      '#placeholder' => '.node-form',
    ];

    // Add prompt settings.
    $this->addPromptSettings($elements, $getConfigValue);

    // Advanced Settings.
    $elements['advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Response Configuration'),
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

    // Token Settings.
    $elements['advanced_settings']['tokens'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Token Limits'),
    ];

    $token_fields = ['maxOutputTokens', 'maxInputTokens'];
    foreach ($token_fields as $field) {
      $elements['advanced_settings']['tokens'][$field] = [
        '#type' => 'number',
        '#title' => $this->t('@title', ['@title' => $formatMachineNameAsTitle($field)]),
        '#description' => $this->t("Maximum number of tokens for @type. If not set, uses model's maximum limit",
          ['@type' => str_contains($field, 'output') ? 'AI response' : 'combined prompt and context']),
        '#min' => 1,
        '#default_value' => $getConfigValue("tokens.$field"),
      ];
    }

    // Context Settings.
    $elements['advanced_settings']['context'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Context Settings'),
    ];

    $context_fields = [
      'contextSize' => [
        'title' => $this->t('Content Window Size'),
        'description' => $this->t('How many tokens to use for surrounding content. Must be less than Total Token Limit. Recommended: 75% of Total Token Limit to leave room for AI instructions.'),
        'min' => 1,
      ],
      'editorContextRatio' => [
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

    // Performance Settings.
    $elements['performance_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Request & Performance Settings'),
      '#open' => FALSE,
    ];

    $performance_fields = [
      'timeOutDuration' => [
        'title' => $this->t('Request Timeout'),
        'description' => $this->t('Maximum wait time for AI response. Default: 45000ms (45s)'),
        'min' => 1000,
        'field_suffix' => 'ms',
      ],
      'retryAttempts' => [
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
        '#default_value' => $getConfigValue($field),
      ];
    }

    // Behavior Settings.
    $elements['behavior_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Debug & Error Settings'),
      '#open' => FALSE,
    ];

    $boolean_options = ['0' => $this->t('Disabled'), '1' => $this->t('Enabled')];
    $behavior_fields = [
      'debugMode' => [
        'title' => $this->t('Debug Mode'),
        'description' => $this->t('Enable detailed logging for troubleshooting purposes.'),
        'type' => 'select',
        'options' => $boolean_options,
      ],
      'showErrorDuration' => [
        'title' => $this->t('Error Message Duration'),
        'description' => $this->t('How long to display error messages. Default: 5000ms (5s)'),
        'min' => 1000,
        'field_suffix' => 'ms',
      ],
    ];

    foreach ($behavior_fields as $field => $settings) {
      $elements['behavior_settings'][$field] = [
        '#type' => isset($settings['type']) ? $settings['type'] : 'number',
        '#title' => $settings['title'],
        '#description' => $settings['description'],
        '#min' => $settings['min'] ?? NULL,
        '#field_suffix' => $settings['field_suffix'] ?? NULL,
        '#options' => $settings['options'] ?? NULL,
        '#default_value' => $getConfigValue($field),
      ];
    }

    // Moderation Settings.
    $elements['moderation_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Safety & Moderation'),
      '#open' => FALSE,
    ];

    $elements['moderation_settings']['moderationEnable'] = $is_plugin
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

    $elements['moderation_settings']['moderationKey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Moderation API Key'),
      '#description' => $this->t('Separate API key for content moderation service. Required if using a different service than the main AI.'),
      '#default_value' => $getConfigValue('moderation.key'),
      '#states' => [
        'visible' => [
          ':input[name="moderationEnable"]' => ['checked' => TRUE],
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

    $elements['moderation_settings']['moderationDisableFlags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Disabled Safety Filters'),
      '#options' => $moderation_flags,
      '#description' => $this->t('Select content types to exclude from moderation. Use with caution.'),
      '#default_value' => $getConfigValue('moderation.disableFlags', []),
      '#states' => [
        'visible' => [
          ':input[name="moderationEnable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $elements;
  }

  /**
   * Adds prompt settings to the form elements.
   *
   * @param array<string, mixed> $elements
   *   List of common form elements.
   * @param \Closure $getConfigValue
   *   Helper function to get config value based on context.
   */
  protected function addPromptSettings(array &$elements, \Closure $getConfigValue): void {
    $elements['promptSettings'] = [
      '#type' => 'details',
      '#title' => $this->t('Tone & Prompt Settings'),
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
        $elements['promptSettings']["override_$key"] = [
          '#type' => 'textarea',
          '#title' => $this->t('@label Override', ['@label' => $label]),
          '#default_value' => $getConfigValue("promptSettings.overrides.$key"),
          '#placeholder' => $default_rules[$key] ?? '',
          '#description' => $this->t('Override the default @label rules. Leave empty to use the default values shown above.', ['@label' => strtolower((string) $label)]),
          '#rows' => 6,
          '#ajax' => FALSE,
        ];

        $elements['promptSettings']["additions_$key"] = [
          '#type' => 'textarea',
          '#title' => $this->t('@label Additions', ['@label' => $label]),
          '#default_value' => $getConfigValue("promptSettings.additions.$key"),
          '#description' => $this->t('Add custom @label rules that will be appended to the defaults.', ['@label' => strtolower((string) $label)]),
          '#rows' => 4,
          '#ajax' => FALSE,
        ];
      }
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError(t('Error loading prompt settings: @error', ['@error' => $e->getMessage()]));
    }
  }

}
