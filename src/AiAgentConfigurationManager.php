<?php

namespace Drupal\ckeditor_ai_agent;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Drupal\editor\Entity\Editor;
use Drupal\ckeditor_ai_agent\Service\AiAgentKeyService;

/**
 * Manages configuration for the CKEditor AI Agent plugin.
 */
class AiAgentConfigurationManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The key service.
   *
   * @var \Drupal\ckeditor_ai_agent\Service\AiAgentKeyService
   */
  protected $keyService;

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
   * @param \Drupal\ckeditor_ai_agent\Service\AiAgentKeyService $key_service
   *   The key service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AiAgentKeyService $key_service,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelInterface $logger,
    CsrfTokenGenerator $csrf_token,
  ) {
    $this->configFactory = $config_factory;
    $this->keyService = $key_service;
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
   * Builds the tokenized endpoint URL for the AI proxy route.
   *
   * @return string
   *   The absolute tokenized endpoint URL.
   */
  protected function getTokenizedProxyEndpointUrl(): string {
    $url = Url::fromRoute('ckeditor_ai_agent.ai_chat');
    $token = $this->csrfToken->get($url->getInternalPath());
    $url->setOptions([
      'absolute' => TRUE,
      'query' => ['token' => $token],
    ]);
    return $url->toString();
  }

}
