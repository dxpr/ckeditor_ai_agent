import type { Editor } from 'ckeditor5/src/core.js';
/**
 * Gets the allowed HTML tags from the editor schema and GeneralHtmlSupport configuration.
 *
 * @param editor - The CKEditor instance
 * @returns Array of allowed HTML tag names
 */
export declare function getAllowedHtmlTags(editor: Editor): Array<string>;
/**
 * Gets the allowed HTML classes from the GeneralHtmlSupport configuration.
 *
 * @param editor - The CKEditor instance
 * @returns Array of allowed HTML class names
 */
export declare function getAllowedHtmlClasses(editor: Editor): Array<string>;
