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
    $form['basic_settings']['apiKey']['#default_value'] = $config->get('apiKey');
    $form['basic_settings']['model']['#default_value'] = $config->get('model') ?: 'gpt-4o';
    $form['basic_settings']['endpointUrl']['#default_value'] = $config->get('endpointUrl') ?: 'https://api.openai.com/v1/chat/completions';
    $form['basic_settings']['contentScope']['#default_value'] = $config->get('contentScope');

    $form['advanced_settings']['temperature']['#default_value'] = $config->get('temperature');
    $form['advanced_settings']['tokens']['maxOutputTokens']['#default_value'] = $config->get('maxOutputTokens');
    $form['advanced_settings']['tokens']['maxInputTokens']['#default_value'] = $config->get('maxInputTokens');
    $form['advanced_settings']['context']['contextSize']['#default_value'] = $config->get('contextSize');
    $form['advanced_settings']['context']['editorContextRatio']['#default_value'] = $config->get('editorContextRatio') ?: 0.3;

    $form['performance_settings']['timeOutDuration']['#default_value'] = $config->get('timeOutDuration') ?: 45000;
    $form['performance_settings']['retryAttempts']['#default_value'] = $config->get('retryAttempts') ?: 1;

    $form['behavior_settings']['debugMode']['#default_value'] = $config->get('debugMode') ? '1' : '0';
    $form['behavior_settings']['streamContent']['#default_value'] = $config->get('streamContent') ? '1' : '0';
    $form['behavior_settings']['showErrorDuration']['#default_value'] = $config->get('showErrorDuration') ?: 5000;

    $form['moderation_settings']['enable']['#default_value'] = $config->get('moderation.enable');
    $form['moderation_settings']['key']['#default_value'] = $config->get('moderation.key');
    $form['moderation_settings']['disableFlags']['#default_value'] = $config->get('moderation.disableFlags') ?: [];

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

    $promptSettings = $this->processPromptSettings($values['promptSettings'] ?? []);
    $config->set('promptSettings', $promptSettings);
    $config->save();
    
    parent::submitForm($form, $form_state);
  }

}
