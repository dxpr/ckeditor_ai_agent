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
   *
   * @return array
   *   The form elements.
   */
  protected function getCommonFormElements($is_plugin = FALSE) {
    $elements = [];
    
    // Basic Settings
    $elements['basic_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic Settings'),
      '#open' => TRUE,
    ];

    if ($is_plugin) {
      $elements['basic_settings']['api_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('OpenAI API Key'),
        '#description' => $this->t('Your OpenAI API key. Leave empty to use <a href="@settings_url">global settings</a>.', [
          '@settings_url' => \Drupal::service('url_generator')->generateFromRoute('ckeditor_ai_agent.settings'),
        ]),
        '#required' => FALSE,
        '#size' => 100,
        '#maxlength' => 255,
      ];

      $elements['basic_settings']['model'] = [
        '#type' => 'select',
        '#title' => $this->t('AI Model'),
        '#options' => [
          '' => $this->t('- Use global settings -'),
          'gpt-4o' => $this->t('GPT-4o (Recommended)'),
          'gpt-4o-mini' => $this->t('GPT-4o Mini'),
          'gpt-3.5-turbo' => $this->t('GPT-3.5 Turbo'),
        ],
        '#description' => $this->t('Select AI model or use global settings.'),
      ];

      $elements['basic_settings']['temperature'] = [
        '#type' => 'number',
        '#title' => $this->t('Temperature'),
        '#min' => 0,
        '#max' => 2,
        '#step' => 0.1,
        '#description' => $this->t('Controls randomness (0-2). Leave empty to use global settings.'),
      ];

      $elements['basic_settings']['endpoint_url'] = [
        '#type' => 'url',
        '#title' => $this->t('API Endpoint URL'),
        '#description' => $this->t('OpenAI API endpoint URL. Only change if using a custom endpoint or proxy.'),
      ];

    } else {
      $elements['basic_settings']['api_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('OpenAI API Key'),
        '#description' => $this->t('Your OpenAI API key for authentication. Required for all AI operations.'),
        '#required' => TRUE,
        '#size' => 100,
        '#maxlength' => 255,
      ];

      $elements['basic_settings']['model'] = [
        '#type' => 'select',
        '#title' => $this->t('AI Model'),
        '#options' => [
          'gpt-4o' => $this->t('GPT-4o (Recommended)'),
          'gpt-4o-mini' => $this->t('GPT-4o Mini'),
          'gpt-3.5-turbo' => $this->t('GPT-3.5 Turbo'),
        ],
        '#description' => $this->t('Select the AI model to use.'),
      ];

      $elements['basic_settings']['endpoint_url'] = [
        '#type' => 'url',
        '#title' => $this->t('API Endpoint URL'),
        '#description' => $this->t('OpenAI API endpoint URL. Only change if using a custom endpoint or proxy.'),
      ];
    }

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
      '#description' => $this->t('Controls response creativity: 0.0-0.5 for focused, factual responses; 0.5-1.0 for balanced creativity; 1.0-2.0 for highly creative variations.'),
    ];

    $elements['advanced_settings']['tokens'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Token Limits'),
    ];

    $elements['advanced_settings']['tokens']['max_output_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Output Tokens'),
      '#description' => $this->t('Maximum number of tokens the AI can generate in its response. If not set, uses model\'s maximum output limit'),
      '#min' => 1,
    ];

    $elements['advanced_settings']['tokens']['max_input_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Input Tokens'),
      '#description' => $this->t('Maximum number of tokens allowed in the combined prompt and context. If not set, uses model\'s maximum context window limit'),
      '#min' => 1,
    ];

    $elements['advanced_settings']['context'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Context Settings'),
    ];

    $elements['advanced_settings']['context']['context_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Context Size'),
      '#description' => $this->t('Maximum context window size in tokens. If not set, defaults to 75% of model\'s maximum input token limit'),
      '#min' => 1,
    ];

    $elements['advanced_settings']['context']['editor_context_ratio'] = [
      '#type' => 'number',
      '#title' => $this->t('Editor Context Ratio'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
      '#field_suffix' => $this->t('(0.0 - 1.0)'),
      '#description' => $this->t('Portion of context for editor content. Default: 0.3 (30%)'),
    ];

    // Performance Settings
    $elements['performance_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Performance Settings'),
      '#open' => FALSE,
    ];

    $elements['performance_settings']['timeout_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Request Timeout'),
      '#min' => 1000,
      '#field_suffix' => 'ms',
      '#description' => $this->t('Maximum wait time for AI response. Default: 45000ms (45s)'),
    ];

    $elements['performance_settings']['retry_attempts'] = [
      '#type' => 'number',
      '#title' => $this->t('Retry Attempts'),
      '#min' => 0,
      '#description' => $this->t('Number of retry attempts for failed requests. Default: 1'),
    ];

    // Behavior Settings
    $elements['behavior_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Behavior Settings'),
      '#open' => FALSE,
    ];

    $elements['behavior_settings']['debug_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Debug Mode'),
      '#options' => $is_plugin 
        ? ['' => $this->t('- Use global settings -'), '0' => $this->t('Disabled'), '1' => $this->t('Enabled')]
        : ['0' => $this->t('Disabled'), '1' => $this->t('Enabled')],
      '#description' => $this->t('Enable detailed logging for troubleshooting.'),
    ];

    $elements['behavior_settings']['stream_content'] = [
      '#type' => 'select',
      '#title' => $this->t('Stream Responses'),
      '#options' => $is_plugin 
        ? ['' => $this->t('- Use global settings -'), '0' => $this->t('Disabled'), '1' => $this->t('Enabled')]
        : ['0' => $this->t('Disabled'), '1' => $this->t('Enabled')],
      '#description' => $this->t('Show AI responses as they are generated. Provides immediate feedback but may feel less polished.'),
    ];

    $elements['behavior_settings']['show_error_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Error Message Duration'),
      '#min' => 1000,
      '#field_suffix' => 'ms',
      '#description' => $this->t('How long to display error messages. Default: 5000ms (5s)'),
    ];

    // Moderation Settings
    $elements['moderation_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Moderation'),
      '#open' => FALSE,
    ];

    if ($is_plugin) {
      $elements['moderation_settings']['moderation_enable'] = [
        '#type' => 'select',
        '#title' => $this->t('Content Moderation'),
        '#options' => [
          '' => $this->t('- Use global settings -'),
          '0' => $this->t('Disabled'),
          '1' => $this->t('Enabled'),
        ],
        '#description' => $this->t('Enable content safety filtering.'),
      ];
    }
    else {
      $elements['moderation_settings']['moderation_enable'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Content Moderation'),
        '#description' => $this->t('Filter inappropriate or unsafe content. Recommended for public-facing implementations.'),
      ];
    }

    $elements['moderation_settings']['moderation_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Moderation API Key'),
      '#description' => $this->t('Separate API key for content moderation service. Required if using a different service than the main AI.'),
      '#states' => [
        'visible' => [
          ':input[name="moderation_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $elements['moderation_settings']['moderation_disable_flags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Disabled Safety Filters'),
      '#options' => [
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
      ],
      '#description' => $this->t('Select content types to exclude from moderation. Use with caution.'),
      '#states' => [
        'visible' => [
          ':input[name="moderation_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $elements;
  }

}