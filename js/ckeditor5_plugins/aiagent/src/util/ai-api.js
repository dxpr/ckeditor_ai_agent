import CustomError, { getError } from './custom-error.js';
import { getAllowedHtmlTags } from './html-utils.js';
export class AIApi {
    constructor(config) {
        var _a;
        this.apiKey = (_a = config.apiKey) !== null && _a !== void 0 ? _a : '';
        this.baseURL = config.baseURL;
        this.engine = config.engine;
        this.editor = config.editor;
    }
    /**
     * Asynchronously streams data from a ReadableStream.
     * @param stream - The ReadableStream to read from.
     * @returns An AsyncGenerator that yields Uint8Array chunks from the stream.
     */
    async *streamAsyncIterator(stream) {
        const reader = stream.getReader();
        try {
            while (true) {
                const { done, value } = await reader.read();
                if (done) {
                    return;
                }
                yield value;
            }
        }
        finally {
            reader.releaseLock();
        }
    }
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
    async fetchAI(aiModel, messages, config, controller, retries) {
        const requestBody = {
            model: aiModel,
            messages: [
                { role: 'system', content: messages.system },
                { role: 'user', content: messages.user }
            ],
            stream: true,
            ...config
        };
        // Add allowed_html_tags only for DXAI engine
        if (this.engine === 'dxai') {
            const allowedTags = getAllowedHtmlTags(this.editor);
            requestBody.allowed_html_tags = allowedTags.join(', ');
        }
        const response = await fetch(this.baseURL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Authorization: `Bearer ${this.apiKey}`
            },
            body: JSON.stringify(requestBody),
            signal: controller.signal
        });
        if (!response.ok) {
            if (retries > 0) {
                return await this.fetchAI(aiModel, messages, config, controller, retries - 1);
            }
            else {
                const { status, error } = await getError(response);
                throw new CustomError(error, status);
            }
        }
        return response;
    }
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
    async *fetchAIStream(aiModel, messages, config, controller, retries) {
        var _a, _b;
        try {
            const response = await this.fetchAI(aiModel, messages, config, controller, retries);
            const decoder = new TextDecoder('utf-8');
            let buffer = ''; // Buffer for incomplete data chunks
            if (response.body) {
                for await (const chunk of this.streamAsyncIterator(response.body)) {
                    const text = decoder.decode(chunk, { stream: true });
                    buffer += text; // Append the current chunk to the buffer
                    // Process the buffer line-by-line
                    const lines = buffer.split('\n');
                    buffer = lines.pop(); // Save the last partial line back to the buffer
                    for (const line of lines) {
                        if (line.trim()) {
                            const dataString = line.substring(5).trim(); // Remove 'data: '
                            if (dataString === '[DONE]') {
                                break;
                            }
                            try {
                                const parsedData = JSON.parse(dataString);
                                let text = '';
                                let type = 'content';
                                if (parsedData.method === 'agent/status') {
                                    type = 'status';
                                    text = parsedData.params.status;
                                }
                                else {
                                    const content = (_b = (_a = parsedData.choices[0]) === null || _a === void 0 ? void 0 : _a.delta) === null || _b === void 0 ? void 0 : _b.content;
                                    if (content !== null && content !== undefined) {
                                        text = content;
                                    }
                                }
                                yield {
                                    text,
                                    type
                                };
                            }
                            catch (err) {
                                console.warn('Error parsing JSON:', err);
                            }
                        }
                    }
                }
            }
        }
        catch (error) {
            throw new CustomError(error, error === null || error === void 0 ? void 0 : error.status);
        }
    }
}
