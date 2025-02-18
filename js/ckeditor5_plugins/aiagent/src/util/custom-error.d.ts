export default class CustomError extends Error {
    status: number;
    constructor(message: string, status: number);
}
/**
 * Retrieves error information from a Response object.
 *
 * @param response - The Response object from which to extract error details.
 * @returns A Promise that resolves to an object containing the status code and error data.
 *
 * @throws Will throw an error if the response cannot be parsed.
 */
export declare function getError(response: Response): Promise<{
    status: number;
    error: any;
}>;
