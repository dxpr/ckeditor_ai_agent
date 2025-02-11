<?php

namespace Drupal\ckeditor_ai_agent\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for handling AI Agent API keys.
 */
class AiAgentKeyService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The key repository.
   *
   * @var object|null
   */
  protected $keyRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AiAgentKeyService.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param object|null $key_repository
   *   The key repository service, if available.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    EntityTypeManagerInterface $entity_type_manager,
    ?object $key_repository = NULL,
  ) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->keyRepository = $key_repository;
  }

  /**
   * Gets the API key from the configured storage method.
   *
   * @param string|null $editor_id
   *   Optional editor ID to get editor-specific key.
   *
   * @return string|null
   *   The API key if found, NULL otherwise.
   */
  public function getApiKey(?string $editor_id = NULL): ?string {
    // First check editor-specific key if editor_id is provided.
    if ($editor_id) {
      $editor_config = $this->configFactory->get('editor.editor.' . $editor_id);
      if ($editor_config && $editor_config->get('settings.plugins.ckeditor_ai_agent_ai_agent.aiAgent.key_provider')) {
        $key_id = $editor_config->get('settings.plugins.ckeditor_ai_agent_ai_agent.aiAgent.key_provider');
        return $this->getKeyValue($key_id);
      }
    }

    // Get global key.
    $config = $this->configFactory->get('ckeditor_ai_agent.settings');
    $key_id = $config->get('key_provider');
    return $this->getKeyValue($key_id);
  }

  /**
   * Sets the API key.
   *
   * @param string|null $selected_key_id
   *   The key ID to use.
   * @param string|null $editor_id
   *   Optional editor ID for editor-specific keys.
   */
  public function setApiKey(
    ?string $selected_key_id = NULL,
    ?string $editor_id = NULL
  ): void {
    if ($editor_id) {
      $this->setEditorKey($editor_id, $selected_key_id);
      return;
    }

    $config = $this->configFactory->getEditable('ckeditor_ai_agent.settings');
    $config->set('key_provider', $selected_key_id)->save();
  }

  /**
   * Sets an editor-specific API key.
   *
   * @param string $editor_id
   *   The editor ID.
   * @param string|null $selected_key_id
   *   The key ID for Key module storage.
   */
  protected function setEditorKey(
    string $editor_id,
    ?string $selected_key_id
  ): void {
    $config = $this->configFactory->getEditable('editor.editor.' . $editor_id);
    if (!$config) {
      return;
    }

    $config
      ->set('settings.plugins.ckeditor_ai_agent_ai_agent.aiAgent.key_provider', $selected_key_id)
      ->save();
  }

  /**
   * Gets a key value from the Key module.
   *
   * @param string|null $key_id
   *   The key ID.
   *
   * @return string|null
   *   The key value if found, NULL otherwise.
   */
  public function getKeyValue(?string $key_id): ?string {
    if (!$key_id || !$this->keyRepository || !method_exists($this->keyRepository, 'getKey')) {
      return NULL;
    }

    $key = $this->keyRepository->getKey($key_id);
    return $key && method_exists($key, 'getKeyValue') ? $key->getKeyValue() : NULL;
  }

} 