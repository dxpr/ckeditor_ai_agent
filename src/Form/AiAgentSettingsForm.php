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

    // Set default values from config.
    $form['basic_settings']['apiKey']['#default_value'] = $config->get('apiKey');
    $form['basic_settings']['model']['#default_value'] = $config->get('model');
    $form['basic_settings']['ollamaModel']['#default_value'] = $config->get('ollamaModel');
    $form['basic_settings']['endpointUrl']['#default_value'] = $config->get('endpointUrl');
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
    
    // Helper function to flatten array with dot notation
    $flatten = function($array, $prefix = '') use (&$flatten) {
      $result = [];
      foreach ($array as $key => $value) {
        // Skip Drupal form system keys
        if (in_array($key, ['form_build_id', 'form_token', 'form_id', 'op', 'actions'])) {
          continue;
        }
        
        // Handle nested arrays (except promptSettings which stays nested)
        if (is_array($value) && $key !== 'promptSettings') {
          $result = array_merge($result, $flatten($value, $key . '.'));
        } else {
          // Convert boolean-like values
          if (is_string($value) && ($value === '0' || $value === '1')) {
            $value = (bool) $value;
          }
          $result[$key] = $value;
        }
      }
      return $result;
    };

    // Flatten form values
    $flat_values = $flatten($values);
    
    // Remove section prefixes from keys
    foreach ($flat_values as $key => $value) {
      $clean_key = str_contains($key, '.') ? substr($key, strpos($key, '.') + 1) : $key;
      $config->set($clean_key, $value);
    }

    // Handle prompt settings separately as they maintain their structure
    if (isset($values['promptSettings'])) {
      $config->set('promptSettings', $this->processPromptSettings($values['promptSettings']));
    }

    // Handle tone of voice taxonomy settings
    if (isset($values['tone_of_voice'])) {
      $tone_settings = $values['tone_of_voice'];
      
      // Save the vocabulary reference if taxonomy tones are enabled
      if (!empty($tone_settings['enable_taxonomy_tones']) && !empty($tone_settings['tone_of_voice_vocabulary'])) {
        $config->set('toneOfVoiceVocabulary', $tone_settings['tone_of_voice_vocabulary']);
      } else {
        $config->set('toneOfVoiceVocabulary', '');
      }
    }
    
    // Handle commands taxonomy settings
    if (isset($values['commands'])) {
      $command_settings = $values['commands'];
      
      // Save the vocabulary reference if taxonomy commands are enabled
      if (!empty($command_settings['enable_taxonomy_commands']) && !empty($command_settings['commands_vocabulary'])) {
        $config->set('commandsVocabulary', $command_settings['commands_vocabulary']);
      } else {
        $config->set('commandsVocabulary', '');
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
