import { Command, type Editor } from 'ckeditor5/src/core.js';
export default class AiAgentToneCommand extends Command {
    private readonly STORAGE_KEY;
    private availableTones;
    private debugMode;
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
     * Saves the selected tone to localStorage for future use.
     *
     * This method stores the specified tone under a unique key in localStorage,
     * allowing the application to remember the user's tone preference across sessions.
     * It also logs the saved value for debugging purposes if debug mode is enabled.
     *
     * @param toneKey - The toneKey string to be saved in localStorage.
     * @returns {void} This function does not return a value.
     *
     * @throws {Error} If localStorage is not available, a warning is logged to the console.
     */
    private saveToneSelection;
    /**
     * Loads the selected tone from localStorage.
     *
     * This method retrieves the tone string stored under a unique key in localStorage,
     * allowing the application to remember the user's tone preference across sessions.
     * If no tone is found, it returns null.
     *
     * @returns {string | null} The stored tone string if found, or null if no tone is stored.
     *
     * @throws {Error} If localStorage is not available, a warning is logged to the console.
     */
    private loadToneSelection;
}
