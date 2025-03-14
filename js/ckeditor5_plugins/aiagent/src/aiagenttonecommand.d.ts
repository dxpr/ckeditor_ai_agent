import { Command, type Editor } from 'ckeditor5/src/core.js';
export default class AiAgentToneCommand extends Command {
    private readonly STORAGE_PREFIX;
    private readonly STORAGE_KEY;
    private availableTones;
    /**
     * @inheritDoc
     */
    constructor(editor: Editor);
    /**
     * Executes the AI agent tone command, setting the tone value to be used in prompts.
     * When a new tone is selected, it completely replaces any previous tone setting
     * and persists the selection to localStorage.
     *
     * @param options - An object containing the tone value to set.
     */
    execute({ value }: {
        value: string;
    }): Promise<void>;
    /**
     * Saves the tone selection label to localStorage with the plugin's namespace.
     * Only the label is stored, not the full tone description, as descriptions may change.
     *
     * @param toneLabel - The label of the selected tone to save.
     */
    private saveToneSelection;
    /**
     * Loads the tone selection from localStorage.
     * Retrieves the stored label and finds the corresponding tone description
     * from the current configuration.
     *
     * @returns The current tone description string or null if not found or invalid.
     */
    private loadToneSelection;
    /**
     * Gets the default tones for validation purposes.
     * This is a simplified version of getDefaultAiAgentToneDropdownMenu.
     *
     * @returns An array of default tone options.
     */
    private _getDefaultTones;
}
