<?php

namespace Drupal\ckeditor_ai_agent;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\editor\Entity\Editor;

/**
 * Manages configuration for the CKEditor AI Agent plugin.
 */
class AiAgentConfigurationManager {

  use ProxyEndpointUrlTrait;
  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfToken;

  /**
   * Constructs a new AiAgentConfigurationManager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\ai\AiProviderPluginManager $ai_provider_manager
   *   The AI provider plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AiProviderPluginManager $ai_provider_manager,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelInterface $logger,
    CsrfTokenGenerator $csrf_token,
  ) {
    $this->configFactory = $config_factory;
    $this->aiProviderManager = $ai_provider_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->csrfToken = $csrf_token;
  }

  /**
   * Gets the configuration.
   *
   * @return array<string, mixed>
   *   The configuration array.
   */
  public function getConfiguration(): array {
    $config = $this->configFactory->get('ckeditor_ai_agent.settings');
    $result = [];

    $result['contentScope'] = $config->get('contentScope');
    $result['temperature'] = $config->get('temperature');
    $result['maxOutputTokens'] = $config->get('maxOutputTokens');
    $result['maxInputTokens'] = $config->get('maxInputTokens');
    $result['contextSize'] = $config->get('contextSize');
    $result['editorContextRatio'] = $config->get('editorContextRatio');
    $result['timeOutDuration'] = $config->get('timeOutDuration');
    $result['retryAttempts'] = $config->get('retryAttempts');
    $result['debugMode'] = $config->get('debugMode');
    $result['streamContent'] = $config->get('streamContent');
    $result['showErrorDuration'] = $config->get('showErrorDuration');
    $result['moderationEnable'] = $config->get('moderationEnable');
    $result['moderationKey'] = $config->get('moderationKey');
    $result['promptSettings'] = $config->get('promptSettings') ?: [];

    return $result;
  }

