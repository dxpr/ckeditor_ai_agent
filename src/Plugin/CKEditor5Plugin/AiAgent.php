<?php

declare(strict_types=1);

namespace Drupal\ckeditor_ai_agent\Plugin\CKEditor5Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;
use Drupal\ckeditor_ai_agent\Form\AiAgentFormTrait;
use Drupal\ckeditor_ai_agent\Form\ConfigSetterTrait;
use Drupal\ckeditor_ai_agent\Form\ConfigMappingTrait;

/**
 * CKEditor 5 AI Agent plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class AiAgent extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {
  use CKEditor5PluginConfigurableTrait;
  use AiAgentFormTrait;
  use ConfigSetterTrait;
  use ConfigMappingTrait;

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<string, mixed>
   */
  public function defaultConfiguration(): array {
    return [
      'aiAgent' => [
        'apiKey' => NULL,
        'model' => NULL,
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
        'moderation' => [
          'enable' => NULL,
          'key' => NULL,
          'disableFlags' => [
            'sexual' => 0,
            'sexual/minors' => 0,
            'harassment' => 0,
            'harassment/threatening' => 0,
            'hate' => 0,
            'hate/threatening' => 0,
            'illicit' => 0,
            'illicit/violent' => 0,
            'self-harm' => 0,
            'self-harm/intent' => 0,
            'self-harm/instructions' => 0,
            'violence' => 0,
            'violence/graphic' => 0,
          ],
        ],
        'promptSettings' => [
          'overrides' => [],
          'additions' => [],
        ],
      ],
      'test_field' => '',
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
    
    $this->configuration['aiAgent'] = $this->processConfigValues(
      $values,
      $this->getConfigMapping(TRUE)
    );

    // Handle moderation and prompt settings.
    $this->configuration['aiAgent']['moderation'] = $this->processModerationSettings($values);
    
    $prompt_settings = $this->processPromptSettings($values['prompt_settings'] ?? []);
    $this->configuration['aiAgent']['promptSettings'] = $prompt_settings;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $static_plugin_config
   * @phpstan-return array<string, mixed>
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $config = \Drupal::config('ckeditor_ai_agent.settings');
    $editor_config = $this->configuration['aiAgent'] ?? [];

    // Build configuration with proper fallback handling.
    $result = ['aiAgent' => []];

    // Basic settings.
    $settings_map = [
      'apiKey' => 'api_key',
      'model' => 'model',
      'endpointUrl' => 'endpoint_url',
      'temperature' => 'temperature',
      'maxOutputTokens' => 'max_output_tokens',
      'maxInputTokens' => 'max_input_tokens',
      'contextSize' => 'context_size',
      'editorContextRatio' => 'editor_context_ratio',
      'timeOutDuration' => 'timeout_duration',
      'retryAttempts' => 'retry_attempts',
      'debugMode' => 'debug_mode',
      'streamContent' => 'stream_content',
      'showErrorDuration' => 'show_error_duration',
    ];

    foreach ($settings_map as $js_key => $drupal_key) {
      // Only set if either editor config or global config has a non-null value.
      if (isset($editor_config[$js_key]) && !empty($editor_config[$js_key])) {
        $result['aiAgent'][$js_key] = $editor_config[$js_key];
      }
      elseif ($config->get($drupal_key) !== NULL && !empty($config->get($drupal_key))) {
        $result['aiAgent'][$js_key] = $config->get($drupal_key);
      }
    }

    // Moderation settings.
    if (isset($editor_config['moderation']) || $config->get('moderation')) {
      $result['aiAgent']['moderation'] = [
        'enable' => $editor_config['moderation']['enable'] ?? $config->get('moderation.enable'),
        'key' => $editor_config['moderation']['key'] ?? $config->get('moderation.key'),
        'disableFlags' => $editor_config['moderation']['disableFlags'] ?? $config->get('moderation.disable_flags'),
      ];
    }

    // Prompt settings.
    if (isset($editor_config['promptSettings']) || $config->get('prompt_settings')) {
      $result['aiAgent']['promptSettings'] = [
        'overrides' => [],
        'additions' => [],
      ];

      foreach (['overrides', 'additions'] as $type) {
        $editor_settings = $editor_config['promptSettings'][$type] ?? [];
        $global_settings = $config->get("prompt_settings.$type") ?? [];

        foreach ($this->getPromptComponents() as $component) {
          if (!empty($editor_settings[$component])) {
            $result['aiAgent']['promptSettings'][$type][$component] = $editor_settings[$component];
          }
          elseif (!empty($global_settings[$component])) {
            $result['aiAgent']['promptSettings'][$type][$component] = $global_settings[$component];
          }
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
