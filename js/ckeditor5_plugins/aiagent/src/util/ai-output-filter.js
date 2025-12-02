/**
 * AI Output Security Filter
 *
 * Mitigates prompt injection attacks that attempt to exfiltrate data via
 * malicious URLs in AI-generated content (CVE-2025-32711 / EchoLeak style attacks).
 *
 * Filters:
 * - Images: <img> tags and Markdown ![alt](url) syntax including reference-style (enabled by default)
 * - Links: <a> tags and Markdown [text](url) syntax including reference-style (enabled by default)
 * - Iframes: All <iframe> tags are unconditionally removed (always enabled)
 * - Dangerous elements: <object>, <embed>, <applet>, SVG <image> (always enabled)
 *
 * @see https://nvd.nist.gov/vuln/detail/CVE-2025-32711
 */
import { PLACEHOLDER_SERVICE_DOMAIN } from '../const.js';
// ============================================================================
// Constants
// ============================================================================
/**
 * Default allowed domains for external images.
 * Only allows the placeholder image service by default for maximum security.
 * Users can extend this list via allowedImageDomains config option.
 */
const DEFAULT_ALLOWED_IMAGE_DOMAINS = [
    PLACEHOLDER_SERVICE_DOMAIN
];
/**
 * Default allowed domains for external links.
 * Empty by default for maximum security - all external links are blocked.
 * Users can extend this list via allowedLinkDomains config option.
 */
const DEFAULT_ALLOWED_LINK_DOMAINS = [];
/** Placeholder image service URL for blocked images */
const PLACEHOLDER_IMAGE_URL = `https://${PLACEHOLDER_SERVICE_DOMAIN}/900x160@x2?prompt=`;
/** 1x1 gray pixel as base64 - used when external placeholder not allowed */
const GRAY_PIXEL_BASE64 = 'data:image/gif;base64,R0lGODlhAQABAIAAAMLCwgAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==';
/** Default dimensions for blocked image placeholder (matches promptahuman.com default) */
const BLOCKED_IMAGE_WIDTH = 900;
const BLOCKED_IMAGE_HEIGHT = 160;
/** URL prefixes that are always considered safe (no network request or same-origin) */
const SAFE_URL_PREFIXES = ['/', '../', './', '#', 'mailto:', 'tel:', 'data:'];
/** Wildcard prefix for domain matching */
const WILDCARD_PREFIX = '*.';
/** HTML elements that are always removed (can load external resources dangerously) */
const DANGEROUS_ELEMENTS_SELECTOR = 'iframe, object, embed, applet';
/** SVG image element selector */
const SVG_IMAGE_SELECTOR = 'svg image';
/** XLink namespace for SVG href attributes */
const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
/** Maximum filename length for placeholders */
const MAX_FILENAME_LENGTH = 50;
/** Default filename when extraction fails */
const DEFAULT_FILENAME = 'image';
// Regex patterns
const PATTERNS = {
    /** Matches [refname]: url or [refname]: url "title" */
    markdownReference: /^\[([^\]]+)\]:\s*(\S+)(?:\s+"[^"]*")?$/gm,
    /** Matches ![alt](url) or ![alt](url "title") */
    markdownInlineImage: /!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g,
    /** Matches ![alt][ref] or ![alt][] */
    markdownRefImage: /!\[([^\]]*)\]\[([^\]]*)\]/g,
    /** Matches [text](url) or [text](url "title") - with negative lookbehind to exclude images */
    markdownInlineLink: /(?<!!)\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g,
    /** Matches [text][ref] or [text][] - with negative lookbehind to exclude images */
    markdownRefLink: /(?<!!)\[([^\]]+)\]\[([^\]]*)\]/g,
    /** Matches reference definitions with trailing newlines */
    markdownRefDefinition: /^\[([^\]]+)\]:\s*(\S+)(?:\s+"[^"]*")?[\r\n]*/gm,
    /** Detects HTML content */
    htmlDetection: /<[^>]+>/,
    /** Escapes regex special characters */
    regexEscape: /[.*+?^${}()|[\]\\]/g
};
// ============================================================================
// Helper Functions
// ============================================================================
/**
 * Resolves partial config into complete config with defaults.
 */
const resolveConfig = (config) => ({
    allowedImageDomains: config?.allowedImageDomains ?? [...DEFAULT_ALLOWED_IMAGE_DOMAINS],
    allowedLinkDomains: config?.allowedLinkDomains ?? [...DEFAULT_ALLOWED_LINK_DOMAINS],
    onUrlBlocked: config?.onUrlBlocked
});
/**
 * Escapes special regex characters in a string.
 */
const escapeRegExp = (str) => str.replace(PATTERNS.regexEscape, '\\$&');
/**
 * Extracts a safe filename from a URL for placeholder display.
 */
const extractFilename = (url) => {
    try {
        const pathPart = url.split('?')[0].split('#')[0];
        const filename = pathPart.split('/').pop() || DEFAULT_FILENAME;
        return filename.substring(0, MAX_FILENAME_LENGTH);
    }
    catch {
        return DEFAULT_FILENAME;
    }
};
/**
 * Checks if a URL starts with any of the safe prefixes.
 */
