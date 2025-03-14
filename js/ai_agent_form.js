/**
 * @file
 * JavaScript behaviors for the CKEditor AI Agent form.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Behavior for the CKEditor AI Agent form.
   */
  Drupal.behaviors.ckeditorAiAgentForm = {
    attach: function (context, settings) {
      // Handle the tone of voice vocabulary field requirement
      const $toneCheckbox = $('input[name="tone_of_voice[enable_taxonomy_tones]"]', context);
      const $toneVocabulary = $('select[name="tone_of_voice[tone_of_voice_vocabulary]"]', context);

      // Set initial state
      this.updateToneVocabularyRequired($toneCheckbox, $toneVocabulary);

      // Update on change
      $toneCheckbox.once('ckeditor-ai-agent-form').on('change', function () {
        Drupal.behaviors.ckeditorAiAgentForm.updateToneVocabularyRequired($toneCheckbox, $toneVocabulary);
      });

      // Handle the commands vocabulary field requirement
      const $commandsCheckbox = $('input[name="commands[enable_taxonomy_commands]"]', context);
      const $commandsVocabulary = $('select[name="commands[commands_vocabulary]"]', context);

      // Set initial state
      this.updateCommandsVocabularyRequired($commandsCheckbox, $commandsVocabulary);

      // Update on change
      $commandsCheckbox.once('ckeditor-ai-agent-form').on('change', function () {
        Drupal.behaviors.ckeditorAiAgentForm.updateCommandsVocabularyRequired($commandsCheckbox, $commandsVocabulary);
      });
    },

    /**
     * Update the required attribute of the tone vocabulary field.
     */
    updateToneVocabularyRequired: function ($checkbox, $vocabulary) {
      if ($checkbox.is(':checked')) {
        $vocabulary.attr('required', 'required');
      } else {
        $vocabulary.removeAttr('required');
      }
    },

    /**
     * Update the required attribute of the commands vocabulary field.
     */
    updateCommandsVocabularyRequired: function ($checkbox, $vocabulary) {
      if ($checkbox.is(':checked')) {
        $vocabulary.attr('required', 'required');
      } else {
        $vocabulary.removeAttr('required');
      }
    }
  };

})(jQuery, Drupal); 