import type { LocaleTranslate } from 'ckeditor5';
import type { Editor } from 'ckeditor5/src/core.js';
export declare function getErrorMessages(status: number, t: LocaleTranslate): string;
export declare function getDefaultAiAgentDropdownMenu(editor: Editor): Array<{
    title: string;
    items: Array<{
        title: string;
        command: string;
    }>;
}>;
export declare function getDefaultAiAgentToneDropdownMenu(editor: Editor): Array<{
    label: string;
    key: string;
    tone: string;
}>;
