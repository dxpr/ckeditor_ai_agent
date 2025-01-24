<?php

namespace Drupal\ckeditor_ai_agent\Form;

/**
 * Provides common configuration setting methods.
 */
trait ConfigSetterTrait {

  /**
   * Sets configuration values with proper type casting.
   *
   * @param array<string, mixed> $values
   *   Form values to process.
   * @param array<string, mixed> $mapping
   *   Mapping of form keys to config keys with type information.
   *
   * @return array<string, mixed>
   *   Processed configuration array.
   */
  protected function processConfigValues(array $values, array $mapping): array {
    $config = [];

    foreach ($mapping as $form_key => $settings) {
      $value = $this->getNestedValue($values, $form_key);

      if ($value !== NULL) {
        $config_key = $settings['config_key'] ?? basename($form_key);
        $config[$config_key] = $this->castValue($value, $settings['type']);
      }
    }

    return $config;
  }

  /**
   * Gets a nested array value using dot notation.
   *
   * @param array<string, mixed> $array
   *   Array of form values.
   * @param string $key
   *   Form key used to extract the nested value.
   */
  protected function getNestedValue(array $array, string $key): mixed {
    $keys = explode('.', $key);
    $value = $array;

    foreach ($keys as $key) {
      if (!is_array($value) || !isset($value[$key])) {
        return NULL;
      }
      $value = $value[$key];
    }

    return $value;
  }

  /**
   * Casts a value to the specified type.
   */
  protected function castValue(mixed $value, string $type): mixed {
    if (empty($value) && $value !== '0' && $value !== 0) {
      return NULL;
    }

    switch ($type) {
      case 'int':
        return (int) $value;

      case 'float':
        return (float) $value;

      case 'bool':
        return (bool) $value;

      case 'array':
        return (array) $value;

      default:
        return (string) $value;
    }
  }

  /**
   * Processes prompt settings.
   *
   * @param array<string, mixed> $values
   *   Array of form values.
   *
   * @return array<string, mixed>
   *   An array of prompt configuration properties.
   */
  protected function processPromptSettings(array $values): array {
    $settings = [
      'overrides' => [],
      'additions' => [],
    ];

    // Load default components from JSON
    $module_path = \Drupal::service('extension.path.resolver')->getPath('module', 'ckeditor_ai_agent');
    $default_rules_path = $module_path . '/js/ckeditor5_plugins/aiagent/src/config/default-rules.json';
    $default_rules = file_exists($default_rules_path)
      ? json_decode(file_get_contents($default_rules_path), TRUE) ?: []
      : [];

    foreach (array_keys($default_rules) as $component) {
      $settings['overrides'][$component] = $values["override_$component"] ?? '';
      $settings['additions'][$component] = $values["additions_$component"] ?? '';
    }

    return $settings;
  }

}
