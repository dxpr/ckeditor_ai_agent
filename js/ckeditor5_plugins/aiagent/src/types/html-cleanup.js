/**
 * Default configuration that can be extended
 */
export const DEFAULT_HTML_CLEANUP_CONFIG = {
    attributes: {
        'id': {
            preserveAll: true
        },
        'alt': {
            preserveAll: true
        },
        'title': {
            preserveAll: true
        },
        'href': {
            preserveAll: true // Preserves link destinations
        },
        'type': {
            preserveAll: true // Preserves input types for better form understanding
        },
        'name': {
            preserveAll: true // Preserves form field names
        },
        'placeholder': {
            preserveAll: true
        },
        'class': {
            allowedSelectors: [
                // Layout classes
                '[class*="col-"]',
                '.col',
                '[class*="row"]',
                '[class*="container"]',
                // UI elements
                '.btn',
                '.button',
                '.card',
                '.panel',
                '.list',
                '.grid',
                // Media
                '[class*="image"]',
                '[class*="video"]',
                // Navigation
                '.nav',
                '.header',
                '.footer',
                '.sidebar'
            ]
        }
    },
    blacklist: [
        // UI Controls - only essential ones
        '.controls',
        '.dxpr-builder-ui',
        // Resource tags - only essential ones
        'style',
        'script',
        'link[rel="stylesheet"]',
        'meta',
        'noscript'
    ]
};
