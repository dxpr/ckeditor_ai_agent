/**
 * The AiAgentContext class provides a context for the AI Agent plugin,
 * allowing access to shared resources and state across different components.
 */
export class AiAgentContext {
    static instance;
    _uiComponent;
    constructor() { }
    static getInstance() {
        if (!AiAgentContext.instance) {
            AiAgentContext.instance = new AiAgentContext();
        }
        return AiAgentContext.instance;
    }
    set uiComponent(component) {
        this._uiComponent = component;
    }
    get uiComponent() {
        return this._uiComponent;
    }
    showError(message) {
        if (this._uiComponent) {
            console.log('Showing error message...', message);
            this._uiComponent.showGptErrorToolTip(message);
        }
    }
    showLoader(editor) {
        if (this._uiComponent) {
            this._uiComponent.showLoader(editor);
        }
    }
    hideLoader(editor) {
        if (this._uiComponent) {
            this._uiComponent.hideLoader(editor);
        }
    }
}
export const aiAgentContext = AiAgentContext.getInstance();
