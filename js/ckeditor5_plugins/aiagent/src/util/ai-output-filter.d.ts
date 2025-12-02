/**
 * AI Output Security Filter
 *
 * Mitigates prompt injection attacks that attempt to exfiltrate data via
 * malicious URLs in AI-generated content (CVE-2025-32711 / EchoLeak style attacks).
 *
 * Filters:
 * - Images: <img> tags and Markdown ![alt](url) syntax including reference-style (enabled by default)
 * - Links: <a> tags and Markdown [text](url) syntax including reference-style (enabled by default)
 * - Plain text URLs: Bare http(s):// URLs in text content (replaced with EXTERNAL_URL_REDACTED)
 * - Iframes: All <iframe> tags are unconditionally removed (always enabled)
 * - Dangerous elements: <object>, <embed>, <applet>, SVG <image> (always enabled)
 *
 * @see https://nvd.nist.gov/vuln/detail/CVE-2025-32711
 */
export interface AiFilterConfig {
    /**
     * Allowed domains for images. Supports wildcards (*.example.com).
     * Default: ['promptahuman.com']. Use [] to block all, ['*'] to allow all.
     */
    allowedImageDomains?: string[];
    /**
     * Allowed domains for links. Supports wildcards (*.example.com).
     * Default: [] (blocks all). Use ['*'] to allow all external links.
     */
    allowedLinkDomains?: string[];
    /** Callback to notify about blocked URLs. Called with blocked URL info after filtering. */
    onUrlBlocked?: (blockedUrls: BlockedUrlInfo) => void;
}
export interface BlockedUrlInfo {
    images: string[];
    links: string[];
}
/**
 * Main filter function for AI-generated content.
 * Automatically detects HTML vs Markdown content.
 * Calls onUrlBlocked callback if any URLs were blocked.
 */
export declare const filterAiImages: (content: string, config?: AiFilterConfig) => string;
