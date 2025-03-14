import type { Editor } from 'ckeditor5/src/core.js';
/**
 * Gets the allowed HTML tags from the editor schema and GeneralHtmlSupport configuration.
 *
 * @param editor - The CKEditor instance
 * @returns Array of allowed HTML tag names
 */
export declare function getAllowedHtmlTags(editor: Editor): Array<string>;
/**
 * Gets the allowed HTML classes from the GeneralHtmlSupport configuration and Style plugin.
 *
 * @param editor - The CKEditor instance
 * @returns Object containing allowed classes and a flag indicating if any element allows all classes
 */
export declare function getAllowedHtmlClasses(editor: Editor): {
    classes: Array<string>;
    allowsAllClasses: boolean;
};
/**
 * Gets the allowed HTML styles from the GeneralHtmlSupport configuration.
 *
 * @param editor - The CKEditor instance
 * @returns Object containing allowed styles and a flag indicating if any element allows all styles
 */
export declare function getAllowedHtmlStyles(editor: Editor): {
    styles: Array<string>;
    allowsAllStyles: boolean;
};
/**
 * Gets the allowed HTML attributes from the GeneralHtmlSupport configuration.
 *
 * @param editor - The CKEditor instance
 * @returns Object containing allowed attributes and a flag indicating if any element allows all attributes
 */
export declare function getAllowedHtmlAttributes(editor: Editor): {
    attributes: Array<string>;
    allowsAllAttributes: boolean;
};
