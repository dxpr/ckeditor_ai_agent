import { type HtmlCleanupOptions } from '../types/html-cleanup.js';
/**
 * Service for cleaning up HTML content based on configurable rules
 */
export declare class HtmlCleanupService {
    private config;
    private editor;
    constructor(options: HtmlCleanupOptions);
    /**
     * Clean HTML content based on configuration
     *
     * @param html - HTML content to clean
     * @returns Cleaned HTML content
     */
    clean(html: string): string;
    /**
     * Remove elements matching blacklisted selectors
     *
     * @param node - Root node to process
     */
    private removeBlacklistedElements;
    /**
     * Recursively clean a DOM node and its children
     *
     * @param node - DOM node to clean
     */
    private cleanNode;
    /**
     * Deep merge two configurations
     *
     * @param defaultConfig - Default configuration
     * @param userConfig - User provided configuration
     * @returns Merged configuration
     */
    private mergeConfigs;
}
