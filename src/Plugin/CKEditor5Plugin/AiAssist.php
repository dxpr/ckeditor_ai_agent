<?php

declare(strict_types=1);

namespace Drupal\ckeditor_ai_assist\Plugin\CKEditor5Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 AI Assist plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class AiAssist extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {
  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => NULL,
      'model' => NULL,
      'endpoint_url' => NULL,
      'temperature' => NULL,
      'max_tokens' => NULL,
      'timeout_duration' => NULL,
      'retry_attempts' => NULL,
      'debug_mode' => NULL,
      'stream_content' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->configuration['api_key'],
      '#description' => $this->t('Enter your API Key. Leave empty to use <a href="@settings_url">global settings</a>.', [
        '@settings_url' => \Drupal::service('url_generator')->generateFromRoute('ckeditor_ai_assist.settings')
      ]),
      '#maxlength' => 512,
    ];

    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Model'),
      '#default_value' => $this->configuration['model'],
      '#options' => [
        '' => $this->t('- Use global settings -'),
        'gpt-4o' => $this->t('GPT-4o'),
        'gpt-4o-mini' => $this->t('GPT-4o Mini'),
        'gpt-4-turbo' => $this->t('GPT-4 Turbo'),
        'gpt-4' => $this->t('GPT-4'),
        'gpt-3.5-turbo' => $this->t('GPT-3.5 Turbo'),
        'gpt-3' => $this->t('GPT-3'),
        'kavya-m1' => $this->t('Kavya M1'),
      ],
      '#description' => $this->t('Select AI model or use global settings.'),
    ];

    $form['temperature'] = [
      '#type' => 'number',
      '#title' => $this->t('Temperature'),
      '#default_value' => $this->configuration['temperature'],
      '#min' => 0,
      '#max' => 2,
      '#step' => 0.1,
      '#description' => $this->t('Controls randomness in responses (0-2). Leave empty to use global settings.'),
    ];

    $form['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#default_value' => $this->configuration['max_tokens'],
      '#min' => 1,
      '#max' => 128000,
      '#description' => $this->t('Maximum number of tokens to generate. Leave empty to use global settings.'),
    ];

    $form['debug_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Debug Mode'),
      '#default_value' => $this->configuration['debug_mode'],
      '#options' => [
        '' => $this->t('- Use global settings -'),
        '0' => $this->t('Disabled'),
        '1' => $this->t('Enabled'),
      ],
      '#description' => $this->t('Enable debug mode for troubleshooting.'),
    ];

    $form['stream_content'] = [
      '#type' => 'select',
      '#title' => $this->t('Stream Content'),
      '#default_value' => $this->configuration['stream_content'],
      '#options' => [
        '' => $this->t('- Use global settings -'),
        '0' => $this->t('Disabled'),
        '1' => $this->t('Enabled'),
      ],
      '#description' => $this->t('Enable streaming of AI responses.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $temperature = $form_state->getValue('temperature');
    if ($temperature !== '' && !is_null($temperature)) {
      $temperature = (float) $temperature;
      if ($temperature < 0 || $temperature > 2) {
        $form_state->setErrorByName('temperature', $this->t('Temperature must be between 0 and 2.'));
      }
      $form_state->setValue('temperature', $temperature);
    } else {
      $form_state->setValue('temperature', NULL);
    }

    $max_tokens = $form_state->getValue('max_tokens');
    if ($max_tokens !== '' && !is_null($max_tokens)) {
      $form_state->setValue('max_tokens', (int) $max_tokens);
    } else {
      $form_state->setValue('max_tokens', NULL);
    }

    // Handle other fields
    foreach (['api_key', 'model'] as $field) {
      if ($form_state->getValue($field) === '') {
        $form_state->setValue($field, NULL);
      }
    }

    foreach (['debug_mode', 'stream_content'] as $field) {
      $value = $form_state->getValue($field);
      if ($value === '') {
        $form_state->setValue($field, NULL);
      } else {
        $form_state->setValue($field, (bool) $value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['api_key'] = $form_state->getValue('api_key');
    $this->configuration['model'] = $form_state->getValue('model');
    $this->configuration['temperature'] = $form_state->getValue('temperature');
    $this->configuration['max_tokens'] = $form_state->getValue('max_tokens');
    $this->configuration['debug_mode'] = $form_state->getValue('debug_mode');
    $this->configuration['stream_content'] = $form_state->getValue('stream_content');
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    return [
      'aiAssist' => [
        'apiKey' => $this->configuration['api_key'],
        'model' => $this->configuration['model'],
        'temperature' => $this->configuration['temperature'],
        'maxTokens' => $this->configuration['max_tokens'],
        'debugMode' => $this->configuration['debug_mode'],
        'streamContent' => $this->configuration['stream_content'],
      ],
    ];
  }

}
