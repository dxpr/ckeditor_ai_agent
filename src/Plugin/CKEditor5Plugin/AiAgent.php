<?php

declare(strict_types=1);

namespace Drupal\ckeditor_ai_agent\Plugin\CKEditor5Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Messenger\MessengerInterface;
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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
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
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->keyService = $key_service;
    $this->extensionPathResolver = $extension_path_resolver;
    $this->urlGenerator = $url_generator;
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
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
      $container->get('module_handler')
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
        'apiKey' => NULL,
        'engine' => NULL,
        'model' => NULL,
        'ollamaModel' => NULL,
        'endpointUrl' => NULL,
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
    $config = $this->configFactory->get('ckeditor_ai_agent.settings');
    $editor_config = $this->configuration['aiAgent'] ?? [];

    // Build configuration with proper fallback handling.
    $result = ['aiAgent' => []];

    // Check if the ai module is available for proxied requests.
    $use_ai_module = $this->moduleHandler->moduleExists('ai');

    // Basic settings.
    $settings_map = $this->getSettingsMap();
    foreach ($settings_map as $js_key => $drupal_key) {
      // Handle apiKey separately.
      if ($js_key === 'apiKey') {
        if ($use_ai_module) {
          // When ai module is available, don't expose API key to browser.
          continue;
        }
        // First check for editor-specific key provider.
        if (isset($editor_config['key_provider']) && $editor_config['key_provider'] !== '') {
          $result['aiAgent'][$js_key] = $this->keyService->getKeyValue($editor_config['key_provider']);
        }
        // Then fall back to global key.
        else {
          $result['aiAgent'][$js_key] = $this->keyService->getApiKey();
        }
        continue;
      }

      // Only set if either editor config or global config has a non-null value.
      if (isset($editor_config[$js_key]) && !empty($editor_config[$js_key])) {
        $result['aiAgent'][$js_key] = $editor_config[$js_key];
      }
      elseif ($config->get($drupal_key) !== NULL && !empty($config->get($drupal_key))) {
        $result['aiAgent'][$js_key] = $config->get($drupal_key);
      }
    }

    if ($use_ai_module) {
      // Route requests through the Drupal proxy controller.
      $result['aiAgent']['endpointUrl'] = $this->urlGenerator->generateFromRoute('ckeditor_ai_agent.ai_chat', [], ['absolute' => TRUE]);
      $result['aiAgent']['engine'] = 'dxai';
      // Pass model through for the proxy to use.
      $model = $result['aiAgent']['model'] ?? 'openai:gpt-4o';
      if (str_contains($model, ':')) {
        [, $model_name] = explode(':', $model, 2);
        $result['aiAgent']['model'] = $model_name;
      }
    }
    else {
      // Handle engine/model for direct API access.
      $model = $result['aiAgent']['model'] ?? 'openai:gpt-4o';
      if (str_contains($model, ':')) {
        [$engine, $model_name] = explode(':', $model, 2);
        $result['aiAgent']['engine'] = $engine;
        if ($engine === 'ollama') {
          // For Ollama, use the ollamaModel value.
          $result['aiAgent']['model'] = $result['aiAgent']['ollamaModel'] ?? $config->get('ollamaModel') ?? '';
        }
        else {
          $result['aiAgent']['model'] = $model_name;
        }
      }
      else {
        // Fallback for legacy configurations.
        $result['aiAgent']['engine'] = 'openai';
        $result['aiAgent']['model'] = $model ?: 'gpt-4o';
      }
    }

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
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $form
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Required by interface, but no validation needed.
  }

}
