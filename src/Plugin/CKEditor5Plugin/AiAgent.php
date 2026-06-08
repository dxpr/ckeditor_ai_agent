<?php

declare(strict_types=1);

namespace Drupal\ckeditor_ai_agent\Plugin\CKEditor5Plugin;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\editor\EditorInterface;
use Drupal\ckeditor_ai_agent\Form\AiAgentFormTrait;
use Drupal\ckeditor_ai_agent\Form\ConfigSetterTrait;
use Drupal\ckeditor_ai_agent\Form\ConfigMappingTrait;
use Drupal\ckeditor_ai_agent\Service\AiAgentKeyService;

/**
 * CKEditor 5 AI Agent plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class AiAgent extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface, ContainerFactoryPluginInterface {
  use CKEditor5PluginConfigurableTrait;
  use AiAgentFormTrait;
  use ConfigSetterTrait;
  use ConfigMappingTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The AI Agent key service.
   *
   * @var \Drupal\ckeditor_ai_agent\Service\AiAgentKeyService
   */
  protected AiAgentKeyService $keyService;

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected ExtensionPathResolver $extensionPathResolver;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected UrlGeneratorInterface $urlGenerator;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfToken;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs an AiAgent plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\ckeditor_ai_agent\Service\AiAgentKeyService $key_service
   *   The AI Agent key service.
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extension_path_resolver
   *   The extension path resolver.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    CKEditor5PluginDefinition $plugin_definition,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    AiAgentKeyService $key_service,
    ExtensionPathResolver $extension_path_resolver,
    UrlGeneratorInterface $url_generator,
    MessengerInterface $messenger,
    CsrfTokenGenerator $csrf_token,
    AccountProxyInterface $current_user,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->keyService = $key_service;
    $this->extensionPathResolver = $extension_path_resolver;
    $this->urlGenerator = $url_generator;
    $this->messenger = $messenger;
    $this->csrfToken = $csrf_token;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var static */
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('ckeditor_ai_agent.key_service'),
      $container->get('extension.path.resolver'),
      $container->get('url_generator'),
      $container->get('messenger'),
      $container->get('csrf_token'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExtensionPathResolver(): ExtensionPathResolver {
    return $this->extensionPathResolver;
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrlGenerator(): UrlGeneratorInterface {
    return $this->urlGenerator;
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigFactory(): ConfigFactoryInterface {
    return $this->configFactory;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMessenger(): MessengerInterface {
    return $this->messenger;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<string, mixed>
   */
  public function defaultConfiguration(): array {
    return [
      'aiAgent' => [
        'contentScope' => NULL,
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
        'moderationEnable' => NULL,
        'moderationKey' => NULL,
        'toneOfVoiceVocabulary' => NULL,
        'commandsVocabulary' => NULL,
        'defaultToneOfVoice' => NULL,
        'tonesDropdown' => [],
        'promptSettings' => [
          'overrides' => [],
          'additions' => [],
        ],
        'aiOutputSecurity' => [
          'allowedDomains' => ['promptahuman.com'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $form
   * @phpstan-return array<string, mixed>
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $this->getCommonFormElements(TRUE, $this->configuration);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $form
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $configMapping = $this->getConfigMapping();
    $this->configuration['aiAgent'] = $this->processConfigValues($values, $configMapping);

    // Handle prompt settings.
    if (isset($values['promptSettings'])) {
      $prompt_settings = $this->processPromptSettings($values['promptSettings']);
      $this->configuration['aiAgent']['promptSettings'] = $prompt_settings;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $static_plugin_config
   * @phpstan-return array<string, mixed>
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    // Do not expose AI controls to users without the proxy permission.
    if (!$this->currentUser->hasPermission('use ckeditor ai agent')) {
      return [];
    }

    $config = $this->configFactory->get('ckeditor_ai_agent.settings');
    $editor_config = $this->configuration['aiAgent'] ?? [];

    // Build configuration with proper fallback handling.
    $result = ['aiAgent' => []];

    // Map remaining CKEditor-specific settings (provider/model/key are
    // handled server-side by the AI module; they are not in this map).
    $settings_map = $this->getSettingsMap();
    foreach ($settings_map as $js_key => $drupal_key) {
      // Only set if either editor config or global config has a non-null value.
      if (isset($editor_config[$js_key]) && !empty($editor_config[$js_key])) {
        $result['aiAgent'][$js_key] = $editor_config[$js_key];
      }
      elseif ($config->get($drupal_key) !== NULL && !empty($config->get($drupal_key))) {
        $result['aiAgent'][$js_key] = $config->get($drupal_key);
      }
    }

    // Route requests through the Drupal proxy controller.
    $result['aiAgent']['endpointUrl'] = $this->getTokenizedProxyEndpointUrl();
    $result['aiAgent']['engine'] = 'dxai';
    // Let the AI module's default provider/model win; do not send a model
    // from legacy config unless the admin explicitly set a per-editor override.
    unset($result['aiAgent']['model']);

    // Add taxonomy-based tones of voice if configured.
    $tone_vocabulary = $result['aiAgent']['toneOfVoiceVocabulary'] ?? '';

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
            // Use the exact key expected by the plugin.
            $result['aiAgent']['tonesDropdown'] = $tones_dropdown;

            // Set the first term (lowest weight) as the default tone.
            if ($first_term !== NULL) {
              $result['aiAgent']['defaultTone'] = $first_term;
            }
          }
        }
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('ckeditor_ai_agent')->error('Error loading tone of voice taxonomy terms in AiAgent plugin: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }

    // Add taxonomy-based commands if configured.
    $commands_vocabulary = $result['aiAgent']['commandsVocabulary'] ?? '';

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
            $result['aiAgent']['commandsDropdown'] = $commands_dropdown;
          }
        }
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('ckeditor_ai_agent')->error('Error loading commands taxonomy terms: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }

    // Handle AI output security settings.
    $ai_output_security = $editor_config['aiOutputSecurity'] ?? $config->get('aiOutputSecurity');
    if (!empty($ai_output_security)) {
      $result['aiAgent']['aiOutputSecurity'] = $ai_output_security;
    }
    else {
      // Set defaults if not configured.
      $result['aiAgent']['aiOutputSecurity'] = [
        'allowedDomains' => ['promptahuman.com'],
      ];
    }

    // Handle prompt settings.
    $result['aiAgent']['promptSettings'] = [
      'overrides' => [],
      'additions' => [],
    ];

    foreach (['overrides', 'additions'] as $type) {
      $editor_settings = $editor_config['promptSettings'][$type] ?? [];
      $global_settings = $config->get("promptSettings.$type") ?? [];

      foreach ($this->getPromptComponents() as $component) {
        // Special handling for tone when using taxonomy integration.
        if ($component === 'tone' && !empty($tone_vocabulary) && !empty($first_term)) {
          // For overrides, use the first term's tone as the tone.
          if ($type === 'overrides') {
            $result['aiAgent']['promptSettings'][$type][$component] = $first_term['tone'];
          }
          // Skip additions for tone when using taxonomy.
          continue;
        }

        if (!empty($editor_settings[$component])) {
          $result['aiAgent']['promptSettings'][$type][$component] = $editor_settings[$component];
        }
        elseif (!empty($global_settings[$component])) {
          $result['aiAgent']['promptSettings'][$type][$component] = $global_settings[$component];
        }
      }
    }

    return $result;
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

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $form
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Required by interface, but no validation needed.
  }

}
