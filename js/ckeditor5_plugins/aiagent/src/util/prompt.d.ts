import type { Editor } from 'ckeditor5/src/core.js';
export interface ModelTokenLimits {
    maxInputContextTokens: number;
}
export declare function getModelTokenLimits(model: string): ModelTokenLimits;
export declare class PromptHelper {
    private editor;
    private contextSize;
    private promptSettings;
    private debugMode;
    private editorContextRatio;
    private contentScope;
    constructor(editor: Editor, options?: {
        editorContextRatio?: number;
    });
    generateGptPromptBasedOnUserPrompt(prompt: string, promptContainerText?: string, selectedContent?: string): Promise<string | null>;
    getSystemPrompt(isInlineResponse?: boolean): string;
    private trimContext;
    private formatFinalPrompt;
    private getComponentContent;
    private generateMarkDownForUrls;
    private allocateTokensToFetchedContent;
}