  /**
   * Gets the CKEditor configuration.
   *
   * @param \Drupal\editor\Entity\Editor|null $editor
   *   The editor entity.
   *
   * @return array<string, array<string, mixed>>
   *   The CKEditor configuration.
   */
  public function getCkEditorConfig(?Editor $editor = NULL): array {
    $global_config = $this->configFactory->get('ckeditor_ai_agent.settings');

    // Structure the config to match the aiAgent JS configuration.
    $config = [
      'aiAgent' => [
        'contentScope' => $global_config->get('contentScope'),
        'temperature' => $global_config->get('temperature'),
        'maxOutputTokens' => $global_config->get('maxOutputTokens'),
        'maxInputTokens' => $global_config->get('maxInputTokens'),
        'contextSize' => $global_config->get('contextSize'),
        'editorContextRatio' => $global_config->get('editorContextRatio'),
        'timeOutDuration' => $global_config->get('timeOutDuration'),
        'retryAttempts' => $global_config->get('retryAttempts'),
        'debugMode' => $global_config->get('debugMode'),
        'showErrorDuration' => $global_config->get('showErrorDuration'),
        'moderationEnable' => $global_config->get('moderationEnable'),
        'moderationKey' => $global_config->get('moderationKey'),
        'promptSettings' => [
          'overrides' => [],
          'additions' => [],
        ],
      ],
    ];

    // Route requests through the Drupal proxy controller.
    $config['aiAgent']['endpointUrl'] = $this->getTokenizedProxyEndpointUrl();
    $config['aiAgent']['engine'] = 'dxai';

    // Properly populate prompt settings from configuration.
    foreach (['overrides', 'additions'] as $type) {
      $settings = $global_config->get("promptSettings.$type");
      if (!empty($settings) && is_array($settings)) {
        foreach ($settings as $component => $value) {
          $config['aiAgent']['promptSettings'][$type][$component] = $value;
        }
      }
    }

    // Add taxonomy-based tones of voice if configured.
    $tone_vocabulary = $global_config->get('toneOfVoiceVocabulary');

    if (!empty($tone_vocabulary)) {
      try {
        // Load the terms from the vocabulary, sorted by weight.
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        // Use loadTree() to get validated terms with correct hierarchy.
        $tree_terms = $term_storage->loadTree($tone_vocabulary);
        $terms = [];
        if (!empty($tree_terms)) {
          $tids = array_column($tree_terms, 'tid');
          $terms = $term_storage->loadMultiple($tids);
          $tones_dropdown = [];
          $first_term = NULL;

          // Add each taxonomy term as a tone option.
          foreach ($terms as $term) {
            $description = $term->getDescription();
            // Only add terms that have a description (tone)
            if (!empty($description)) {
              $tone_item = [
                'label' => $term->label(),
                'tone' => $description,
              ];

              $tones_dropdown[] = $tone_item;

              // Keep track of the first valid term (lowest weight) to use as
              // default.
              if ($first_term === NULL) {
                $first_term = $tone_item;
              }
            }
          }

          // Only add the tones to the configuration if we have valid tones.
          if (!empty($tones_dropdown)) {
            // Set the tones dropdown.
            $config['aiAgent']['tonesDropdown'] = $tones_dropdown;

            // Set the first term (lowest weight) as the default tone.
            if ($first_term !== NULL) {
              $config['aiAgent']['defaultTone'] = $first_term;

              // Also set the tone in the prompt settings.
              $config['aiAgent']['promptSettings']['overrides']['tone'] = $first_term['tone'];
            }
          }
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Error loading tone of voice taxonomy terms: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }

    // Add taxonomy-based commands if configured.
    $commands_vocabulary = $global_config->get('commandsVocabulary');

    if (!empty($commands_vocabulary)) {
      try {
        // Use loadTree() to get validated terms with correct hierarchy.
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $tree_terms = $term_storage->loadTree($commands_vocabulary);

        if (!empty($tree_terms)) {
          $commands_dropdown = [];
          $categories = [];

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

          // Build the dropdown structure.
          foreach ($categories as $category_data) {
            $command_group = [
              'title' => $category_data['term']->name,
              'items' => [],
            ];

            // Add child commands to this category.
            foreach ($category_data['children'] as $command_tree_term) {
              // Load the full term to get description.
              $command_term = $term_storage->load($command_tree_term->tid);
              $description = $command_term->getDescription();

              // Only add terms that have a description (command)
              if (!empty($description)) {
                $command_item = [
                  'title' => $command_term->label(),
                  'command' => $description,
                ];

                $command_group['items'][] = $command_item;
              }
            }

            // Only add the category if it has commands.
            if (!empty($command_group['items'])) {
              $commands_dropdown[] = $command_group;
            }
          }

          // Only add the commands dropdown to the configuration if we have
          // valid categories.
          if (!empty($commands_dropdown)) {
            $config['aiAgent']['commandsDropdown'] = $commands_dropdown;
          }
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Error loading commands taxonomy terms: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }

    return $config;
  }

  /**
   * Checks the AI provider setup and returns a requirements entry.
   *
   * @return array
   *   A requirements array entry with 'title', 'severity', and optionally
   *   'description' and 'value' keys.
   */
  public function checkAiProvider(): array {
    $settings_url = Url::fromRoute('ai.settings_form')->toString();

    $definitions = $this->aiProviderManager->getDefinitions();
    if (empty($definitions)) {
      return [
        'title' => $this->t('CKEditor AI Agent'),
        'description' => $this->t('No AI provider modules are installed. Install at least one provider (e.g. <a href="@dxpr">DXPR AI Provider</a>, <a href="@openai">OpenAI</a>, <a href="@anthropic">Anthropic</a>, or <a href="@ollama">Ollama</a>) so the AI module can route requests.', [
          '@dxpr' => 'https://www.drupal.org/project/ai_provider_dxpr',
          '@openai' => 'https://www.drupal.org/project/ai_provider_openai',
          '@anthropic' => 'https://www.drupal.org/project/ai_provider_anthropic',
          '@ollama' => 'https://www.drupal.org/project/ai_provider_ollama',
        ]),
        'severity' => REQUIREMENT_ERROR,
      ];
    }

    $usable = $this->aiProviderManager->getProvidersForOperationType('chat', TRUE);
    if (empty($usable)) {
      $installed_names = array_map(fn($d) => $d['label'] ?? $d['id'], $definitions);
      return [
        'title' => $this->t('CKEditor AI Agent'),
        'description' => $this->t('Provider modules are installed (@providers) but none are ready for chat. This usually means an API key or authentication is missing. Configure your provider at <a href="@settings">AI settings</a>.', [
          '@providers' => implode(', ', $installed_names),
          '@settings' => $settings_url,
        ]),
        'severity' => REQUIREMENT_ERROR,
      ];
    }

    $default = $this->aiProviderManager->getDefaultProviderForOperationType('chat');
    if (empty($default['provider_id'])) {
      $usable_names = array_map(fn($d) => $d['label'] ?? $d['id'], $usable);
      return [
        'title' => $this->t('CKEditor AI Agent'),
        'description' => $this->t('@count chat-capable provider(s) available (@providers) but no default is selected. Choose a default chat provider at <a href="@settings">AI settings</a>.', [
          '@count' => count($usable),
          '@providers' => implode(', ', $usable_names),
          '@settings' => $settings_url,
        ]),
        'severity' => REQUIREMENT_WARNING,
      ];
    }

    $provider_label = $default['provider_id'];
    $model_label = $default['model_id'] ?? '';
    if (isset($definitions[$default['provider_id']]['label'])) {
      $provider_label = $definitions[$default['provider_id']]['label'];
    }
    return [
      'title' => $this->t('CKEditor AI Agent'),
      'value' => $provider_label . ($model_label ? ' / ' . $model_label : ''),
      'description' => $this->t('Requests are routed server-side through the AI module. Change the provider at <a href="@settings">AI settings</a>.', [
        '@settings' => $settings_url,
      ]),
      'severity' => REQUIREMENT_OK,
    ];
  }

}
