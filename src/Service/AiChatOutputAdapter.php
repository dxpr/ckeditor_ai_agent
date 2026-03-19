<?php

namespace Drupal\ckeditor_ai_agent\Service;

/**
 * Typed wrapper around AI chat operation output.
 */
final class AiChatOutputAdapter {

  /**
   * The raw provider output object.
   *
   * @var object
   */
  protected object $output;

  /**
   * Constructs a new output adapter.
   *
   * @param object $output
   *   The provider chat output object.
   */
  public function __construct(object $output) {
    $this->output = $output;
  }

  /**
   * Gets the normalized AI response.
   *
   * @return mixed
   *   The normalized response from the AI operation output.
   */
  public function getNormalized(): mixed {
    $get_normalized = [$this->output, 'getNormalized'];
    if (!is_callable($get_normalized)) {
      throw new \RuntimeException('AI provider output does not support getNormalized().');
    }

    return call_user_func($get_normalized);
  }

}
