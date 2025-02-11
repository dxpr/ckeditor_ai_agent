import type { Editor } from 'ckeditor5/src/core.js';
export interface AttributeConfig {
    /**
     * Whether to preserve all values for this attribute
     */
    preserveAll?: boolean;
    /**
     * List of allowed selectors. Can include:
     * - Simple class names: '.btn'
     * - Complex selectors: '[class^="col-"]'
     * - Multiple patterns: '[class*="col-"],[class*="cols-"]'
     */
    allowedSelectors?: Array<string>;
}
export interface HtmlCleanupConfig {
    /**
     * Map of attribute names to their configuration
     */
    attributes: Record<string, AttributeConfig>;
    /**
     * CSS selectors for elements that should be removed before cleanup
     */
    blacklist: Array<string>;
}
export interface HtmlCleanupOptions {
    /**
     * Configuration for HTML cleanup
     */
    config: HtmlCleanupConfig;
    /**
     * CKEditor instance for extensibility
     */
    editor: Editor;
}
/**
 * Default configuration that can be extended
 */
export declare const DEFAULT_HTML_CLEANUP_CONFIG: HtmlCleanupConfig;
