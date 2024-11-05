import { aiAssistContext } from './aiassistcontext.js';
import { PromptHelper } from './util/prompt.js';
import { HtmlParser } from './util/htmlparser.js';
export default class AiAssistService {
  /**
   * Initializes the AiAssistService with the provided editor and configuration settings.
   *
   * @param editor - The CKEditor instance to be used with the AI assist service.
   */
  constructor(editor) {
    var _a, _b;
    this.aiAssistFeatureLockId = Symbol('ai-assist-feature');
    this.buffer = '';
    this.openTags = [];
    this.isInlineInsertion = false;
    this.editor = editor;
    this.promptHelper = new PromptHelper(editor);
    this.htmlParser = new HtmlParser(editor);
    const config = editor.config.get('aiAssist');
    this.aiModel = config.model;
    this.apiKey = config.apiKey;
    this.endpointUrl = config.endpointUrl;
    this.temperature = config.temperature;
    this.timeOutDuration = (_a = config.timeOutDuration) !== null && _a !== void 0 ? _a : 45000;
    this.maxTokens = config.maxTokens;
    this.retryAttempts = config.retryAttempts;
    this.stopSequences = config.stopSequences;
    this.streamContent = (_b = config.streamContent) !== null && _b !== void 0 ? _b : true;
  }
  /**
   * Handles the slash command input from the user, processes it, and interacts with the AI model.
   *
   * @returns A promise that resolves when the command has been processed.
   */
  async handleSlashCommand() {
    const editor = this.editor;
    const model = editor.model;
    const mapper = editor.editing.mapper;
    const view = editor.editing.view;
    const root = model.document.getRoot();
    let content;
    let parentEquivalentHTML;
    let parent;
    const position = model.document.selection.getLastPosition();
    if (position && root) {
      parent = position.parent;
      const inlineSlash = parent.name === 'inline-slash' ? parent : undefined;
      const equivalentView = mapper.toViewElement(parent);
      parentEquivalentHTML = equivalentView ? view.domConverter.mapViewToDom(equivalentView) : undefined;
      if (inlineSlash) {
        this.isInlineInsertion = true;
        const startingPath = inlineSlash.getPath();
        const endingPath = position === null || position === void 0 ? void 0 : position.path;
        const startPosition = model.createPositionFromPath(root, startingPath); // Example path
        const endPosition = model.createPositionFromPath(root, endingPath); // Example path
        const range = model.createRange(startPosition, endPosition);
        parentEquivalentHTML = (equivalentView === null || equivalentView === void 0 ? void 0 : equivalentView.parent) ?
          view.domConverter.mapViewToDom(equivalentView.parent) :
          undefined;
        content = '';
        for (const item of range.getItems()) {
          if (item.is('$textProxy')) {
            content += item.data.trim(); // Add text data
          }
        }
      }
      else if (parentEquivalentHTML) {
        content = parentEquivalentHTML === null || parentEquivalentHTML === void 0 ? void 0 : parentEquivalentHTML.innerText;
      }
    }
    try {
      const domSelection = window.getSelection();
      const domRange = domSelection === null || domSelection === void 0 ? void 0 : domSelection.getRangeAt(0);
      const rect = domRange.getBoundingClientRect();
      aiAssistContext.showLoader(rect);
      const gptPrompt = await this.generateGptPromptBasedOnUserPrompt(content !== null && content !== void 0 ? content : '', parentEquivalentHTML === null || parentEquivalentHTML === void 0 ? void 0 : parentEquivalentHTML.innerText);
      if (parent && gptPrompt) {
        await this.fetchAndProcessGptResponse(gptPrompt, parent);
      }
    }
    catch (error) {
      console.error('Error handling slash command:', error);
      throw error;
    }
    finally {
      this.isInlineInsertion = false;
      aiAssistContext.hideLoader();
    }
  }
  /**
   * Fetches and processes the GPT response based on the provided prompt and parent element.
   *
   * @param prompt - The prompt to send to the GPT model.
   * @param parent - The parent element in the editor where the response will be inserted.
   * @param retries - The number of retry attempts for the API call (default is the configured retry attempts).
   * @returns A promise that resolves when the response has been processed.
   */
  async fetchAndProcessGptResponse(prompt, parent, retries = this.retryAttempts) {
    var _a, _b, _c;
    console.log('Starting fetchAndProcessGptResponse');
    const editor = this.editor;
    const t = editor.t;
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.timeOutDuration);
    let buffer = '';
    let contentBuffer = '';
    try {
      const response = await fetch(this.endpointUrl, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          model: this.aiModel,
          messages: [
            { role: 'system', content: this.promptHelper.getSystemPrompt(this.isInlineInsertion) },
            { role: 'user', content: prompt }
          ],
          temperature: this.temperature,
          max_tokens: this.maxTokens,
          stop: this.stopSequences,
          stream: true
        }),
        signal: controller.signal
      });
      clearTimeout(timeoutId);
      if (!response.ok) {
        throw new Error('Fetch failed');
      }
      aiAssistContext.hideLoader();
      const reader = response.body.getReader();
      const decoder = new TextDecoder('utf-8');
      this.clearParentContent(parent);
      this.editor.enableReadOnlyMode(this.aiAssistFeatureLockId);
      console.log('Starting to process response');
      for (;;) {
        const { done, value } = await reader.read();
        if (done) {
          console.log('Finished reading response');
          break;
        }
        const chunk = decoder.decode(value, { stream: true });
        buffer += chunk;
        let newlineIndex;
        while ((newlineIndex = buffer.indexOf('\n')) !== -1) {
          const line = buffer.slice(0, newlineIndex).trim();
          buffer = buffer.slice(newlineIndex + 1);
          if (line.startsWith('data: ')) {
            const jsonStr = line.slice(5).trim();
            if (jsonStr === '[DONE]') {
              console.log('Received [DONE] signal');
              break;
            }
            try {
              const data = JSON.parse(jsonStr);
              const content = (_b = (_a = data.choices[0]) === null || _a === void 0 ? void 0 : _a.delta) === null || _b === void 0 ? void 0 : _b.content;
              if (content) {
                contentBuffer += content;
                if (this.htmlParser.isCompleteHtmlChunk(contentBuffer)) {
                  await this.processContent(contentBuffer, parent);
                  contentBuffer = '';
                }
              }
            }
            catch (parseError) {
              console.warn('Error parsing JSON:', parseError);
            }
          }
        }
      }
      // Process any remaining content in the buffer
      if (contentBuffer.trim()) {
        await this.processContent(contentBuffer.trim(), parent);
      }
    }
    catch (error) {
      console.error('Error in fetchAndProcessGptResponse:', error);
      const errorIdentifier = ((error === null || error === void 0 ? void 0 : error.message) || '').trim() || ((error === null || error === void 0 ? void 0 : error.name) || '').trim();
      const isRetryableError = [
        'AbortError',
        'ReadableStream not supported',
        'AiAssist: Fetch failed'
      ].includes(errorIdentifier);
      if (retries > 0 && isRetryableError) {
        console.warn(`Retrying... (${retries} attempts left)`);
        return await this.fetchAndProcessGptResponse(prompt, parent, retries - 1);
      }
      let errorMessage;
      switch ((error === null || error === void 0 ? void 0 : error.name) || ((_c = error === null || error === void 0 ? void 0 : error.message) === null || _c === void 0 ? void 0 : _c.trim())) {
        case 'ReadableStream not supported':
          errorMessage = t('Browser does not support readable streams');
          break;
        case 'AiAssist: Fetch failed':
          errorMessage = t('We couldn\'t connect to the AI. Please check your internet');
          break;
        default:
          errorMessage = t('We couldn\'t connect to the AI. Please check your internet');
      }
      aiAssistContext.showError(errorMessage);
    }
    finally {
      this.editor.disableReadOnlyMode(this.aiAssistFeatureLockId);
    }
  }
  /**
   * Processes the provided content and inserts it into the specified parent element.
   * Depending on the feature flag, it either uses a simple HTML insertion method
   * or processes the content as HTML.
   *
   * @param content - The content to be processed and inserted.
   * @param parent - The parent element in the editor where the content will be inserted.
   */
  async processContent(content, parent) {
    try {
      console.log('--- Start of processContent ---');
      console.log('Processing content:', content, this.isInlineInsertion);
      if (this.isInlineInsertion) {
        const position = this.editor.model.document.selection.getLastPosition();
        const tempParagraph = document.createElement('div');
        tempParagraph.innerHTML = content;
        await this.htmlParser.insertSimpleHtml(content);
      }
      else {
        if (this.streamContent) {
          // Existing complex content processing logic
          await this.proceedHtmlResponse(content);
        }
        else {
          // Use the simple HTML insertion method
          await this.htmlParser.insertSimpleHtml(content);
        }
      }
      console.log('--- End of processContent ---');
    }
    catch (error) {
      console.error(error);
    }
  }
  /**
   * Processes the provided HTML string and inserts its content into the editor.
   * It creates a temporary div to parse the HTML and handles different types of
   * elements (lists, tables, headings, etc.) accordingly.
   *
   * @param html - The HTML string to be processed and inserted into the editor.
   */
  async proceedHtmlResponse(html) {
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    for (const child of Array.from(tempDiv.childNodes)) {
      const element = child;
      if (element.nodeType === Node.ELEMENT_NODE) {
        const elementName = element.tagName.toLowerCase();
        const isStreamingNotAllow = [
          'table', 'blockquote', 'pre', 'img', 'form', 'figure'
        ].includes(elementName);
        if (isStreamingNotAllow) {
          await this.htmlParser.insertSimpleHtml(element.outerHTML);
        }
        else if (elementName === 'ul' || elementName === 'ol') {
          await this.htmlParser.insertAsText(element, undefined, true, true);
        }
        else {
          await this.htmlParser.insertAsText(element, undefined, true);
        }
      }
      else if (element.nodeType === Node.TEXT_NODE && element.textContent) {
        const tempParagraph = document.createElement('div');
        tempParagraph.innerText = element.textContent;
        await this.htmlParser.insertAsText(tempParagraph, undefined, true);
      }
    }
  }
  /**
   * Clears the content of the specified parent element in the editor.
   *
   * @param parent - The parent element whose content will be cleared.
   */
  clearParentContent(parent) {
    const editor = this.editor;
    const model = editor.model;
    const root = model.document.getRoot();
    const position = model.document.selection.getLastPosition();
    const inlineSlash = Array.from(parent.getChildren()).find((child) => child.name === 'inline-slash');
    if (root && position) {
      editor.model.change(writer => {
        const startingPath = (inlineSlash === null || inlineSlash === void 0 ? void 0 : inlineSlash.getPath()) || parent.getPath();
        const range = model.createRange(model.createPositionFromPath(root, startingPath), model.createPositionFromPath(root, position.path));
        writer.remove(range);
        writer.setSelection(model.createPositionFromPath(root, startingPath));
      });
    }
  }
  /**
   * Generates a GPT prompt based on the user's input and the current context in the editor.
   * This method processes the input prompt, extracts any URLs, and formats the final prompt
   * to be sent to the GPT model. It also handles the case where the editor is empty.
   *
   * @param prompt - The user's input prompt, typically starting with a slash.
   * @param promptContainerText - Optional text from the container that may provide additional context.
   * @returns A promise that resolves to the generated GPT prompt string or null if an error occurs.
   */
  async generateGptPromptBasedOnUserPrompt(prompt, promptContainerText) {
    try {
      const context = this.promptHelper.trimContext(prompt, promptContainerText);
      const request = prompt.slice(1); // Remove the leading slash
      let markDownContents = [];
      const urlRegex = /https?:\/\/[^\s/$.?#].[^\s]*/g;
      const urls = prompt.match(urlRegex);
      if (Array.isArray(urls) && urls.length) {
        const formattedUrl = urls.map(url => {
          return url.replace(/[,.]$/, '');
        });
        markDownContents = await this.promptHelper.generateMarkDownForUrls(formattedUrl);
        markDownContents = this.promptHelper.allocateTokensToFetchedContent(prompt, markDownContents);
      }
      const isEditorEmpty = context === '"@@@cursor@@@"';
      return this.promptHelper.formatFinalPrompt(request, context, markDownContents, isEditorEmpty);
    }
    catch (error) {
      console.error(error);
      return null;
    }
  }
}
