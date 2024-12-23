<?php

namespace Drupal\ckeditor_ai_agent\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure CKEditor AI Agent settings.
 */
class AiAgentSettingsForm extends ConfigFormBase {
  use AiAgentFormTrait;
  use ConfigSetterTrait;
  use ConfigMappingTrait;

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * Constructs a new AiAgentSettingsForm.
   *
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extension_path_resolver
   *   The extension path resolver.
   */
  public function __construct(ExtensionPathResolver $extension_path_resolver) {
    $this->extensionPathResolver = $extension_path_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('extension.path.resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor_ai_agent_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return string[]
   */
  protected function getEditableConfigNames(): array {
    return ['ckeditor_ai_agent.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $form
   * @phpstan-return mixed[]
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ckeditor_ai_agent.settings');

    // Get common form elements.
    $form = $this->getCommonFormElements(FALSE, $config);

    // Enforce nested collections of form elements.
    $form['#tree'] = TRUE;

    // Set default values from config.
    $form['basic_settings']['api_key']['#default_value'] = $config->get('api_key');
    $form['basic_settings']['model']['#default_value'] = $config->get('model') ?: 'gpt-4o';
    $form['basic_settings']['endpoint_url']['#default_value'] = $config->get('endpoint_url') ?: 'https://api.openai.com/v1/chat/completions';

    $form['advanced_settings']['temperature']['#default_value'] = $config->get('temperature');
    $form['advanced_settings']['tokens']['max_output_tokens']['#default_value'] = $config->get('max_output_tokens');
    $form['advanced_settings']['tokens']['max_input_tokens']['#default_value'] = $config->get('max_input_tokens');
    $form['advanced_settings']['context']['context_size']['#default_value'] = $config->get('context_size');
    $form['advanced_settings']['context']['editor_context_ratio']['#default_value'] = $config->get('editor_context_ratio') ?: 0.3;

    $form['performance_settings']['timeout_duration']['#default_value'] = $config->get('timeout_duration') ?: 45000;
    $form['performance_settings']['retry_attempts']['#default_value'] = $config->get('retry_attempts') ?: 1;

    $form['behavior_settings']['debug_mode']['#default_value'] = $config->get('debug_mode') ? '1' : '0';
    $form['behavior_settings']['stream_content']['#default_value'] = $config->get('stream_content') ? '1' : '0';
    $form['behavior_settings']['show_error_duration']['#default_value'] = $config->get('show_error_duration') ?: 5000;

    $form['moderation_settings']['moderation_enable']['#default_value'] = $config->get('moderation.enable');
    $form['moderation_settings']['moderation_key']['#default_value'] = $config->get('moderation.key');
    $form['moderation_settings']['moderation_disable_flags']['#default_value'] = $config->get('moderation.disable_flags') ?: [];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $form
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Validate temperature range.
    $temperature = $form_state->getValue('temperature');
    if ($temperature !== '' && ($temperature < 0 || $temperature > 2)) {
      $form_state->setErrorByName('temperature', $this->t('Temperature must be between 0 and 2.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param mixed[] $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('ckeditor_ai_agent.settings');
    $values = $form_state->getValues();

    $processed = $this->processConfigValues($values, $this->getConfigMapping());
    foreach ($processed as $key => $value) {
      $config->set($key, $value);
    }

    // Handle moderation and prompt settings.
    $moderation = $this->processModerationSettings($values);
    $config->set('moderation', $moderation);

    $prompt_settings = $this->processPromptSettings($values);
    $config->set('prompt_settings', $prompt_settings);

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
