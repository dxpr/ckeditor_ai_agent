<?php

namespace Drupal\ckeditor_ai_agent\Hook;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
/**
 * Hook implementations for ckeditor_ai_agent.
 */
class CkeditorAiAgentHooks
{
    use StringTranslationTrait;
    /**
     * Implements hook_ckeditor5_plugin_info_alter().
     *
     * @phpstan-param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition[] $plugin_definitions
     */
    #[Hook('ckeditor5_plugin_info_alter')]
    public function ckeditor5PluginInfoAlter(array &$plugin_definitions): void
    {
        if (isset($plugin_definitions['ckeditor_ai_agent_ai_agent'])) {
            /** @var \Drupal\ckeditor_ai_agent\AiAgentConfigurationManager $config_manager */
            $config_manager = \Drupal::service('ckeditor_ai_agent.configuration_manager');
            /** @var \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $definition */
            $definition = $plugin_definitions['ckeditor_ai_agent_ai_agent'];
            $plugin_config = $definition->toArray();
            // Get existing config or initialize empty array.
            $config = $plugin_config['ckeditor5']['config'] ?? [
            ];
            // Get the current editor entity from the route.
            $route_match = \Drupal::routeMatch();
            $editor = $route_match->getParameter('editor');
            // Get our configuration.
            $our_config = $config_manager->getCKEditorConfig($editor);
            // Merge with our configuration.
            $config = array_merge($config, $our_config);
            // Update the plugin configuration.
            $plugin_config['ckeditor5']['config'] = $config;
            // Create new definition with updated config.
            $plugin_definitions['ckeditor_ai_agent_ai_agent'] = new \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition($plugin_config);
        }
    }
    /**
     * Implements hook_ai_brand_voice_alter().
     */
    #[Hook('ai_brand_voice_alter')]
    public function aiBrandVoiceAlter(string &$brand_voice)
    {
        $config = \Drupal::config('ckeditor_ai_agent.settings');
        $tone_override = $config->get('promptSettings.overrides.tone');
        $tone_additions = $config->get('promptSettings.additions.tone');
        if (!empty($tone_override)) {
            $brand_voice = $tone_override;
            if (!empty($tone_additions)) {
                $brand_voice .= "\n\n" . $tone_additions;
            }
        }
    }
    /**
     * Implements hook_help().
     */
    #[Hook('help')]
    public function help($route_name, \Drupal\Core\Routing\RouteMatchInterface $route_match)
    {
        switch ($route_name) {
            case 'help.page.ckeditor_ai_agent':
                $output = '';
                $output .= '<h3>' . $this->t('About') . '</h3>';
                $output .= '<p>' . $this->t('The CKEditor AI Agent module adds AI-powered content generation capabilities to CKEditor 5.') . '</p>';
                return $output;
        }
    }
    /**
     * Implements hook_page_attachments_alter().
     */
    #[Hook('page_attachments_alter')]
    public function pageAttachmentsAlter(array &$attachments)
    {
        // This implementation is no longer needed as we're handling tones directly
        // in the AiAgent plugin.
    }
}
