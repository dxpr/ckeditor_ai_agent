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
    private saveToneSelection;
    private loadToneSelection;
}