const hasSafePrefix = (url) => SAFE_URL_PREFIXES.some(prefix => url.startsWith(prefix));
/**
 * Checks if a hostname matches an allowed domain pattern.
 */
const matchesDomainPattern = (hostname, pattern) => {
    if (pattern === hostname)
        return true;
    if (pattern.startsWith(WILDCARD_PREFIX)) {
        const baseDomain = pattern.substring(WILDCARD_PREFIX.length);
        return hostname.endsWith(`.${baseDomain}`) || hostname === baseDomain;
    }
    return false;
};
/**
 * Validates whether a URL is allowed based on the whitelist.
 * Use ['*'] to allow all external URLs.
 */
const isUrlAllowed = (url, allowedDomains) => {
    if (!url)
        return false;
    // ['*'] means allow all
    if (allowedDomains.includes('*'))
        return true;
    try {
        if (hasSafePrefix(url))
            return true;
        const urlObj = new URL(url, typeof window !== 'undefined' ? window.location.href : undefined);
        const { hostname } = urlObj;
        // Allow same-origin URLs
        if (typeof window !== 'undefined' && hostname === window.location.hostname) {
            return true;
        }
        return allowedDomains.some(pattern => matchesDomainPattern(hostname, pattern));
    }
    catch {
        return false; // Invalid URL - block it
    }
};
/**
 * Determines if an image URL should be blocked based on config.
 */
const shouldBlockImageUrl = (url, config) => !isUrlAllowed(url, config.allowedImageDomains);
/**
 * Determines if a link URL should be blocked based on config.
 */
const shouldBlockLinkUrl = (url, config) => !isUrlAllowed(url, config.allowedLinkDomains);
/**
 * Creates a blocked URL tracker for collecting blocked URLs during filtering.
 */
const createBlockedUrlTracker = () => ({
    images: [],
    links: []
});
/**
 * Records a blocked URL in the tracker.
 */
const recordBlockedUrl = (tracker, url, type) => {
    if (!url)
        return;
    const list = type === 'image' ? tracker.images : tracker.links;
    if (!list.includes(url)) {
        list.push(url);
    }
};
/**
 * Checks if the placeholder image service is allowed by the config.
 */
const isPlaceholderServiceAllowed = (config) => config.allowedImageDomains.some(domain => matchesDomainPattern(PLACEHOLDER_SERVICE_DOMAIN, domain));
/**
 * Gets the replacement image source.
 * Uses BASE64 gray pixel if promptahuman.com is not in the allowedImageDomains.
 * This ensures we don't violate the user's security config by replacing blocked images
 * with another external URL they haven't explicitly allowed.
 */
const getReplacementImageSrc = (originalUrl, config) => {
    if (!isPlaceholderServiceAllowed(config)) {
        return GRAY_PIXEL_BASE64;
    }
    return PLACEHOLDER_IMAGE_URL + encodeURIComponent(extractFilename(originalUrl));
};
/**
 * Parses reference-style definitions from Markdown content.
 */
const parseMarkdownReferences = (markdown) => {
    const refs = new Map();
    const pattern = new RegExp(PATTERNS.markdownReference.source, 'gm');
    let match;
    while ((match = pattern.exec(markdown)) !== null) {
        refs.set(match[1].toLowerCase(), match[2]);
    }
    return refs;
};
/**
 * Creates a regex pattern to find reference usage in markdown.
 */
const createRefUsagePattern = (refName, isImage) => {
    const escaped = escapeRegExp(refName);
    return isImage
        ? new RegExp(`!\\[[^\\]]*\\]\\[${escaped}\\]`, 'i')
        : new RegExp(`(?<!!)\\[[^\\]]+\\]\\[${escaped}\\]`, 'i');
};
// ============================================================================
// HTML Filtering
// ============================================================================
/**
 * Filters HTML content for dangerous elements, images, and links.
 */
