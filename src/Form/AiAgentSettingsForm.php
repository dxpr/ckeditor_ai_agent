<?php

namespace Drupal\ckeditor_ai_agent\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure CKEditor AI Agent settings.
 *
 * @phpstan-consistent-constructor
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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs a new AiAgentSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed configuration manager.
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extension_path_resolver
   *   The extension path resolver.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    ExtensionPathResolver $extension_path_resolver,
    EntityTypeManagerInterface $entity_type_manager,
    UrlGeneratorInterface $url_generator,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->extensionPathResolver = $extension_path_resolver;
    $this->entityTypeManager = $entity_type_manager;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('extension.path.resolver'),
      $container->get('entity_type.manager'),
      $container->get('url_generator')
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
    return $this->configFactory();
  }

  /**
   * {@inheritdoc}
   */
  protected function getMessenger(): MessengerInterface {
    return $this->messenger();
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

    // Set default values from config.
    $form['advanced_settings']['context']['contentScope']['#default_value'] = $config->get('contentScope');

    $form['advanced_settings']['temperature']['#default_value'] = $config->get('temperature');
    $form['advanced_settings']['tokens']['maxOutputTokens']['#default_value'] = $config->get('maxOutputTokens');
    $form['advanced_settings']['tokens']['maxInputTokens']['#default_value'] = $config->get('maxInputTokens');
    $form['advanced_settings']['context']['contextSize']['#default_value'] = $config->get('contextSize');
    $form['advanced_settings']['context']['editorContextRatio']['#default_value'] = $config->get('editorContextRatio') ?: 0.3;

    $form['performance_settings']['timeOutDuration']['#default_value'] = $config->get('timeOutDuration') ?: 120000;
    $form['performance_settings']['retryAttempts']['#default_value'] = $config->get('retryAttempts') ?: 1;

    $form['behavior_settings']['debugMode']['#default_value'] = $config->get('debugMode') ? '1' : '0';
    $form['behavior_settings']['streamContent']['#default_value'] = $config->get('streamContent') ? '1' : '0';
    $form['behavior_settings']['showErrorDuration']['#default_value'] = $config->get('showErrorDuration') ?: 5000;

    $form['moderation_settings']['moderationEnable']['#default_value'] = $config->get('moderationEnable');
    $form['moderation_settings']['moderationKey']['#default_value'] = $config->get('moderationKey');

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

    // Helper function to flatten array with dot notation.
    $flatten = function ($array, $prefix = '') use (&$flatten) {
      $result = [];
      foreach ($array as $key => $value) {
        // Skip Drupal form system keys.
        if (in_array($key, ['form_build_id', 'form_token', 'form_id', 'op', 'actions'])) {
          continue;
        }

        // Handle nested arrays (except promptSettings which stays nested)
        if (is_array($value) && $key !== 'promptSettings') {
          $result = array_merge($result, $flatten($value, $key . '.'));
        }
        else {
          // Convert boolean-like values.
          if (is_string($value) && ($value === '0' || $value === '1')) {
            $value = (bool) $value;
          }
          $result[$key] = $value;
        }
      }
      return $result;
    };

    // Flatten form values.
    $flat_values = $flatten($values);

    // Remove section prefixes from keys.
    foreach ($flat_values as $key => $value) {
      $clean_key = str_contains($key, '.') ? substr($key, strpos($key, '.') + 1) : $key;
      $config->set($clean_key, $value);
    }

    // Handle prompt settings separately as they maintain their structure.
    if (isset($values['promptSettings'])) {
      $config->set('promptSettings', $this->processPromptSettings($values['promptSettings']));
    }

    // Handle tone of voice taxonomy settings.
    if (isset($values['tone_of_voice'])) {
      $tone_settings = $values['tone_of_voice'];

      // Save the vocabulary reference if taxonomy tones are enabled.
      if (!empty($tone_settings['enable_taxonomy_tones']) && !empty($tone_settings['toneOfVoiceVocabulary'])) {
        $config->set('toneOfVoiceVocabulary', $tone_settings['toneOfVoiceVocabulary']);
      }
      else {
        $config->set('toneOfVoiceVocabulary', '');
      }
    }

    // Handle commands taxonomy settings.
    if (isset($values['commands'])) {
      $command_settings = $values['commands'];

      // Save the vocabulary reference if taxonomy commands are enabled.
      if (!empty($command_settings['enable_taxonomy_commands']) && !empty($command_settings['commandsVocabulary'])) {
        $config->set('commandsVocabulary', $command_settings['commandsVocabulary']);
      }
      else {
        $config->set('commandsVocabulary', '');
      }
    }

    // Handle AI output security settings.
    if (isset($values['security_settings'])) {
      $security_settings = $values['security_settings'];
      $config->set('aiOutputSecurity', [
        'allowedDomains' => $this->parseDomainsFromTextarea($security_settings['allowedDomains'] ?? ''),
      ]);
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
