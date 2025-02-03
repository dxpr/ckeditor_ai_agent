import { Plugin } from 'ckeditor5/src/core.js';
import AiAgentUI from './aiagentui.js';
import AiAgentEditing from './aiagentediting.js';
import type { Editor } from 'ckeditor5';
import type { AiEngine } from './type-identifiers.js';
import '../theme/style.css';
export default class AiAgent extends Plugin {
    DEFAULT_GPT_ENGINE: AiEngine;
    DEFAULT_GPT_MODEL: "gpt-4o";
    constructor(editor: Editor);
    static get requires(): readonly [typeof AiAgentUI, typeof AiAgentEditing];
    static get pluginName(): "AiAgent";
    private validateConfiguration;
    init(): void;
}
