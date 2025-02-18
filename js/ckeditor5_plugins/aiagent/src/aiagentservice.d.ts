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
    private isInlineInsertion;
    private abortGeneration;
    private moderationKey;
    private moderationEnable;
    private disableFlags;
    private stream;
    private writesPerSecond;
    private readonly STORAGE_PREFIX;
    private readonly FILTERED_STRINGS;
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
     * Moderates the input content using OpenAI's moderation API to check for inappropriate content.
     *
     * @param input - The text content to be moderated
     * @returns A promise that resolves to:
     * - `true` if content is acceptable or if moderation fails (fail-open)
     * - `false` if content is flagged as inappropriate
     *
     * @throws Shows user-friendly error messages via aiAgentContext for:
     * - Flagged content ("Cannot process your query...")
     * - API errors ("Error in content moderation")
     */
    private moderateContent;
    /**
     * Fetches and processes the GPT response based on the provided prompt and parent element.
     *
     * @param prompt - The prompt to send to the GPT model.
     * @param parent - The parent element in the editor where the response will be inserted.
     * @param retries - The number of retry attempts for the API call (default is the configured retry attempts).
     * @returns A promise that resolves when the response has been processed.
     */
    private fetchAndProcessGptResponse;
    /**
     * Checks if the specified AI model exists for the given engine.
     * If the models are not cached, it fetches them from the API and caches them.
     *
     * @param engine - The AI engine to check the model against.
     * @param model - The model identifier to verify.
     * @param apiKey - Optional API key for authentication with the AI engine.
     * @returns A promise that resolves to an object containing:
     * - `success`: A boolean indicating whether the model exists.
     * - `error`: An optional string containing error details if the model is invalid.
     *
     * @throws Will throw an error if unable to load models from the API.
     */
    private checkModel;
    /**
     * Retrieves cached models from local storage based on the provided key.
     * If the cached models are expired, they are removed from local storage.
     *
     * @param engine - The key used to access the cached models in local storage.
     * @returns An array of model identifiers retrieved from local storage, or an empty array if no valid models are found.
     */
    private getCachedModels;
    private cacheModels;
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
     * Handles cleanup after AI generation is completed or cancelled.
     * Removes the cancel button from the UI and cleans up the temporary AI tag from editor content.
     *
     * @param blockID - Unique identifier for the AI generation block to be cleaned up
     * @private
     */
    private processCompleted;
    /**
     * Recursively retrieves all child elements of a given view element that match the specified block ID.
     *
     * @param viewElement - The parent view element from which to retrieve children.
     * @param blockID - The unique identifier of the AI block to search for.
     * @returns An array of matching child elements.
     */
    private getViewChildrens;
    private animatedStatusMessages;
    /**
     * Updates the content of an AI-generated block in the editor.
     *
     * @param newHtml - The new HTML content to insert
     * @param blockID - The unique identifier of the AI block to update
     * @returns Promise that resolves when the update is complete
     * @private
     */
    private updateContent;
    /**
     * Processes the provided content and inserts it into the specified parent element.
     * Depending on the feature flag, it either uses a simple HTML insertion method
     * or processes the content as HTML.
     *
     * @param content - The content to be processed and inserted.
     * @param parent - The parent element in the editor where the content will be inserted.
     */
    private processContent;
    /**
     * Processes the provided HTML string and inserts its content into the editor.
     * It creates a temporary div to parse the HTML and handles different types of
     * elements (lists, tables, headings, etc.) accordingly.
     *
     * @param html - The HTML string to be processed and inserted into the editor.
     */
    private proceedHtmlResponse;
    /**
     * Clears the content of the specified parent element in the editor.
     *
     * @param parent - The parent element whose content will be cleared.
     */
    private clearParentContent;
    /**
     * Generates a GPT prompt based on the user's input and the current context in the editor.
     * This method processes the input prompt, extracts any URLs, and formats the final prompt
     * to be sent to the GPT model. It also handles the case where the editor is empty.
     *
     * @param prompt - The user's input prompt, typically starting with a slash.
     * @param promptContainerText - Optional text from the container that may provide additional context.
     * @returns A promise that resolves to the generated GPT prompt string or null if an error occurs.
    */
    private generateGptPromptBasedOnUserPrompt;
    /**
     * Handles the undo and redo commands for the editor.
     *
     * This function adds event listeners to the undo and redo commands.
     * If the editor's data contains any AI tags, executing the undo or redo command
     * will trigger the respective command, allowing for proper management of AI-generated content.
     *
     * @returns void
     */
    private undoRedoHandler;
    /**
     * Inserts AI tags into the editor at the current selection position.
     *
     * This function creates two AI tags: one inline tag and one block tag.
     * The inline tag is inserted immediately after the current selection,
     * while the block tag is inserted after the parent element of the selection.
     *
     * @param blockID - The unique identifier for the AI block, used to set the ID of the tags.
     *
     * @returns A Promise that resolves when the tags have been successfully inserted.
     */
    private insertAiTag;
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
}
