<?php

namespace Drupal\ckeditor_ai_agent\Form;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides common form elements for AI Agent configuration.
 */
trait AiAgentFormTrait {
  use StringTranslationTrait;

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
        // For plugin config, values are nested under aiAgent
        $value = $config['aiAgent'] ?? [];
        // Special case for ollamaModel to match the structure in settings
        if ($key === 'ollamaModel') {
          return $value['ollamaModel'] ?? $default;
        }
        $keys = explode('.', $key);
        foreach ($keys as $k) {
          if (!isset($value[$k])) {
            return $default;
          }
          $value = $value[$k];
        }
        return $value;
      }
      // For settings form, use direct config get
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
      '#ajax' => FALSE,
    ];

    // Get available keys
    $key_options = [];
    $key_storage = \Drupal::entityTypeManager()->getStorage('key');
    $keys = $key_storage->loadMultiple();
    foreach ($keys as $key) {
      $key_options[$key->id()] = $key->label();
    }

    $elements['basic_settings']['key_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('API Key'),
      '#description' => $is_plugin
        ? $this->t('Select the key that contains your API credentials or use the <a href="@url">global settings</a>. <a href="@keys_url">Manage keys</a>', [
          '@url' => \Drupal::service('url_generator')->generateFromRoute('ckeditor_ai_agent.settings'),
          '@keys_url' => '/admin/config/system/keys',
        ])
        : $this->t('Select the key that contains your API credentials. <a href="@url">Manage keys</a>', [
          '@url' => '/admin/config/system/keys',
        ]),
      '#options' => $is_plugin ? $getSelectOptions($key_options) : $key_options,
      '#default_value' => $getConfigValue('key_provider'),
      '#required' => !$is_plugin,
      '#ajax' => FALSE,
    ];

    // Load supported models from JSON file
    $supported_models = [];
    $json_path = \Drupal::service('extension.path.resolver')->getPath('module', 'ckeditor_ai_agent') . '/js/ckeditor5_plugins/aiagent/src/SUPPORTED_MODELS.json';
    if (file_exists($json_path)) {
      $supported_models = json_decode(file_get_contents($json_path), TRUE) ?: [];
    }

    // Create model options grouped by engine
    $model_options = [];
    foreach ($supported_models as $engine => $models) {
      $model_options[$engine] = [];
      foreach ($models as $model) {
        $model_options[$engine][$engine . ':' . $model] = $model;
      }
    }

    // Add ollama as a special case
    $model_options['ollama'] = ['ollama:custom' => $this->t('Custom Model')];

    // Add DXAI as a new engine
    $model_options['dxai'] = [
      'dxai:kavya-m1' => 'Kavya M1',
      'dxai:kavya-m1-eu' => 'Kavya M1 European Union'
    ];

    ksort($model_options);

    $model_field_name = $is_plugin ? 'aiAgent[model]' : 'model';
    $elements['basic_settings']['model'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Engine/Model'),
      '#options' => $getSelectOptions($model_options),
      '#description' => $this->t('@description', [
        '@description' => 'Select AI engine and model' . ($is_plugin ? ' or use global settings.' : '.'),
      ]),
      '#default_value' => $getConfigValue('model'),
      '#ajax' => FALSE,
    ];

    $elements['basic_settings']['ollamaModel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ollama Model Name'),
      '#description' => $this->t('Enter the model name when using Ollama (e.g., llama2, mistral, codellama).'),
      '#default_value' => $getConfigValue('ollamaModel'),
      '#ajax' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="editor[settings][plugins][ckeditor_ai_agent_ai_agent][aiAgent][model]"]' => ['value' => 'ollama:custom'],
        ],
      ],
    ];

    // For plugin context, ensure ollamaModel is saved under aiAgent
    if ($is_plugin) {
      $elements['basic_settings']['ollamaModel']['#description'] = $this->t('Not available in plugin context due to ckeditor5 module limitations.');
      $elements['basic_settings']['ollamaModel']['#disabled'] = TRUE;
    }

    $elements['basic_settings']['endpointUrl'] = [
      '#type' => 'url',
      '#title' => $this->t('API Endpoint URL'),
      '#description' => $this->t('API endpoint URL. Only change if using a custom endpoint or proxy.'),
      '#default_value' => $getConfigValue('endpointUrl'),
      '#ajax' => FALSE,
    ];

    $elements['basic_settings']['contentScope'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Scope'),
      '#description' => $this->t('CSS selector that extends context gathering to include content from other CKEditor 5 instances found within the first matching ancestor element.'),
      '#default_value' => $getConfigValue('contentScope'),
      '#placeholder' => '.node-form',
      '#ajax' => FALSE,
    ];

    // Add prompt settings.
    $this->addPromptSettings($elements, $getConfigValue);

    // Advanced Settings.
    $elements['advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Response Configuration'),
      '#open' => FALSE,
      '#ajax' => FALSE,
    ];

    $elements['advanced_settings']['temperature'] = [
      '#type' => 'number',
      '#title' => $this->t('Response Creativity'),
      '#field_suffix' => $this->t('(0.0 - 2.0)'),
      '#min' => 0,
      '#max' => 2,
      '#step' => 0.1,
      '#description' => $this->t('Controls the creativity of AI responses. Low values (0.0-0.5) produce consistent, deterministic responses ideal for factual content. Medium values (0.6-1.0) offer balanced creativity. High values (1.1-2.0) generate more diverse and unexpected responses.'),
      '#default_value' => $getConfigValue('temperature'),
      '#ajax' => FALSE,
    ];

    // Token Settings.
    $elements['advanced_settings']['tokens'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Token Limits'),
      '#ajax' => FALSE,
    ];

    $token_fields = ['maxOutputTokens', 'maxInputTokens'];
    foreach ($token_fields as $field) {
      $elements['advanced_settings']['tokens'][$field] = [
        '#type' => 'number',
        '#title' => $this->t('@title', ['@title' => $formatMachineNameAsTitle($field)]),
        '#description' => $this->t("Maximum number of tokens for @type. If not set, uses model's maximum limit",
          ['@type' => str_contains($field, 'output') ? 'AI response' : 'combined prompt and context']),
        '#min' => 1,
        '#default_value' => $getConfigValue("$field"),
        '#ajax' => FALSE,
      ];
    }

    // Context Settings.
    $elements['advanced_settings']['context'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Context Settings'),
      '#ajax' => FALSE,
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
        '#default_value' => $getConfigValue("$field"),
        '#ajax' => FALSE,
      ];
    }

    // Performance Settings.
    $elements['performance_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Request & Performance Settings'),
      '#open' => FALSE,
      '#ajax' => FALSE,
    ];

    $performance_fields = [
      'timeOutDuration' => [
        'title' => $this->t('Request Timeout'),
        'description' => $this->t('Maximum wait time for AI response. Default: 120000ms (45s)'),
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
        '#ajax' => FALSE,
      ];
    }

    // Behavior Settings.
    $elements['behavior_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Debug & Error Settings'),
      '#open' => FALSE,
      '#ajax' => FALSE,
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
        '#ajax' => FALSE,
      ];
    }

    // Moderation Settings.
    $elements['moderation_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Safety & Moderation'),
      '#open' => FALSE,
      '#ajax' => FALSE,
    ];

    $elements['moderation_settings']['moderationEnable'] = $is_plugin
        ? [
          '#type' => 'select',
          '#title' => $this->t('Content Moderation'),
          '#options' => $getSelectOptions($boolean_options),
          '#description' => $this->t('Enable content safety filtering.'),
          '#default_value' => $getConfigValue('moderationEnable'),
          '#ajax' => FALSE,
        ]
        : [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable Content Moderation'),
          '#description' => $this->t('Filter inappropriate or unsafe content. Recommended for public-facing implementations.'),
          '#default_value' => $getConfigValue('moderationEnable'),
          '#ajax' => FALSE,
        ];

    $elements['moderation_settings']['moderationKey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Moderation API Key'),
      '#description' => $this->t('Separate API key for content moderation service. Required if using a different service than the main AI.'),
      '#default_value' => $getConfigValue('moderationKey'),
      '#states' => [
        'visible' => [
          ':input[name="moderationEnable"]' => ['checked' => TRUE],
        ],
      ],
      '#ajax' => FALSE,
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
      '#ajax' => FALSE,
    ];

    // Add the tone of voice taxonomy integration
    $this->addToneOfVoiceSettings($elements, $getConfigValue);

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
        // Handle tone fields differently when using taxonomy integration
        $is_tone_with_vocab = ($key === 'tone' && !empty($getConfigValue('toneOfVoiceVocabulary')));

        $elements['promptSettings']["override_$key"] = [
          '#type' => 'textarea',
          '#title' => $this->t('@label Override', ['@label' => $label]),
          '#default_value' => $getConfigValue("promptSettings.overrides.$key"),
          '#placeholder' => $default_rules[$key] ?? '',
          '#description' => $is_tone_with_vocab 
            ? $this->t('This field is disabled because you are using the Tone of Voice vocabulary. The tone will be set automatically based on the selected vocabulary terms. To modify tones, please edit the terms in the vocabulary above.')
            : $this->t('Override the default @label rules. Leave empty to use the default values shown above.', ['@label' => strtolower((string) $label)]),
          '#rows' => 6,
          '#ajax' => FALSE,
          '#disabled' => $is_tone_with_vocab,
          '#attributes' => $is_tone_with_vocab ? ['class' => ['tone-vocab-disabled']] : [],
        ];

        $elements['promptSettings']["additions_$key"] = [
          '#type' => 'textarea',
          '#title' => $this->t('@label Additions', ['@label' => $label]),
          '#default_value' => $getConfigValue("promptSettings.additions.$key"),
          '#description' => $is_tone_with_vocab
            ? $this->t('This field is disabled because you are using the Tone of Voice vocabulary. The tone will be set automatically based on the selected vocabulary terms. To modify tones, please edit the terms in the vocabulary above.')
            : $this->t('Add custom @label rules that will be appended to the defaults.', ['@label' => strtolower((string) $label)]),
          '#rows' => 4,
          '#ajax' => FALSE,
          '#disabled' => $is_tone_with_vocab,
          '#attributes' => $is_tone_with_vocab ? ['class' => ['tone-vocab-disabled']] : [],
        ];

        // Add a warning message above the tone fields when using vocabulary
        if ($is_tone_with_vocab) {
          $elements['promptSettings']["tone_vocab_warning"] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('<strong>Note:</strong> The tone settings below are disabled because you are using the Tone of Voice vocabulary above. The tone will be set automatically based on the selected vocabulary terms. To modify tones, please manage the terms in the vocabulary.'),
            '#weight' => -1,
            '#attributes' => [
              'class' => ['messages', 'messages--warning', 'tone-vocab-warning'],
            ],
          ];
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError(t('Error loading prompt settings: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Adds tone of voice taxonomy settings to the form.
   *
   * @param array<string, mixed> $elements
   *   List of common form elements.
   * @param \Closure $getConfigValue
   *   Helper function to get config value based on context.
   */
  protected function addToneOfVoiceSettings(array &$elements, \Closure $getConfigValue): void {
    // Create a fieldset for the tone of voice settings
    $elements['promptSettings']['tone_of_voice'] = [
      '#type' => 'details',
      '#title' => $this->t('Tone of Voice'),
      '#open' => TRUE,
      '#description' => $this->t('Configure the tones of voice available to content creators when interacting with the AI Agent.'),
    ];

    // Get all vocabularies for the dropdown
    $vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
    $vocab_options = [];
    foreach ($vocabularies as $vocabulary) {
      $vocab_options[$vocabulary->id()] = $vocabulary->label();
    }

    // Add a toggle to enable/disable the taxonomy integration
    $elements['promptSettings']['tone_of_voice']['enable_taxonomy_tones'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use taxonomy terms for tones of voice'),
      '#description' => $this->t('When enabled, content creators can select from predefined tones of voice from a taxonomy vocabulary. At least 2 terms with descriptions are required for the dropdown to appear in the editor.'),
      '#default_value' => !empty($getConfigValue('toneOfVoiceVocabulary')),
    ];

    // Add the vocabulary selector
    $elements['promptSettings']['tone_of_voice']['tone_of_voice_vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Tone of Voice Vocabulary'),
      '#description' => $this->t('Select the taxonomy vocabulary that contains your tones of voice. The term name will be shown to users in the dropdown, and the term description will be used as the tone command for the AI.'),
      '#options' => $vocab_options,
      '#default_value' => $getConfigValue('toneOfVoiceVocabulary'),
      '#states' => [
        'visible' => [
          ':input[name="promptSettings[tone_of_voice][enable_taxonomy_tones]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="promptSettings[tone_of_voice][enable_taxonomy_tones]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Add a container to preview available tones
    $elements['promptSettings']['tone_of_voice']['preview_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="promptSettings[tone_of_voice][enable_taxonomy_tones]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Add a preview of available tones if a vocabulary is selected
    $selected_vocabulary = $getConfigValue('toneOfVoiceVocabulary');
    if (!empty($selected_vocabulary)) {
      // Load the terms from the selected vocabulary, sorted by weight
      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $query = $term_storage->getQuery()
        ->condition('vid', $selected_vocabulary)
        ->sort('weight')
        ->accessCheck(FALSE);
      $tids = $query->execute();
      
      if (!empty($tids)) {
        $terms = $term_storage->loadMultiple($tids);
        
        $elements['promptSettings']['tone_of_voice']['preview_container']['tone_terms_preview'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Available Tones'),
          '#description' => $this->t('The following tones will be available to content creators. Terms are ordered by weight, with the lightest weight appearing first in the dropdown.'),
        ];

        $header = [
          $this->t('Tone Name'),
          $this->t('Description/Command'),
          $this->t('Weight'),
          $this->t('Status'),
        ];

        $rows = [];
        $valid_terms_count = 0;

        foreach ($terms as $term) {
          $description = $term->getDescription();
          $status = !empty($description) 
            ? $this->t('Valid') 
            : $this->t('Missing description - will not appear in dropdown');
          
          if (!empty($description)) {
            $valid_terms_count++;
          }
          
          $rows[] = [
            $term->label(),
            $description ?: $this->t('- No description -'),
            $term->get('weight')->value,
            $status,
          ];
        }

        $elements['promptSettings']['tone_of_voice']['preview_container']['tone_terms_table'] = [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#empty' => $this->t('No terms found in this vocabulary. Please <a href="@link">add some terms</a> to the vocabulary.', [
            '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/add',
          ]),
          '#attributes' => [
            'class' => ['tone-terms-table'],
          ],
        ];

        // Add warning if we don't have enough valid terms
        if ($valid_terms_count < 2) {
          $elements['promptSettings']['tone_of_voice']['preview_container']['warning'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Warning: At least 2 terms with descriptions are required for the tone dropdown to appear in the editor. Currently you have @count valid terms.', [
              '@count' => $valid_terms_count,
            ]),
            '#attributes' => [
              'class' => ['messages', 'messages--warning'],
            ],
          ];
        }

        // Add help text for term descriptions
        $elements['promptSettings']['tone_of_voice']['preview_container']['help_text'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('
            <h4>How to set up tones of voice:</h4>
            <ol>
              <li>Each taxonomy term represents one tone of voice option in the dropdown.</li>
              <li>The term <strong>name</strong> will be displayed in the dropdown menu.</li>
              <li>The term <strong>description</strong> will be used as the command sent to the AI. Make it descriptive and clear.</li>
              <li>The term <strong>weight</strong> determines the order in the dropdown (lighter weights appear first).</li>
              <li>The first term by weight will be used as the default tone.</li>
              <li>Only terms with descriptions will be included in the dropdown.</li>
            </ol>
            <p><strong>Example description:</strong> "Write in a warm, clear, and simple way for patients to understand."</p>
          '),
          '#attributes' => [
            'class' => ['tone-of-voice-tip'],
          ],
        ];
        
        // Add a link to manage the terms
        $elements['promptSettings']['tone_of_voice']['preview_container']['manage_link'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('<a href="@link" class="button">Manage Tone of Voice Terms</a>', [
            '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/overview',
          ]),
          '#attributes' => [
            'class' => ['tone-manage-link'],
          ],
        ];
      }
      else {
        $elements['promptSettings']['tone_of_voice']['preview_container']['no_terms'] = [
          '#type' => 'markup',
          '#markup' => $this->t('No terms found in this vocabulary. Please <a href="@link">add some terms</a> to the vocabulary.', [
            '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/add',
          ]),
        ];
      }
    }

    // Add information about creating a vocabulary if none exists
    if (empty($vocab_options)) {
      $elements['promptSettings']['tone_of_voice']['no_vocabularies'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No taxonomy vocabularies found. Please <a href="@link">create a vocabulary</a> for tone of voice terms first.', [
          '@link' => '/admin/structure/taxonomy/add',
        ]),
      ];
    }

    // Add some styling for the tone of voice section
    $elements['#attached']['library'][] = 'ckeditor_ai_agent/tone_of_voice';
  }

}