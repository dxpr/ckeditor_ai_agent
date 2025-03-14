import type { AiModel, AIApiConfig } from '../type-identifiers.js';
export declare class AIApi {
    private apiKey;
    private baseURL;
    private engine;
    private editor;
    private providers?;
    constructor(config: AIApiConfig);
    /**
     * Asynchronously streams data from a ReadableStream.
     * @param stream - The ReadableStream to read from.
     * @returns An AsyncGenerator that yields Uint8Array chunks from the stream.
     */
    private streamAsyncIterator;
    /**
     * Fetches AI response from the API.
     *
     * This method sends a POST request to the AI API with the specified model and messages.
     * It handles retries in case of failure and throws a CustomError if the response is not ok.
     *
     * @param aiModel - The AI model to use for the request.
     * @param messages - An object containing system and user messages.
     * @param config - Configuration options for the request, including temperature, max_tokens, and stop sequences.
     * @param controller - An AbortController to manage request cancellation.
     * @param retries - The number of retries to attempt in case of failure.
     *
     * @returns A Promise that resolves to the Response object from the fetch call.
     *
     * @throws CustomError if the response is not ok after the specified number of retries.
     */
    private fetchAI;
    /**
     * Fetches a stream of AI responses from the API.
     *
     * This asynchronous generator method sends a request to the AI API and processes the response as a stream.
     * It yields chunks of data as they are received, handling retries in case of failure.
     * The method decodes the response and parses it line-by-line, yielding objects containing the type and text of the response.
     *
     * @param aiModel - The AI model to use for the request.
     * @param messages - An object containing system and user messages.
     * @param config - Configuration options for the request, including temperature, max_tokens, and stop sequences.
     * @param controller - An AbortController to manage request cancellation.
     * @param retries - The number of retries to attempt in case of failure.
     *
     * @returns An AsyncGenerator that yields objects containing the type and text of the response.
     *
     * @throws CustomError if the response cannot be parsed or if an error occurs during the fetch operation.
     */
    fetchAIStream(aiModel: AiModel, messages: {
        system: string;
        user: string;
    }, config: {
        temperature: number | undefined;
        max_tokens: number;
        stop: Array<string>;
    }, controller: AbortController, retries: number): AsyncGenerator<{
        type: string;
        text: string;
    }>;
}
