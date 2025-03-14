interface FetchResult {
    url: string;
    content: string;
    error?: string;
}
/**
 * Fetches content from multiple URLs in parallel with a maximum concurrency of 5.
 *
 * @param urls - Array of URLs to fetch content from
 * @param maxConcurrent - Maximum number of concurrent requests (default: 5)
 * @returns Promise resolving to an array of FetchResult objects
 */
export declare function fetchMultipleUrls(urls: Array<string>, maxConcurrent?: number): Promise<Array<FetchResult>>;
/**
 * @deprecated Use fetchMultipleUrls instead for better performance
 */
export declare function fetchUrlContent(url: string): Promise<string>;
export {};
