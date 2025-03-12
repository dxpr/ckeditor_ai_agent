<?php

namespace Drupal\ckeditor_ai_agent;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\editor\Entity\Editor;
use Drupal\ckeditor_ai_agent\Service\AiAgentKeyService;

/**
 * Manages configuration for the CKEditor AI Agent plugin.
 */
class AiAgentConfigurationManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The key service.
   *
   * @var \Drupal\ckeditor_ai_agent\Service\AiAgentKeyService
   */
  protected $keyService;

  /**
   * Constructs a new AiAgentConfigurationManager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\ckeditor_ai_agent\Service\AiAgentKeyService $key_service
   *   The key service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AiAgentKeyService $key_service
  ) {
    $this->configFactory = $config_factory;
    $this->keyService = $key_service;
  }

  /**
   * Gets the configuration.
   *
   * @return array<string, mixed>
   *   The configuration array.
   */
  public function getConfiguration(): array {
    $config = $this->configFactory->get('ckeditor_ai_agent.settings');
    $result = [];

    // Get basic settings
    $result['apiKey'] = $this->keyService->getApiKey();
    
    // Handle engine/model
    $model = $config->get('model');
    if ($model && str_contains($model, ':')) {
      [$engine, $model_name] = explode(':', $model, 2);
      $result['engine'] = $engine;
      if ($engine === 'ollama') {
        $result['model'] = $config->get('ollamaModel') ?: '';
      } else {
        $result['model'] = $model_name;
      }
    } else {
      // Fallback for legacy configurations
      $result['engine'] = 'openai';
      $result['model'] = $model ?: 'gpt-4o';
    }

    // Get other settings
    $result['endpointUrl'] = $config->get('endpointUrl');
    $result['contentScope'] = $config->get('contentScope');
    $result['temperature'] = $config->get('temperature');
    $result['maxOutputTokens'] = $config->get('maxOutputTokens');
    $result['maxInputTokens'] = $config->get('maxInputTokens');
    $result['contextSize'] = $config->get('contextSize');
    $result['editorContextRatio'] = $config->get('editorContextRatio');
    $result['timeOutDuration'] = $config->get('timeOutDuration');
    $result['retryAttempts'] = $config->get('retryAttempts');
    $result['debugMode'] = $config->get('debugMode');
    $result['streamContent'] = $config->get('streamContent');
    $result['showErrorDuration'] = $config->get('showErrorDuration');
    $result['moderationEnable'] = $config->get('moderationEnable');
    $result['moderationKey'] = $config->get('moderationKey');
    $result['promptSettings'] = $config->get('promptSettings') ?: [];
    $result['ollamaModel'] = $config->get('ollamaModel');

    return $result;
  }

  /**
   * Gets the CKEditor configuration.
   *
   * @param \Drupal\editor\Entity\Editor|null $editor
   *   The editor entity.
   *
   * @return array<string, array<string, mixed>>
   *   The CKEditor configuration.
   */
  public function getCkEditorConfig(?Editor $editor = NULL): array {
    $global_config = $this->configFactory->get('ckeditor_ai_agent.settings');

    // Structure the config to match the aiAgent JS configuration.
    $config = [
      'aiAgent' => [
        'apiKey' => $editor ? $this->keyService->getApiKey($editor->id()) : $this->keyService->getApiKey(),
        'model' => $global_config->get('model'),
        'ollamaModel' => $global_config->get('ollamaModel'),
        'endpointUrl' => $global_config->get('endpointUrl'),
        'contentScope' => $global_config->get('contentScope'),
        'temperature' => $global_config->get('temperature'),
        'maxOutputTokens' => $global_config->get('maxOutputTokens'),
        'maxInputTokens' => $global_config->get('maxInputTokens'),
        'contextSize' => $global_config->get('contextSize'),
        'editorContextRatio' => $global_config->get('editorContextRatio'),
        'timeOutDuration' => $global_config->get('timeOutDuration'),
        'retryAttempts' => $global_config->get('retryAttempts'),
        'debugMode' => $global_config->get('debugMode'),
        'showErrorDuration' => $global_config->get('showErrorDuration'),
        'moderationEnable' => $global_config->get('moderationEnable'),
        'moderationKey' => $global_config->get('moderationKey'),
        'promptSettings' => [
          'overrides' => [],
          'additions' => [],
        ],
      ],
    ];

    // Properly populate prompt settings from configuration
    foreach (['overrides', 'additions'] as $type) {
      $settings = $global_config->get("promptSettings.$type");
      if (!empty($settings) && is_array($settings)) {
        foreach ($settings as $component => $value) {
          $config['aiAgent']['promptSettings'][$type][$component] = $value;
        }
      }
    }

    // Add taxonomy-based tones of voice if configured
    $tone_vocabulary = $global_config->get('toneOfVoiceVocabulary');
    
    if (!empty($tone_vocabulary)) {
      try {
        // Load the terms from the vocabulary, sorted by weight
        $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
        $query = $term_storage->getQuery()
          ->condition('vid', $tone_vocabulary)
          ->sort('weight')
          ->accessCheck(FALSE);
        $tids = $query->execute();
        
        if (!empty($tids)) {
          $terms = $term_storage->loadMultiple($tids);
          $tones_dropdown = [];
          $first_term = NULL;
          
          // Add each taxonomy term as a tone option
          foreach ($terms as $term) {
            $description = $term->getDescription();
            // Only add terms that have a description (command)
            if (!empty($description)) {
              $tone_item = [
                'title' => $term->label(),
                'command' => $description,
              ];
              
              $tones_dropdown[] = $tone_item;
              
              // Keep track of the first valid term (lowest weight) to use as default
              if ($first_term === NULL) {
                $first_term = $tone_item;
              }
            }
          }
          
          // Only add the tones to the configuration if we have valid tones
          if (!empty($tones_dropdown)) {
            // Set the tones dropdown
            $config['aiAgent']['tonesDropdown'] = $tones_dropdown;
            
            // Set the first term (lowest weight) as the default tone
            if ($first_term !== NULL) {
              $config['aiAgent']['defaultTone'] = $first_term;
              
              // Also set the tone in the prompt settings
              if (!isset($config['aiAgent']['promptSettings']['overrides'])) {
                $config['aiAgent']['promptSettings']['overrides'] = [];
              }
              $config['aiAgent']['promptSettings']['overrides']['tone'] = $first_term['command'];
            }
          }
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('ckeditor_ai_agent')->error('Error loading tone of voice taxonomy terms: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }
    
    return $config;
  }

}
