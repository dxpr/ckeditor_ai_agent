import { Plugin, type Editor } from 'ckeditor5/src/core.js';
import { Widget } from 'ckeditor5/src/widget.js';
export default class AiAgentUI extends Plugin {
    readonly PLACEHOLDER_TEXT_ID = "slash-placeholder";
    readonly GPT_RESPONSE_LOADER_ID = "gpt-response-loader";
    readonly GPT_RESPONSE_ERROR_ID = "gpt-error";
    private showErrorDuration;
    private readonly abortController;
    private readonly pendingTimeouts;
    private errorTooltipElement;
    constructor(editor: Editor);
    static get pluginName(): "AiAgentUI";
    static get requires(): readonly [typeof Widget];
    /**
     * Creates a tracked timeout that will be automatically cleared on destroy.
     */
    private setTimeout;
    /**
     * Clears all pending timeouts.
     */
    private clearAllTimeouts;
    /**
     * Initializes the AI Agent UI plugin, setting up UI components and event listeners.
     * This method is called when the plugin is loaded.
     */
    init(): void;
    /**
     * Initializes UI components such as placeholders, loaders, and buttons for the editor.
     */
    private initializeUIComponents;
    /**
     * Initializes the UI language settings based on the editor's locale.
     * Displays an error tooltip if the current language is unsupported.
     */
    private initializeUILanguage;
    /**
     * Attaches event listeners to the editor for handling user interactions and content changes.
     */
    private attachListener;
    /**
     * Removes empty inline-slash elements from the document.
     */
    private removeEmptyInlineSlashElements;
    /**
     * Applies the placeholder to the current line in the editor if it is empty.
     * Hides the placeholder if the line is not empty.
     */
    applyPlaceholderToCurrentLine(): void;
    /**
     * Retrieves the DOM rectangle of a given model element.
     *
     * @param element - The model element for which to get the DOM rectangle.
     * @returns The position of the element relative to the editor container, or null if not found.
     */
    private getRectDomOfGivenModelElement;
    /**
     * Adds a placeholder element to the document body for user interaction.
     */
    private addPlaceholder;
    /**
     * Shows the placeholder at the specified position.
     *
     * @param rect - The DOMRect object defining the position to show the placeholder.
     */
    private showPlaceHolder;
    /**
     * Hides the placeholder element from the document.
     */
    private hidePlaceHolder;
    /**
     * Adds a loader element to the document body for indicating processing.
     */
    private addLoader;
    /**
     * Shows the loader at the current cursor position.
     *
     * @param editor - The editor instance.
     */
    showLoader(editor: Editor): void;
    /**
     * Hides the loader element from the document.
     */
    hideLoader(editor: Editor): void;
    /**
     * Adds an error tooltip element to the document body for displaying error messages.
     */
    private addGptErrorToolTip;
    /**
     * Displays an error tooltip with the specified message.
     *
     * @param message - The error message to display in the tooltip.
     * @param options - Optional configuration for the tooltip.
     */
    showGptErrorToolTip(message: string, options?: {
        type?: 'error' | 'warning';
        html?: boolean;
        duration?: number;
    }): void;
    /**
     * Displays a warning notification for blocked URLs.
     *
     * @param blockedUrls - Array of blocked URL strings.
     */
    showBlockedUrlsWarning(blockedUrls: string[]): void;
    /**
     * Escapes HTML special characters to prevent XSS.
     */
    private escapeHtml;
    /**
     * Hides the error tooltip element from the document.
     */
    private hideGptErrorToolTip;
    /**
     * Cleans up resources when the plugin is destroyed.
     * Removes event listeners, clears timeouts, and removes created DOM elements.
     */
    destroy(): void;
}
