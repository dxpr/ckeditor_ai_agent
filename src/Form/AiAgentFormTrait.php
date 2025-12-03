<?php

namespace Drupal\ckeditor_ai_agent\Form;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides common form elements for AI Agent configuration.
 */
trait AiAgentFormTrait {
  use StringTranslationTrait;

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  abstract protected function getEntityTypeManager(): EntityTypeManagerInterface;

  /**
   * Gets the extension path resolver.
   *
   * @return \Drupal\Core\Extension\ExtensionPathResolver
   *   The extension path resolver.
   */
  abstract protected function getExtensionPathResolver(): ExtensionPathResolver;

  /**
   * Gets the URL generator.
   *
   * @return \Drupal\Core\Routing\UrlGeneratorInterface
   *   The URL generator.
   */
  abstract protected function getUrlGenerator(): UrlGeneratorInterface;

  /**
   * Gets the config factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  abstract protected function getConfigFactory(): ConfigFactoryInterface;

  /**
   * Gets the messenger service.
   *
   * @return \Drupal\Core\Messenger\MessengerInterface
   *   The messenger.
   */
  abstract protected function getMessenger(): MessengerInterface;

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
      $config = $this->getConfigFactory()->get('ckeditor_ai_agent.settings');
    }

    // Add styling for the AI Agent settings.
    $elements['#attached']['library'][] = 'ckeditor_ai_agent/ai_agent_settings';

    // Helper function to get config value based on context.
    $getConfigValue = function ($key, $default = NULL) use ($is_plugin, $config) {
      if ($is_plugin) {
        // For plugin config, values are nested under aiAgent.
        $value = $config['aiAgent'] ?? [];
        // Special case for ollamaModel to match the structure in settings.
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
      // For settings form, use direct config get.
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

    // Get available keys.
    $key_options = [];
    $key_storage = $this->getEntityTypeManager()->getStorage('key');
    $keys = $key_storage->loadMultiple();
    foreach ($keys as $key) {
      $key_options[$key->id()] = $key->label();
    }

    $elements['basic_settings']['key_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('API Key'),
      '#description' => $is_plugin
        ? $this->t('Select the key that contains your API credentials or use the <a href="@url">global settings</a>. <a href="@keys_url">Manage keys</a>', [
          '@url' => $this->getUrlGenerator()->generateFromRoute('ckeditor_ai_agent.settings'),
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

    // Load supported models from JSON file.
    $supported_models = [];
    $json_path = $this->getExtensionPathResolver()->getPath('module', 'ckeditor_ai_agent') . '/js/ckeditor5_plugins/aiagent/src/SUPPORTED_MODELS.json';
    if (file_exists($json_path)) {
      $supported_models = json_decode(file_get_contents($json_path), TRUE) ?: [];
    }

    // Create model options grouped by engine.
    $model_options = [];
    foreach ($supported_models as $engine => $models) {
      $model_options[$engine] = [];
      foreach ($models as $model) {
        $model_options[$engine][$engine . ':' . $model] = $model;
      }
    }

    // Add ollama as a special case.
    $model_options['ollama'] = ['ollama:custom' => $this->t('Custom Model')];

    // Add DXAI as a new engine.
    $model_options['dxai'] = [
      'dxai:kavya-m1' => 'Kavya M1',
      'dxai:kavya-m1-eu' => 'Kavya M1 European Union',
    ];

    ksort($model_options);

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

    // For plugin context, ensure ollamaModel is saved under aiAgent.
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

    // Add prompt settings.
    $this->addPromptSettings($elements, $getConfigValue);

    // Add the tone of voice taxonomy integration as a separate fieldset.
    $elements['tone_of_voice'] = [
      '#type' => 'details',
      '#title' => $this->t('Tone of Voice'),
      '#open' => FALSE,
      '#description' => $this->t('Configure the tones of voice available to content creators when interacting with the AI Agent.'),
    // Place right after basic settings.
      '#weight' => 5,
    ];

    // Move tone of voice settings to the new fieldset.
    $this->addToneOfVoiceSettings($elements, $getConfigValue);

    // Advanced Settings.
    $elements['advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Response Configuration'),
      '#open' => FALSE,
      '#ajax' => FALSE,
    // Place after tone of voice settings.
      '#weight' => 10,
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

    $elements['advanced_settings']['context']['contentScope'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Scope'),
      '#description' => $this->t('CSS selector that extends context gathering to include content from other CKEditor 5 instances found within the first matching ancestor element.'),
      '#default_value' => $getConfigValue('contentScope'),
      '#placeholder' => '.node-form',
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

    // Create the advanced prompt settings fieldset.
    $elements['promptSettings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Prompt Settings'),
      '#open' => FALSE,
      '#ajax' => FALSE,
    // After AI Response Configuration.
      '#weight' => 15,
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
      $module_path = $this->getExtensionPathResolver()->getPath('module', 'ckeditor_ai_agent');
      $default_rules_path = $module_path . '/js/ckeditor5_plugins/aiagent/src/config/default-rules.json';
      $default_rules = file_exists($default_rules_path)
            ? json_decode(file_get_contents($default_rules_path), TRUE) ?: []
            : [];

      foreach ($prompt_components as $key => $label) {
        // Handle tone fields differently when using taxonomy integration.
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

        // Add a warning message above the tone fields when using vocabulary.
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
      $this->getMessenger()->addError(t('Error loading prompt settings: @error', ['@error' => $e->getMessage()]));
    }

    // Performance Settings.
    $elements['performance_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Performance Settings'),
      '#open' => FALSE,
      '#ajax' => FALSE,
    // After Advanced Prompt Settings.
      '#weight' => 20,
    ];

    $performance_fields = [
      'timeOutDuration' => [
        'title' => $this->t('Request Timeout'),
        'description' => $this->t('Maximum wait time for AI response. Default: 120000ms (120s)'),
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
    // After Performance Settings.
      '#weight' => 25,
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
        '#type' => $settings['type'] ?? 'number',
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
      '#title' => $this->t('Moderation'),
      '#open' => FALSE,
      '#ajax' => FALSE,
    // After Debug & Error Settings.
      '#weight' => 30,
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

    // AI Output Security Settings.
    $elements['security_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Output Security'),
      '#open' => FALSE,
      '#ajax' => FALSE,
      '#weight' => 31,
      '#description' => $this->t('Mitigate prompt injection attacks (CVE-2025-32711) that attempt to exfiltrate data via malicious URLs in AI-generated content.'),
    ];

    $elements['security_settings']['allowedDomains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed Domains'),
      '#description' => $this->t('List of domains allowed for external URLs (images and links) in AI output (one per line). Supports wildcards (e.g., *.example.com). Default: promptahuman.com. Use * to allow all domains (not recommended).'),
      '#default_value' => $this->formatDomainsForTextarea($getConfigValue('aiOutputSecurity.allowedDomains') ?? ['promptahuman.com']),
      '#rows' => 4,
      '#ajax' => FALSE,
    ];

    // Add the commands section to the form.
    $elements['commands'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Edit Commands'),
      '#open' => FALSE,
      '#description' => $this->t('Configure the commands available to content creators when interacting with the AI Agent.'),
    // Place right after tone of voice settings.
      '#weight' => 6,
    ];

    // Get all vocabularies for the dropdown.
    $vocabularies = $this->getEntityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
    $vocab_options = [];
    foreach ($vocabularies as $vocabulary) {
      $vocab_options[$vocabulary->id()] = $vocabulary->label();
    }

    // Add a toggle to enable/disable the taxonomy integration.
    $elements['commands']['enable_taxonomy_commands'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Organize commands in categories'),
      '#description' => $this->t('Use categories (taxonomy vocabularies) to manage your commands. Parent terms become categories, child terms become commands. <a href="@vocab_link">Manage categories</a>.', [
        '@vocab_link' => '/admin/structure/taxonomy',
      ]),
      '#default_value' => !empty($getConfigValue('commandsVocabulary')),
    ];

    // Add the vocabulary selector.
    $elements['commands']['commandsVocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Command Collection'),
      '#description' => $this->t('Select or create a vocabulary to manage your commands'),
      '#options' => $vocab_options,
      '#default_value' => $getConfigValue('commandsVocabulary'),
      '#states' => [
        'visible' => [
          ':input[name="commands[enable_taxonomy_commands]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="commands[enable_taxonomy_commands]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Add a container to preview available commands.
    $elements['commands']['preview_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="commands[enable_taxonomy_commands]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Add a preview of available commands if a vocabulary is selected.
    $selected_vocabulary = $getConfigValue('commandsVocabulary');
    if (!empty($selected_vocabulary)) {
      // Load the terms from the selected vocabulary, sorted by weight.
      $term_storage = $this->getEntityTypeManager()->getStorage('taxonomy_term');

      // Use loadTree() to get validated terms with correct hierarchy.
      $tree_terms = $term_storage->loadTree($selected_vocabulary);
      $categories = [];

      if (!empty($tree_terms)) {
        // Organize terms by hierarchy (parent categories and child commands)
        foreach ($tree_terms as $tree_term) {
          if ($tree_term->parents[0] == 0) {
            // This is a parent category.
            $categories[$tree_term->tid] = [
              'term' => $tree_term,
              'children' => [],
            ];
          }
          else {
            // This is a child command.
            $parent_id = $tree_term->parents[0];
            if (isset($categories[$parent_id])) {
              $categories[$parent_id]['children'][] = $tree_term;
            }
          }
        }

        if (!empty($categories)) {

          // Create a structured table showing the command hierarchy.
          $rows = [];

          foreach ($categories as $category_data) {
            // Add the category as a header-like row.
            $rows[] = [
              'data' => [
              [
                'data' => $this->t('@category (Category)', ['@category' => $category_data['term']->name]),
                'colspan' => 2,
                'class' => ['command-category-header'],
              ],
              ],
              'class' => ['command-category-row'],
            ];

            // Add child commands to this category.
            if (!empty($category_data['children'])) {
              foreach ($category_data['children'] as $command_tree_term) {
                // Load the full term to get description.
                $command_term = $term_storage->load($command_tree_term->tid);

                // Add each command to the table.
                $description = $command_term->getDescription();
                // Strip HTML and truncate for display.
                $description = strip_tags($description);
                $short_description = strlen($description) > 100
                ? substr($description, 0, 100) . '...'
                : ($description ?: $this->t('- No instruction defined -'));

                $rows[] = [
                  'data' => [
                  [
                    'data' => $command_term->label(),
                    'class' => ['command-name'],
                  ],
                  [
                    'data' => $short_description,
                    'class' => ['command-description'],
                  ],
                  ],
                  'class' => ['command-row'],
                ];
              }
            }
            else {
              $rows[] = [
                'data' => [
                [
                  'data' => $this->t('No commands found in this category'),
                  'colspan' => 2,
                  'class' => ['empty-category'],
                ],
                ],
              ];
            }
          }

          $header = [
            $this->t('Command'),
            $this->t('Instruction'),
          ];

          $elements['commands']['preview_container']['commands_table'] = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('No commands found. <a href="@link">Add commands</a>', [
              '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/add',
            ]),
            '#attributes' => [
              'class' => ['commands-table'],
            ],
          ];

          // Add a link to manage the terms.
          $elements['commands']['preview_container']['manage_link'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('<a href="@link" class="button">Manage Commands</a>', [
              '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/overview',
            ]),
            '#attributes' => [
              'class' => ['commands-manage-link'],
            ],
          ];
        }
      }
      else {
        $elements['commands']['preview_container']['no_terms'] = [
          '#type' => 'markup',
          '#markup' => $this->t('No commands found. <a href="@link">Add commands</a>', [
            '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/add',
          ]),
        ];
      }
    }

    // Add information about creating a vocabulary if none exists.
    if (empty($vocab_options)) {
      $elements['commands']['no_vocabularies'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No vocabularies found. <a href="@link">Create one</a> to manage your commands.', [
          '@link' => '/admin/structure/taxonomy/add',
        ]),
      ];
    }

    // Enforce nested collections of form elements.
    $elements['#tree'] = TRUE;

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
    // Add the tone of voice taxonomy integration.
    $this->addToneOfVoiceSettings($elements, $getConfigValue);

    // Add the commands taxonomy integration.
    $this->addCommandSettings($elements, $getConfigValue);

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
      $module_path = $this->getExtensionPathResolver()->getPath('module', 'ckeditor_ai_agent');
      $default_rules_path = $module_path . '/js/ckeditor5_plugins/aiagent/src/config/default-rules.json';
      $default_rules = file_exists($default_rules_path)
            ? json_decode(file_get_contents($default_rules_path), TRUE) ?: []
            : [];

      foreach ($prompt_components as $key => $label) {
        // Handle tone fields differently when using taxonomy integration.
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

        // Add a warning message above the tone fields when using vocabulary.
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
      $this->getMessenger()->addError(t('Error loading prompt settings: @error', ['@error' => $e->getMessage()]));
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
    // Get all vocabularies for the dropdown.
    $vocabularies = $this->getEntityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
    $vocab_options = [];
    foreach ($vocabularies as $vocabulary) {
      $vocab_options[$vocabulary->id()] = $vocabulary->label();
    }

    // Track if we have any vocabularies.
    $has_vocabularies = !empty($vocab_options);

    // If no vocabularies exist at all, add a placeholder option.
    if (!$has_vocabularies) {
      $vocab_options[''] = $this->t('- No vocabularies available -');
    }

    // Add a toggle to enable/disable the taxonomy integration.
    $elements['tone_of_voice']['enable_taxonomy_tones'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Organize tones in categories'),
      '#description' => $this->t('Use categories (taxonomy vocabularies) to manage your tones of voice. The first tone will be used as default. To enable tone selection while editing, add the Tone of Voice button to your text format toolbar. <a href="@vocab_link">Manage categories</a>.', [
        '@vocab_link' => '/admin/structure/taxonomy',
      ]),
      '#default_value' => !empty($getConfigValue('toneOfVoiceVocabulary')),
    ];

    // Get saved tone vocabulary and check if it exists.
    $saved_tone_vocab = $getConfigValue('toneOfVoiceVocabulary');
    $tone_default_value = '';

    if ($saved_tone_vocab && isset($vocab_options[$saved_tone_vocab])) {
      $tone_default_value = $saved_tone_vocab;
    }

    // Add the vocabulary selector.
    $elements['tone_of_voice']['toneOfVoiceVocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Tone Collection'),
      '#description' => $this->t('Select a vocabulary to manage your tones'),
      '#options' => $vocab_options,
      '#default_value' => $tone_default_value,
      '#states' => [
        'visible' => [
          ':input[name="tone_of_voice[enable_taxonomy_tones]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="tone_of_voice[enable_taxonomy_tones]"]' => ['checked' => TRUE],
        ],
      ],
      '#empty_option' => $this->t('- Select a vocabulary -'),
      '#empty_value' => '',
    ];

    // Add a container to preview available tones.
    $elements['tone_of_voice']['preview_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="tone_of_voice[enable_taxonomy_tones]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Add a preview of available tones if a vocabulary is selected.
    $selected_vocabulary = $getConfigValue('toneOfVoiceVocabulary');
    if (!empty($selected_vocabulary)) {
      // Load the terms from the selected vocabulary, sorted by weight.
      $term_storage = $this->getEntityTypeManager()->getStorage('taxonomy_term');
      // Use loadTree() to get validated terms with correct hierarchy.
      $tree_terms = $term_storage->loadTree($selected_vocabulary);
      $terms = [];
      if (!empty($tree_terms)) {
        $tids = array_column($tree_terms, 'tid');
        $terms = $term_storage->loadMultiple($tids);

        $header = [
          $this->t('Label'),
          $this->t('Tone'),
        ];

        $rows = [];

        foreach ($terms as $term) {
          $description = $term->getDescription();
          // Strip HTML and truncate for display.
          $description = strip_tags($description);
          $short_description = strlen($description) > 100
            ? substr($description, 0, 100) . '...'
            : ($description ?: $this->t('- No tone defined -'));

          $rows[] = [
            'data' => [
              [
                'data' => $term->label(),
                'class' => ['tone-name'],
              ],
              [
                'data' => $short_description,
                'class' => ['tone-description'],
              ],
            ],
            'class' => ['tone-row'],
          ];
        }

        $elements['tone_of_voice']['preview_container']['tone_terms_table'] = [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#empty' => $this->t('No tones found. <a href="@link">Add tones</a>', [
            '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/add',
          ]),
          '#attributes' => [
            'class' => ['tone-terms-table'],
          ],
        ];

        // Add a link to manage the terms.
        $elements['tone_of_voice']['preview_container']['manage_link'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('<a href="@link" class="button">Manage Tones</a>', [
            '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/overview',
          ]),
          '#attributes' => [
            'class' => ['tone-manage-link'],
          ],
        ];
      }
      else {
        $elements['tone_of_voice']['preview_container']['no_terms'] = [
          '#type' => 'markup',
          '#markup' => $this->t('No tones found. <a href="@link">Add tones</a>', [
            '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/add',
          ]),
        ];
      }
    }

    // Add information about creating a vocabulary if none exists.
    if (!$has_vocabularies) {
      $elements['tone_of_voice']['no_vocabularies'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No vocabularies found. <a href="@link">Create one</a> to manage your tones.', [
          '@link' => '/admin/structure/taxonomy/add',
        ]),
      ];
    }

    // No need to add commands library separately as it's included in
    // ai_agent_settings.
  }

  /**
   * Adds command taxonomy settings to the form.
   *
   * @param array<string, mixed> $elements
   *   List of common form elements.
   * @param \Closure $getConfigValue
   *   Helper function to get config value based on context.
   */
  protected function addCommandSettings(array &$elements, \Closure $getConfigValue): void {
    // Get all vocabularies for the dropdown.
    $vocabularies = $this->getEntityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
    $vocab_options = [];
    foreach ($vocabularies as $vocabulary) {
      $vocab_options[$vocabulary->id()] = $vocabulary->label();
    }

    // Track if we have any vocabularies.
    $has_vocabularies = !empty($vocab_options);

    // If no vocabularies exist at all, add a placeholder option.
    if (!$has_vocabularies) {
      $vocab_options[''] = $this->t('- No vocabularies available -');
    }

    // Add a toggle to enable/disable the taxonomy integration.
    $elements['commands']['enable_taxonomy_commands'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Organize commands in categories'),
      '#description' => $this->t('Use categories (taxonomy vocabularies) to manage your commands. Parent terms become categories, child terms become commands. <a href="@vocab_link">Manage categories</a>.', [
        '@vocab_link' => '/admin/structure/taxonomy',
      ]),
      '#default_value' => !empty($getConfigValue('commandsVocabulary')),
    ];

    // Get saved command vocabulary and check if it exists.
    $saved_command_vocab = $getConfigValue('commandsVocabulary');
    $command_default_value = '';

    if ($saved_command_vocab && isset($vocab_options[$saved_command_vocab])) {
      $command_default_value = $saved_command_vocab;
    }

    // Add the vocabulary selector.
    $elements['commands']['commandsVocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Command Collection'),
      '#description' => $this->t('Select a vocabulary to manage your commands'),
      '#options' => $vocab_options,
      '#default_value' => $command_default_value,
      '#states' => [
        'visible' => [
          ':input[name="commands[enable_taxonomy_commands]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="commands[enable_taxonomy_commands]"]' => ['checked' => TRUE],
        ],
      ],
      '#empty_option' => $this->t('- Select a vocabulary -'),
      '#empty_value' => '',
    ];

    // Add a container to preview available commands.
    $elements['commands']['preview_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="commands[enable_taxonomy_commands]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Add a preview of available commands if a vocabulary is selected.
    $selected_vocabulary = $getConfigValue('commandsVocabulary');
    if (!empty($selected_vocabulary)) {
      // Load the terms from the selected vocabulary, sorted by weight.
      $term_storage = $this->getEntityTypeManager()->getStorage('taxonomy_term');

      // Use loadTree() to get validated terms with correct hierarchy.
      $tree_terms = $term_storage->loadTree($selected_vocabulary);
      $categories = [];

      if (!empty($tree_terms)) {
        // Organize terms by hierarchy (parent categories and child commands)
        foreach ($tree_terms as $tree_term) {
          if ($tree_term->parents[0] == 0) {
            // This is a parent category.
            $categories[$tree_term->tid] = [
              'term' => $tree_term,
              'children' => [],
            ];
          }
          else {
            // This is a child command.
            $parent_id = $tree_term->parents[0];
            if (isset($categories[$parent_id])) {
              $categories[$parent_id]['children'][] = $tree_term;
            }
          }
        }

        if (!empty($categories)) {

          // Create a structured table showing the command hierarchy.
          $rows = [];

          foreach ($categories as $category_data) {
            // Add the category as a header-like row.
            $rows[] = [
              'data' => [
              [
                'data' => $this->t('@category (Category)', ['@category' => $category_data['term']->name]),
                'colspan' => 2,
                'class' => ['command-category-header'],
              ],
              ],
              'class' => ['command-category-row'],
            ];

            // Add child commands to this category.
            if (!empty($category_data['children'])) {
              foreach ($category_data['children'] as $command_tree_term) {
                // Load the full term to get description.
                $command_term = $term_storage->load($command_tree_term->tid);

                // Add each command to the table.
                $description = $command_term->getDescription();
                // Strip HTML and truncate for display.
                $description = strip_tags($description);
                $short_description = strlen($description) > 100
                ? substr($description, 0, 100) . '...'
                : ($description ?: $this->t('- No instruction defined -'));

                $rows[] = [
                  'data' => [
                  [
                    'data' => $command_term->label(),
                    'class' => ['command-name'],
                  ],
                  [
                    'data' => $short_description,
                    'class' => ['command-description'],
                  ],
                  ],
                  'class' => ['command-row'],
                ];
              }
            }
            else {
              $rows[] = [
                'data' => [
                [
                  'data' => $this->t('No commands found in this category'),
                  'colspan' => 2,
                  'class' => ['empty-category'],
                ],
                ],
              ];
            }
          }

          $header = [
            $this->t('Command'),
            $this->t('Instruction'),
          ];

          $elements['commands']['preview_container']['commands_table'] = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('No commands found. <a href="@link">Add commands</a>', [
              '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/add',
            ]),
            '#attributes' => [
              'class' => ['commands-table'],
            ],
          ];

          // Add a link to manage the terms.
          $elements['commands']['preview_container']['manage_link'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('<a href="@link" class="button">Manage Commands</a>', [
              '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/overview',
            ]),
            '#attributes' => [
              'class' => ['commands-manage-link'],
            ],
          ];
        }
      }
      else {
        $elements['commands']['preview_container']['no_terms'] = [
          '#type' => 'markup',
          '#markup' => $this->t('No commands found. <a href="@link">Add commands</a>', [
            '@link' => '/admin/structure/taxonomy/manage/' . $selected_vocabulary . '/add',
          ]),
        ];
      }
    }

    // Add information about creating a vocabulary if none exists.
    if (!$has_vocabularies) {
      $elements['commands']['no_vocabularies'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No vocabularies found. <a href="@link">Create one</a> to manage your commands.', [
          '@link' => '/admin/structure/taxonomy/add',
        ]),
      ];
    }
  }

  /**
   * Formats an array of domains as a newline-separated string for textarea.
   *
   * @param array|string|null $domains
   *   The domains array or string.
   *
   * @return string
   *   The domains as a newline-separated string.
   */
  protected function formatDomainsForTextarea(array|string|null $domains): string {
    if (empty($domains)) {
      return '';
    }
    if (is_string($domains)) {
      return $domains;
    }
    return implode("\n", $domains);
  }

  /**
   * Parses a newline-separated string of domains into an array.
   *
   * @param string|null $text
   *   The textarea value.
   *
   * @return array
   *   The domains as an array.
   */
  protected function parseDomainsFromTextarea(?string $text): array {
    if (empty($text)) {
      return [];
    }
    $lines = preg_split('/\r\n|\r|\n/', $text);
    return array_values(array_filter(array_map('trim', $lines)));
  }

}
