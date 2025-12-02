import type { Editor } from 'ckeditor5/src/core.js';
import type { ModelElement as Element } from 'ckeditor5/src/engine.js';
import { type AiFilterConfig } from './ai-output-filter.js';
export declare class ProcessContentHelper {
    private editor;
    private filterConfig;
    private readonly FILTERED_STRINGS;
    constructor(editor: Editor, filterConfig?: AiFilterConfig);
    /**
     * Updates the content of an AI-generated block in the editor.
     *
     * @param newHtml - The new HTML content to insert
     * @param blockID - The unique identifier of the AI block to update
     * @returns Promise that resolves when the update is complete
     * @private
     */
    updateContent(newHtml: string, blockID: string): Promise<void>;
    /**
     * Recursively retrieves all child elements of a given view element that match the specified block ID.
     *
     * @param viewElement - The parent view element from which to retrieve children.
     * @param blockID - The unique identifier of the AI block to search for.
     * @returns An array of matching child elements.
     */
    private getViewChildrens;
    /**
     * Handles cleanup after AI generation is completed or cancelled.
     * Removes the cancel button from the UI and cleans up the temporary AI tag from editor content.
     *
     * @param blockID - Unique identifier for the AI generation block to be cleaned up
     * @private
     */
    processCompleted(blockID: string): void;
    /**
     * Handles the undo and redo commands for the editor.
     *
     * This function adds event listeners to the undo and redo commands.
     * If the editor's data contains any AI tags, executing the undo or redo command
     * will trigger the respective command, allowing for proper management of AI-generated content.
     *
     * @returns void
     */
    undoRedoHandler(): void;
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
    insertAiTag(blockID: string): Promise<void>;
    /**
     * Clears the content of the specified parent element in the editor.
     *
     * @param parent - The parent element whose content will be cleared.
     */
    clearParentContent(parent: Element, command: boolean): void;
    /**
     * Creates and displays an animated status message in the editor.
     * Replaces existing content in the target element with a loading animation and status text.
     *
     * @param status - The status message to display
     * @param blockID - Unique identifier for the target block element
     * @returns A Promise that resolves when the animation is set up
     *
     * @example
     * await processContentHelper.animatedStatusMessages('Processing', 'block-123');
     *
     * @remarks
     * - Looks for elements with either `${blockID}-inline` or `${blockID}` suffix
     * - Creates an 'ai-animated-status' element with 'ck-loading-shimmer' class
     * - Appends ellipsis to the status message
     * - Changes are not undoable in the editor history
     */
    animatedStatusMessages(status: string, blockID: string): Promise<void>;
}
