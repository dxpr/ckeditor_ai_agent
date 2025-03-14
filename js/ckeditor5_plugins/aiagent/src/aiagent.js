import { Plugin } from 'ckeditor5/src/core.js';
import AiAgentUI from './aiagentui.js';
import AiAgentEditing from './aiagentediting.js';
import { AI_CUSTOM_ENGINE, AI_CUSTOM_MODEL } from './const.js';
import { getModelTokenLimits } from './util/prompt.js';
export default class AiAgent extends Plugin {
    DEFAULT_GPT_ENGINE = 'openai';
    DEFAULT_GPT_MODEL = 'gpt-4o';
    isEnabled = false;
    constructor(editor) {
        super(editor);
        const config = editor.config.get('aiAgent') || {};
        // Check if plugin is enabled based on presence of API key
        this.isEnabled = Boolean(config.apiKey);
        // Set default values and merge with provided config
        const defaultConfig = {
            engine: this.DEFAULT_GPT_ENGINE,
            model: this.DEFAULT_GPT_MODEL,
            apiKey: '',
            endpointUrl: '',
            timeOutDuration: 120000,
            retryAttempts: 1,
            stopSequences: [],
            promptSettings: {},
            debugMode: false,
            streamContent: true // Default streaming mode
        };
        // Set default endpoint URL for DXAI engine
        if (config.engine === 'dxai') {
            if (!config.endpointUrl) {
                config.endpointUrl = 'https://kavya.dxpr.com/v1/chat/completions';
            }
            if (!config.model) {
                config.model = 'kavya-m1';
            }
        }
        let tokenLimits = {};
        const model = config.model ?? defaultConfig.model;
        if (model && AI_CUSTOM_ENGINE.includes(config.engine) && config.engine !== 'dxai') {
            const { maxInputContextTokens } = getModelTokenLimits(model);
            tokenLimits = {
                maxOutputTokens: 16384,
                maxInputTokens: maxInputContextTokens,
                contextSize: maxInputContextTokens * 0.75
            };
        }
        // First merge defaults with user config to preserve user settings
        const mergedConfig = {
            ...defaultConfig,
            ...config
        };
        // Then add token limits if needed
        const updatedConfig = {
            ...mergedConfig,
            ...tokenLimits
        };
        // Set the merged config back to the editor
        editor.config.set('aiAgent', updatedConfig);
        // Only validate configuration if plugin is enabled
        if (this.isEnabled) {
            this.validateConfiguration(updatedConfig);
        }
    }
    static get requires() {
        return [AiAgentUI, AiAgentEditing];
    }
    static get pluginName() {
        return 'AiAgent';
    }
    async validateConfiguration(config) {
        // Skip validation if plugin is disabled
        if (!this.isEnabled) {
            return;
        }
        // Only validate engine-specific requirements and other settings
        // since API key presence was already validated in constructor
        if (AI_CUSTOM_ENGINE.includes(config.engine)) {
            // TODO: Chooses models to support in production
            if (!AI_CUSTOM_MODEL.includes(config.model)) {
                // 	throw new Error( `AiAgent: model is not allowed for ${ config.engine }` );
            }
            if (!config.endpointUrl) {
                throw new Error('AiAgent: endpointUrl is required for custom engine.');
            }
            // Validate providers is only used with dxai engine
            if (config.providers && config.engine !== 'dxai') {
                throw new Error('AiAgent: providers is only supported with the dxai engine.');
            }
        }
        else if (config.providers) {
            // If engine is not dxai but providers is set, throw an error
            throw new Error('AiAgent: providers is only supported with the dxai engine.');
        }
        // Validate common settings
        if (config.temperature && (config.temperature < 0 || config.temperature > 2)) {
            throw new Error('AiAgent: Temperature must be a number between 0 and 2.');
        }
        const model = config.model ?? this.DEFAULT_GPT_MODEL;
        const { maxInputContextTokens } = getModelTokenLimits(model);
        const DEFAULT_MAX_OUTPUT_TOKENS = 16384;
        const DEFAULT_MIN_OUTPUT_TOKENS = 0;
        // Validate output tokens
        if (config.maxOutputTokens !== undefined) {
            if (config.maxOutputTokens < DEFAULT_MIN_OUTPUT_TOKENS ||
                config.maxOutputTokens > DEFAULT_MAX_OUTPUT_TOKENS) {
                throw new Error(`AiAgent: maxOutputTokens must be between ${DEFAULT_MIN_OUTPUT_TOKENS} ` +
                    `and ${DEFAULT_MAX_OUTPUT_TOKENS} for ${config.model}`);
            }
        }
        // Validate input tokens
        if (config.maxInputTokens !== undefined &&
            config.maxInputTokens > maxInputContextTokens) {
            throw new Error(`AiAgent: maxInputTokens cannot exceed ${maxInputContextTokens} ` +
                `for ${config.model}`);
        }
    }
    init() {
        // Any additional initialization if needed
    }
}
