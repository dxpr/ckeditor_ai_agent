import { getAllowedHtmlTags } from './html-utils.js';
import defaultRulesJson from '../config/default-rules.json';
export function getDefaultRules(editor) {
    var _a;
    const rules = { ...defaultRulesJson };
    // Replace the placeholder in htmlFormatting with actual allowed tags
    if ((_a = rules.htmlFormatting) === null || _a === void 0 ? void 0 : _a.includes('{{ALLOWED_HTML_TAGS}}')) {
        rules.htmlFormatting = rules.htmlFormatting.replace('{{ALLOWED_HTML_TAGS}}', getAllowedHtmlTags(editor).join(', '));
    }
    return rules;
}
