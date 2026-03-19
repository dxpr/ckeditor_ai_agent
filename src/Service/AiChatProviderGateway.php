<?php

namespace Drupal\ckeditor_ai_agent\Service;

use Drupal\ai\AiProviderPluginManager;

/**
 * Resolves and wraps AI chat providers.
 */
final class AiChatProviderGateway {

  /**
   * The AI provider manager service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * Constructs a new gateway.
   *
   * @param \Drupal\ai\AiProviderPluginManager $ai_provider_manager
   *   The AI provider manager service.
   */
  public function __construct(AiProviderPluginManager $ai_provider_manager) {
    $this->aiProviderManager = $ai_provider_manager;
  }

  /**
   * Gets the default chat provider as a typed adapter.
   *
   * @return \Drupal\ckeditor_ai_agent\Service\AiChatProviderAdapter|null
   *   Wrapped provider, or NULL if no default is configured.
   */
  public function getDefaultChatProvider(): ?AiChatProviderAdapter {
    $default = $this->aiProviderManager->getDefaultProviderForOperationType('chat');
    if (empty($default['provider_id']) || !is_string($default['provider_id'])) {
      return NULL;
    }

    $provider = $this->aiProviderManager->createInstance($default['provider_id']);

    $default_model_id = '';
    if (!empty($default['model_id']) && is_string($default['model_id'])) {
      $default_model_id = $default['model_id'];
    }

    return new AiChatProviderAdapter($provider, $default_model_id);
  }

}
