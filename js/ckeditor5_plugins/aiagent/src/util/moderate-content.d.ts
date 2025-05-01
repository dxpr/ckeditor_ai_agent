import type { ModerationFlagsTypes } from '../type-identifiers.js';
import type { LocaleTranslate } from 'ckeditor5';
export declare function moderateContent({ content, moderationKey, timeOutDuration, disableFlags, t }: {
    content: string;
    moderationKey: string;
    timeOutDuration: number;
    disableFlags: Array<ModerationFlagsTypes>;
    t: LocaleTranslate;
}): Promise<boolean>;
