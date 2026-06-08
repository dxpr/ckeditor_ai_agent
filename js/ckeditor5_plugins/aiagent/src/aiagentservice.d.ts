import type { Editor } from 'ckeditor5/src/core.js';
export default class AiAgentService {
    private editor;
    private aiEngine;
    private aiModel;
    private apiKey;
    private endpointUrl;
    private temperature;
    private timeOutDuration;
    private maxTokens;
    private retryAttempts;
    private streamContent;
    private stopSequences;
    private aiAgentFeatureLockId;
    private promptHelper;
    private htmlParser;
    private providers?;
    private creditsUrl?;
    private isInlineInsertion;
    private abortGeneration;
    private moderationKey;
    private moderationEnable;
    private disableFlags;
    private stream;
    private writesPerSecond;
    private processContentHelper;
    private readonly FILTERED_STRINGS;
    private getOutputMetrics;
    /**
     * Initializes the AiAgentService with the provided editor and configuration settings.
     *
     * @param editor - The CKEditor instance to be used with the AI assist service.
     */
    constructor(editor: Editor);
    /**
     * Handles the slash command input from the user, processes it, and interacts with the AI model.
     *
     * @returns A promise that resolves when the command has been processed.
     */
    handleSlashCommand(command?: string): Promise<void>;
    /**
     * Fetches and processes the GPT response based on the provided prompt and parent element.
     *
     * @param prompt - The prompt to send to the GPT model.
     * @param parent - The parent element in the editor where the response will be inserted.
     * @param retries - The number of retry attempts for the API call (default is the configured retry attempts).
     * @returns A promise that resolves when the response has been processed.
     */
    private fetchAndProcessGptResponse;
    private handleStreamingResponse;
    private handleNonStreamingResponse;
    /**
     * Creates and configures a cancel generation button with keyboard shortcut support.
     *
     * @param blockID - Unique identifier for the AI generation block
     * @param controller - AbortController to cancel the ongoing AI generation
     * @private
     */
    private cancelGenerationButton;
    /**
     * Generates a stream of messages from the specified language model (LLM) based on the provided input thread.
     * This method handles the streaming of responses, yielding each message as it is received.
     *
     * @param llm - The language model instance used for generating responses.
     * @param model - The identifier of the model to be used for generation.
     * @param thread - An array of messages that form the context for the generation.
     * @param opts - Options for the LLM completion, such as max tokens and temperature.
     * @returns An async generator that yields messages from the LLM as they are received.
     *
     * @throws Will throw an error if the streaming process fails or if the model is invalid.
     */
    private generate;
    private escapeHtml;
    private getErrorNotification;
}