const filterHtmlContent = (html, config, blockedUrls) => {
    const parser = typeof DOMParser !== 'undefined' ? new DOMParser() : null;
    if (!parser)
        return html;
    const doc = parser.parseFromString(html, 'text/html');
    // Always remove dangerous elements
    doc.querySelectorAll(DANGEROUS_ELEMENTS_SELECTOR).forEach(el => el.remove());
    // Filter images
    doc.querySelectorAll('img').forEach(img => {
        const src = img.getAttribute('src') || '';
        if (shouldBlockImageUrl(src, config)) {
            recordBlockedUrl(blockedUrls, src, 'image');
            const replacementSrc = getReplacementImageSrc(src, config);
            img.setAttribute('src', replacementSrc);
            img.removeAttribute('srcset');
            // Set visible dimensions when using the BASE64 placeholder
            if (replacementSrc === GRAY_PIXEL_BASE64) {
                img.setAttribute('width', String(BLOCKED_IMAGE_WIDTH));
                img.setAttribute('height', String(BLOCKED_IMAGE_HEIGHT));
            }
        }
    });
    // Filter SVG images
    doc.querySelectorAll(SVG_IMAGE_SELECTOR).forEach(svgImg => {
        const href = svgImg.getAttribute('href') || svgImg.getAttributeNS(XLINK_NAMESPACE, 'href') || '';
        if (shouldBlockImageUrl(href, config)) {
            recordBlockedUrl(blockedUrls, href, 'image');
            svgImg.remove();
        }
    });
    // Filter links
    doc.querySelectorAll('a').forEach(anchor => {
        const href = anchor.getAttribute('href') || '';
        if (href && shouldBlockLinkUrl(href, config)) {
            recordBlockedUrl(blockedUrls, href, 'link');
            anchor.setAttribute('href', '#');
        }
    });
    return doc.body.innerHTML;
};
// ============================================================================
// Markdown Filtering
// ============================================================================
/**
 * Filters inline markdown resources (images or links).
 */
const filterMarkdownInline = (content, pattern, config, isImage, blockedUrls) => {
    const regex = new RegExp(pattern.source, pattern.flags);
    const shouldBlock = isImage ? shouldBlockImageUrl : shouldBlockLinkUrl;
    return content.replace(regex, (match, textOrAlt, url) => {
        if (shouldBlock(url, config)) {
            recordBlockedUrl(blockedUrls, url, isImage ? 'image' : 'link');
            return isImage
                ? `![${textOrAlt}](${getReplacementImageSrc(url, config)})`
                : textOrAlt; // For links, return just the text
        }
        return match;
    });
};
/**
 * Filters reference-style markdown resources (images or links).
 */
const filterMarkdownReference = (content, pattern, references, config, isImage, blockedUrls) => {
    const regex = new RegExp(pattern.source, pattern.flags);
    const shouldBlock = isImage ? shouldBlockImageUrl : shouldBlockLinkUrl;
    return content.replace(regex, (match, textOrAlt, ref) => {
        const refKey = (ref || textOrAlt).toLowerCase();
        const url = references.get(refKey);
        if (url && shouldBlock(url, config)) {
            recordBlockedUrl(blockedUrls, url, isImage ? 'image' : 'link');
            return isImage
                ? `![${textOrAlt}](${getReplacementImageSrc(url, config)})`
                : textOrAlt; // For links, return just the text
        }
        return match;
    });
};
/**
 * Removes orphaned reference definitions for blocked URLs.
 */
const cleanupMarkdownReferences = (content, originalMarkdown, config) => {
    const regex = new RegExp(PATTERNS.markdownRefDefinition.source, 'gm');
    return content.replace(regex, (match, refName, url) => {
        // Check image references
        const imagePattern = createRefUsagePattern(refName, true);
        if (imagePattern.test(originalMarkdown) && shouldBlockImageUrl(url, config)) {
            return '';
        }
        // Check link references
        const linkPattern = createRefUsagePattern(refName, false);
        if (linkPattern.test(originalMarkdown) && shouldBlockLinkUrl(url, config)) {
            return '';
        }
        return match;
    });
};
/**
 * Filters Markdown content for images and links.
 */
const filterMarkdownContent = (markdown, config, blockedUrls) => {
    let filtered = markdown;
    const references = parseMarkdownReferences(markdown);
    // Filter images
    filtered = filterMarkdownInline(filtered, PATTERNS.markdownInlineImage, config, true, blockedUrls);
    filtered = filterMarkdownReference(filtered, PATTERNS.markdownRefImage, references, config, true, blockedUrls);
    // Filter links
    filtered = filterMarkdownInline(filtered, PATTERNS.markdownInlineLink, config, false, blockedUrls);
    filtered = filterMarkdownReference(filtered, PATTERNS.markdownRefLink, references, config, false, blockedUrls);
    // Clean up orphaned reference definitions
    filtered = cleanupMarkdownReferences(filtered, markdown, config);
    return filtered;
};
// ============================================================================
// Public API
// ============================================================================
/**
 * Main filter function for AI-generated content.
 * Automatically detects HTML vs Markdown content.
 * Calls onUrlBlocked callback if any URLs were blocked.
 */
export const filterAiImages = (content, config) => {
    if (!content)
        return content;
    const resolvedConfig = resolveConfig(config);
    const blockedUrls = createBlockedUrlTracker();
    const isHtml = PATTERNS.htmlDetection.test(content);
    const filtered = isHtml
        ? filterHtmlContent(content, resolvedConfig, blockedUrls)
        : filterMarkdownContent(content, resolvedConfig, blockedUrls);
    // Notify about blocked URLs if any were found
    const hasBlockedUrls = blockedUrls.images.length > 0 || blockedUrls.links.length > 0;
    if (hasBlockedUrls && resolvedConfig.onUrlBlocked) {
        resolvedConfig.onUrlBlocked(blockedUrls);
    }
    return filtered;
};
