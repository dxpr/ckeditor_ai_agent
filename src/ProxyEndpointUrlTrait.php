<?php

namespace Drupal\ckeditor_ai_agent;

use Drupal\Core\Url;

/**
 * Provides the tokenized proxy endpoint URL for the AI chat route.
 *
 * Classes using this trait must have a $csrfToken property of type
 * \Drupal\Core\Access\CsrfTokenGenerator.
 */
trait ProxyEndpointUrlTrait {

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
