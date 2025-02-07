import { aiAgentContext } from '../aiagentcontext.js';
import { removeLeadingSpaces, extractEditorContent, trimMultilineString } from './text-utils.js';
import { countTokens, trimLLMContentByTokens } from './token-utils.js';
import { fetchMultipleUrls } from './url-utils.js';
import { getDefaultRules } from './default-rules.js';
import { getAllowedHtmlTags } from './html-utils.js';
// Default token limits if no specific match is found
const DEFAULT_MAX_INPUT_TOKENS = 1000000;
export function getModelTokenLimits(model) {
    // OpenAI models
    if (model.includes('o1')) {
        return { maxInputContextTokens: 200000 };
    }
    if (model.includes('o3-mini')) {
        return { maxInputContextTokens: 200000 };
    }
    if (model.includes('gpt-4o')) {
        return { maxInputContextTokens: 128000 };
    }
    // Anthropic models
    if (model.includes('claude-2.0')) {
        return { maxInputContextTokens: 100000 };
    }
    if (model.includes('claude')) {
        return { maxInputContextTokens: 200000 };
    }
    // Google models
    if (model.includes('gemini-1.5-pro')) {
        return { maxInputContextTokens: 2000000 };
    }
    if (model.includes('gemini') && model.includes('flash')) {
        return { maxInputContextTokens: 1000000 };
    }
    if (model.includes('gemma')) {
        return { maxInputContextTokens: 8192 };
    }
    // Mistral models
    if (model.includes('codestral-mamba')) {
        return { maxInputContextTokens: 256000 };
    }
    if (model.includes('mixtral-8x22b')) {
        return { maxInputContextTokens: 65000 };
    }
    if (model.includes('mixtral-8x7b-32768')) {
        return { maxInputContextTokens: 32768 };
    }
    if (model.includes('mixtral') ||
        model.includes('mistral-medium') ||
        model.includes('mistral-small') ||
        model.includes('mistral-tiny')) {
        return { maxInputContextTokens: 33000 };
    }
    if (model.includes('mistral-large') || model.includes('ministral')) {
        return { maxInputContextTokens: 128000 };
    }
    // Default for all other models
    return { maxInputContextTokens: DEFAULT_MAX_INPUT_TOKENS };
}
export class PromptHelper {
    constructor(editor, options = {}) {
        var _a, _b, _c, _d, _e, _f;
        this.editor = editor;
        const config = editor.config.get('aiAgent');
        const model = ((_a = config.model) !== null && _a !== void 0 ? _a : 'gpt-4o');
        // Get model's maxInputContextTokens based on pattern matching
        const { maxInputContextTokens } = getModelTokenLimits(model);
        this.contextSize = (_b = config.contextSize) !== null && _b !== void 0 ? _b : Math.floor(maxInputContextTokens * 0.75);
        this.promptSettings = (_c = config.promptSettings) !== null && _c !== void 0 ? _c : {};
        this.debugMode = (_d = config.debugMode) !== null && _d !== void 0 ? _d : false;
        this.editorContextRatio = (_e = options.editorContextRatio) !== null && _e !== void 0 ? _e : 0.3;
        this.contentScope = (_f = config === null || config === void 0 ? void 0 : config.contentScope) !== null && _f !== void 0 ? _f : '';
        if (this.debugMode) {
            console.log('[Context Init]', {
                model,
                maxInputContextTokens,
                defaultContextSize: Math.floor(maxInputContextTokens * 0.75),
                configuredContextSize: config.contextSize,
                finalContextSize: this.contextSize
            });
        }
    }
    getSystemPrompt(isInlineResponse = false) {
        var _a, _b;
        const defaultComponents = getDefaultRules(this.editor);
        let systemPrompt = '';
        // Process each component
        for (const [id, defaultContent] of Object.entries(defaultComponents)) {
            // Skip components that are not allowed in the editor and not inline response
            if ((id === 'imageHandling' && !getAllowedHtmlTags(this.editor).includes('img')) ||
                (id === 'inlineContent' && !isInlineResponse)) {
                continue;
            }
            const componentId = id;
            let content = defaultContent;
            // Apply overrides if they exist
            if ((_a = this.promptSettings.overrides) === null || _a === void 0 ? void 0 : _a[componentId]) {
                content = this.promptSettings.overrides[componentId];
            }
            // Apply additions if they exist
            if ((_b = this.promptSettings.additions) === null || _b === void 0 ? void 0 : _b[componentId]) {
                content += '\n' + this.promptSettings.additions[componentId];
            }
            // Convert componentId to uppercase for XML tag
            const xmlTag = componentId.replace(/([A-Z])/g, '_$1').toUpperCase();
            // Add the component to the system prompt with XML tags
            systemPrompt += `<${xmlTag}>\n${trimMultilineString(content)}\n</${xmlTag}>\n\n`;
        }
        if (this.debugMode) {
            console.group('AiAgent System Prompt Debug');
            console.log('System Prompt:', systemPrompt);
            console.groupEnd();
        }
        return systemPrompt;
    }
    trimContext(prompt, promptContainerText = '') {
        var _a, _b, _c, _d, _e;
        let contentBeforePrompt = '';
        let contentAfterPrompt = '';
        const splitText = promptContainerText !== null && promptContainerText !== void 0 ? promptContainerText : prompt;
        const view = (_d = (_c = (_b = (_a = this.editor) === null || _a === void 0 ? void 0 : _a.editing) === null || _b === void 0 ? void 0 : _b.view) === null || _c === void 0 ? void 0 : _c.domRoots) === null || _d === void 0 ? void 0 : _d.get('main');
        let context = (_e = view === null || view === void 0 ? void 0 : view.innerText) !== null && _e !== void 0 ? _e : '';
        if (this.debugMode) {
            console.log('[Context]', {
                contextSize: this.contextSize,
                editorContextRatio: this.editorContextRatio
            });
        }
        if (this.contentScope) {
            const activeEditorElement = this.editor.editing.view.getDomRoot();
            const targetElement = activeEditorElement === null || activeEditorElement === void 0 ? void 0 : activeEditorElement.closest(this.contentScope);
            const ckContents = targetElement === null || targetElement === void 0 ? void 0 : targetElement.querySelectorAll('.ck-content');
            if (ckContents === null || ckContents === void 0 ? void 0 : ckContents.length) {
                context = '';
                Array.from(ckContents).map(item => {
                    context += context ? `\n${item.innerHTML}` : item.innerHTML;
                });
            }
        }
        const matchIndex = context.indexOf(splitText);
        const nextEnterIndex = context.indexOf('\n', matchIndex);
        const firstNewlineIndex = nextEnterIndex !== -1 ? nextEnterIndex : matchIndex + splitText.length;
        const beforeNewline = context.substring(0, firstNewlineIndex);
        const afterNewline = context.substring(firstNewlineIndex + 1);
        const contextParts = [beforeNewline, afterNewline];
        const allocatedEditorContextToken = Math.floor(this.contextSize * this.editorContextRatio);
        if (this.debugMode) {
            console.log('[Context Size]', {
                allocatedTokens: allocatedEditorContextToken,
                beforeLength: contextParts[0].length,
                afterLength: contextParts[1].length
            });
        }
        if (contextParts.length > 1) {
            if (contextParts[0].length < contextParts[1].length) {
                contentBeforePrompt = extractEditorContent(contextParts[0], allocatedEditorContextToken / 2, true, this.editor);
                contentAfterPrompt = extractEditorContent(contextParts[1], allocatedEditorContextToken - contentBeforePrompt.length / 4, false, this.editor);
            }
            else {
                contentAfterPrompt = extractEditorContent(contextParts[1], allocatedEditorContextToken / 2, false, this.editor);
                contentBeforePrompt = extractEditorContent(contextParts[0], allocatedEditorContextToken - contentAfterPrompt.length / 4, true, this.editor);
            }
        }
        // Combine the trimmed context with the cursor placeholder
        const escapedPrompt = prompt.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // Escapes special characters
        contentBeforePrompt = contentBeforePrompt.trim()
            .replace(new RegExp(escapedPrompt.slice(1)), '@@@cursor@@@')
            .replace('/@@@cursor@@@', '@@@cursor@@@'); // Remove forward slash if present
        const trimmedContext = `${contentBeforePrompt}\n${contentAfterPrompt}`;
        return trimmedContext.trim();
    }
    formatFinalPrompt(request, context, selectedContent, markDownContents, isEditorEmpty = false) {
        if (this.debugMode) {
            console.group('formatFinalPrompt Debug');
            console.log('Request:', request);
            console.log('Context:', context);
            console.log('MarkDownContents:', markDownContents);
            console.log('IsEditorEmpty:', isEditorEmpty);
        }
        const contentLanguageCode = this.editor.locale.contentLanguage;
        const corpus = [];
        // Task Section
        corpus.push('<TASK>');
        corpus.push(request);
        corpus.push('</TASK>');
        // Context Section
        if ((context === null || context === void 0 ? void 0 : context.length) && !selectedContent) {
            corpus.push('\n<CONTEXT>');
            corpus.push(context);
            corpus.push('</CONTEXT>');
        }
        if (selectedContent) {
            corpus.push('<SELECTED_CONTENT>');
            corpus.push(selectedContent);
            corpus.push('</SELECTED_CONTENT>');
        }
        // Markdown Content Section
        if (markDownContents === null || markDownContents === void 0 ? void 0 : markDownContents.length) {
            corpus.push('\n<REFERENCE_CONTENT>');
            for (const content of markDownContents) {
                corpus.push(`<SOURCE url="${content.url}">\n${content.content}\n</SOURCE>`);
            }
            corpus.push('</REFERENCE_CONTENT>');
            // Use default referenceGuidelines
            corpus.push('\n<REFERENCE_GUIDELINES>');
            corpus.push(this.getComponentContent('referenceGuidelines'));
            corpus.push('</REFERENCE_GUIDELINES>');
        }
        // Context-Specific Instructions
        if (!isEditorEmpty && !selectedContent) {
            corpus.push('\n<CONTEXT_REQUIREMENTS>');
            corpus.push(this.getComponentContent('contextRequirements'));
            corpus.push('</CONTEXT_REQUIREMENTS>');
        }
        // Add language instructions back
        corpus.push('\n<INSTRUCTIONS>');
        corpus.push(`The response must follow the language code - ${contentLanguageCode}.`);
        corpus.push('</INSTRUCTIONS>');
        // Debug Output
        if (this.debugMode) {
            console.group('AiAgent Final Prompt Debug');
            console.log('Final Prompt:', corpus.join('\n'));
            console.groupEnd();
        }
        return corpus.map(text => removeLeadingSpaces(text)).join('\n');
    }
    getComponentContent(componentId) {
        var _a, _b;
        const defaultComponents = getDefaultRules(this.editor);
        let content = defaultComponents[componentId];
        if ((_a = this.promptSettings.overrides) === null || _a === void 0 ? void 0 : _a[componentId]) {
            content = this.promptSettings.overrides[componentId];
        }
        if ((_b = this.promptSettings.additions) === null || _b === void 0 ? void 0 : _b[componentId]) {
            content += '\n' + this.promptSettings.additions[componentId];
        }
        return trimMultilineString(content);
    }
    async generateMarkDownForUrls(urls) {
        try {
            const results = await fetchMultipleUrls(urls);
            const markdownContents = [];
            for (const result of results) {
                if (result.content && !result.error) {
                    markdownContents.push({
                        content: result.content,
                        url: result.url,
                        tokenCount: countTokens(result.content)
                    });
                }
                else if (this.debugMode) {
                    console.error(`Failed to fetch content from ${result.url}:`, result.error);
                }
            }
            return this.allocateTokensToFetchedContent(this.getSystemPrompt(), markdownContents);
        }
        catch (error) {
            if (this.debugMode) {
                console.error('Error generating markdown content:', error);
            }
            aiAgentContext.showError('Failed to generate markdown content');
            return [];
        }
    }
    allocateTokensToFetchedContent(prompt, fetchedContent) {
        var _a, _b, _c, _d, _e, _f;
        const editorContent = (_f = (_e = (_d = (_c = (_b = (_a = this.editor) === null || _a === void 0 ? void 0 : _a.editing) === null || _b === void 0 ? void 0 : _b.view) === null || _c === void 0 ? void 0 : _c.domRoots) === null || _d === void 0 ? void 0 : _d.get('main')) === null || _e === void 0 ? void 0 : _e.innerText) !== null && _f !== void 0 ? _f : '';
        const editorToken = Math.min(Math.floor(this.contextSize * this.editorContextRatio), countTokens(editorContent));
        const availableLimit = this.contextSize - editorToken;
        if (availableLimit === 0 || !fetchedContent.length) {
            return fetchedContent;
        }
        const tokensPerContent = Math.floor(availableLimit / fetchedContent.length);
        return fetchedContent.map(content => ({
            ...content,
            content: trimLLMContentByTokens(content.content, tokensPerContent)
        }));
    }
}
