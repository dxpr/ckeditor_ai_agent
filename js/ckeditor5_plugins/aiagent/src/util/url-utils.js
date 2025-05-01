import { aiAgentContext } from '../aiagentcontext.js';
/**
 * Fetches content from multiple URLs in parallel with a maximum concurrency of 5.
 *
 * @param urls - Array of URLs to fetch content from
 * @param maxConcurrent - Maximum number of concurrent requests (default: 5)
 * @returns Promise resolving to an array of FetchResult objects
 */
export async function fetchMultipleUrls(urls, maxConcurrent = 5) {
    const results = [];
    const chunks = [];
    // Split URLs into chunks of maxConcurrent size
    for (let i = 0; i < urls.length; i += maxConcurrent) {
        chunks.push(urls.slice(i, i + maxConcurrent));
    }
    // Process each chunk in parallel
    for (const chunk of chunks) {
        const chunkResults = await Promise.all(chunk.map(url => fetchUrlWithRetry(url)));
        results.push(...chunkResults);
    }
    return results;
}
/**
 * Fetches content from a URL with retry logic
 *
 * @param url - The URL to fetch from
 * @param maxRetries - Maximum number of retry attempts (default: 3)
 * @returns Promise resolving to a FetchResult object
 */
async function fetchUrlWithRetry(url, maxRetries = 3) {
    const result = { url, content: '' };
    const urlRegex = /^(https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i;
    const trimmedUrl = url.trim();
    if (!urlRegex.test(trimmedUrl)) {
        result.error = 'Invalid URL format';
        return result;
    }
    for (let attempt = 0; attempt < maxRetries; attempt++) {
        try {
            const cleanedUrl = trimmedUrl.replace(/[^\x20-\x7E]/g, '');
            const requestURL = `https://r.jina.ai/${cleanedUrl.trim()}`;
            const response = await fetch(requestURL.trim());
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const content = await response.text();
            if (content.includes('Warning: Target URL returned error')) {
                throw new Error(`Target URL (${trimmedUrl}) returned an error`);
            }
            if (content.trim().length === 0) {
                throw new Error('Empty content received');
            }
            result.content = content
                .replace(/\(https?:\/\/[^\s]+\)/g, '')
                .replace(/^\s*$/gm, '')
                .trim();
            return result;
        }
        catch (error) {
            if (attempt === maxRetries - 1) {
                result.error = error.message;
                console.error(`Failed to fetch content after ${maxRetries} attempts: ${url}`, error);
                aiAgentContext.showError(`Failed to fetch content from ${url}`);
            }
            else {
                // Exponential backoff
                await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempt) * 1000));
            }
        }
    }
    return result;
}
/**
 * @deprecated Use fetchMultipleUrls instead for better performance
 */
export async function fetchUrlContent(url) {
    const result = await fetchUrlWithRetry(url);
    if (result.error) {
        throw new Error(result.error);
    }
    return result.content;
}
