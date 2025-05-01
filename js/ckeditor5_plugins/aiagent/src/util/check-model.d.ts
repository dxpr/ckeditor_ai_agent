import type { AiEngine } from '../type-identifiers.js';
export declare function checkModel(engine: AiEngine, model: string, apiKey?: string): Promise<{
    success: boolean;
    error?: string;
}>;
