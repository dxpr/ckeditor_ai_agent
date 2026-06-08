/**
 * AI Output Security Filter
 *
 * Mitigates prompt injection attacks that attempt to exfiltrate data via
 * malicious URLs in AI-generated content (CVE-2025-32711 / EchoLeak style attacks).
 *
 * Uses UNIVERSAL URL detection - scans ALL content for URLs regardless of context.
 * No more whack-a-mole with specific tags/attributes.
 *
 * @see https://nvd.nist.gov/vuln/detail/CVE-2025-32711
 */
export interface AiFilterConfig {
    allowedDomains?: string[];
    onUrlBlocked?: (blockedUrls: string[]) => void;
}
/**
 * Extract unique domains from content (HTML or plain text).
 * Used to preserve URLs that were already in the user's content.
 */
export declare const extractDomainsFromContent: (content: string) => Set<string>;
/**
 * Set context domains from pre-existing content.
 * These domains will be allowed in addition to the configured allowedDomains.
 */
export declare const setContextDomains: (content: string) => void;
/**
 * Clear context domains after an AI operation completes.
 */
export declare const clearContextDomains: () => void;
export declare const filterAiImages: (content: string, config?: AiFilterConfig) => string;
