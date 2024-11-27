<?php

namespace Drupal\ckeditor_ai_assist\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure CKEditor AI Assist settings.
 */
class AiAssistSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor_ai_assist_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ckeditor_ai_assist.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ckeditor_ai_assist.settings');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
      '#description' => $this->t('Enter your AI API key'),
    ];

    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Model'),
      '#default_value' => $config->get('model') ?: 'gpt-4o',
      '#options' => [
        'gpt-3' => 'GPT-3',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        'gpt-4' => 'GPT-4',
        'gpt-4o' => 'GPT-4o',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-4o-mini' => 'GPT-4o Mini',
        'kavya-m1' => 'Kavya M1',
      ],
    ];

    $form['endpoint_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Endpoint URL'),
      '#default_value' => $config->get('endpoint_url') ?: 'https://kavya.dxpr.com/v1/chat/completions',
      '#required' => TRUE,
    ];

    $form['temperature'] = [
      '#type' => 'number',
      '#title' => $this->t('Temperature'),
      '#default_value' => $config->get('temperature') ?: 0.7,
      '#min' => 0,
      '#max' => 2,
      '#step' => 0.1,
      '#description' => $this->t('Controls randomness in responses (0-2)'),
    ];

    $form['timeout_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout Duration (ms)'),
      '#default_value' => $config->get('timeout_duration') ?: 45000,
      '#min' => 1000,
      '#step' => 1000,
    ];

    $form['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#default_value' => $config->get('max_tokens') ?: 4096,
      '#min' => 1,
      '#max' => 128000,
    ];

    $form['retry_attempts'] = [
      '#type' => 'number',
      '#title' => $this->t('Retry Attempts'),
      '#default_value' => $config->get('retry_attempts') ?: 1,
      '#min' => 0,
    ];

    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug Mode'),
      '#default_value' => $config->get('debug_mode') ?: FALSE,
    ];

    $form['stream_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Stream Content'),
      '#default_value' => $config->get('stream_content') ?: TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    
    // Validate temperature range
    $temperature = $form_state->getValue('temperature');
    if ($temperature < 0 || $temperature > 2) {
      $form_state->setErrorByName('temperature', $this->t('Temperature must be between 0 and 2.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('ckeditor_ai_assist.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('model', $form_state->getValue('model'))
      ->set('endpoint_url', $form_state->getValue('endpoint_url'))
      ->set('temperature', $form_state->getValue('temperature'))
      ->set('timeout_duration', $form_state->getValue('timeout_duration'))
      ->set('max_tokens', $form_state->getValue('max_tokens'))
      ->set('retry_attempts', $form_state->getValue('retry_attempts'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->set('stream_content', $form_state->getValue('stream_content'))
      ->save();

    parent::submitForm($form, $form_state);
  }
} 