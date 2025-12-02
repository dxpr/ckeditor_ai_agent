import { Editor } from "ckeditor5";
/**
 * The AiAgentContext class provides a context for the AI Agent plugin,
 * allowing access to shared resources and state across different components.
 */
export declare class AiAgentContext {
    private static instance;
    private _uiComponent;
    private constructor();
    static getInstance(): AiAgentContext;
    set uiComponent(component: any);
    get uiComponent(): any;
    showError(message: string): void;
    showLoader(editor: Editor): void;
    hideLoader(editor: Editor): void;
}
export declare const aiAgentContext: AiAgentContext;
