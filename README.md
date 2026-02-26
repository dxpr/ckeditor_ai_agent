# CKEditor AI Agent for Drupal

## Overview

CKEditor AI Agent is a Drupal module that integrates AI-powered content
generation capabilities into CKEditor 5. It provides an intuitive interface for
generating, modifying, and enhancing content directly within your editor.

## Requirements

- Drupal 10.4+ or 11
- CKEditor 5
- PHP 8.1 or higher
- Key module (for secure API key storage)
- Markdownify module (provides markdown versions of Drupal content for AI to access when URLs are included in prompts)
- [AI module](https://www.drupal.org/project/ai) (recommended) — routes AI requests through Drupal server-side, keeping API keys out of the browser. Works with any AI provider plugin (DXPR, OpenAI, Anthropic, Ollama, and more).
- An AI provider module (e.g. [DXPR AI Provider](https://www.drupal.org/project/ai_provider_dxpr), [OpenAI](https://www.drupal.org/project/ai_provider_openai), etc.) or a direct API key

## Installation

1. **Install the Module**
   ```bash
   composer require drupal/ckeditor_ai_agent
   drush en ckeditor_ai_agent
   ```

2. **Set Up an AI Provider (recommended)**

   Install the AI module and a provider plugin to route requests server-side
   (API keys stay on the server, never sent to the browser):
   ```bash
   composer require drupal/ai drupal/ai_provider_dxpr
   drush en ai ai_provider_dxpr
   ```
   Then configure a default Chat provider at **Administration > Configuration >
   AI > Settings** (`/admin/config/ai/settings`).

   Any AI provider supported by the AI module will work (DXPR, OpenAI,
   Anthropic, Ollama, etc.).

   Alternatively, you can use the module without the AI module by configuring
   an API key directly (see step 3).

3. **Configure API Key Storage (without AI module)**

   If not using the AI module, configure direct API access:
   - Go to **Administration > Configuration > System > Keys**
     (`admin/config/system/keys`)
   - Add a new key for your AI provider API credentials
   - Select "Authentication" as the key type
   - Enter your API key value

4. **Configure CKEditor Integration**
   - Go to **Administration > Configuration > Content authoring > Text formats
   and editors** (`admin/config/content/formats`)
   - Edit your desired text format (typically Full HTML)
   - Drag and drop the "AI Agent" button into the CKEditor toolbar to make it
   available for content editors

4. **Development**

   - **Code Quality Checks**

     Run these commands from the repository root:

     ```bash
     # Run Drupal coding standards checks
     docker compose run drupal-lint

     # Auto-fix Drupal coding standards where possible
     docker compose run drupal-lint-auto-fix

     # Run static analysis using PHPStan
     docker compose run drupal-check
     ```

   - **Updating the Build**

     When updating the CKEditor AI Agent plugin or making changes to the build:

     ```bash
     # Update and build the plugin (this will trigger the postinstall hook)
     npm install

     # Clear Drupal cache
     drush cr
     ```

     Note: After updating, test the plugin functionality in your text formats 
     where CKEditor AI Agent is enabled. During development, you can use:
     - `npm run watch` for automatic rebuilds while making changes
     - Check the browser console for any JavaScript errors

   - **Configuration**

     These checks use:
     - PHP_CodeSniffer with Drupal coding standards
     - PHPStan for static analysis
     - Targets Drupal 10.x compatibility

## Configuration

Navigate to `Administration > Configuration > Content authoring > CKEditor AI
Agent Settings` (`/admin/config/content/ckeditor-ai-agent`) to configure global
settings.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| **Basic Settings** ||||
| `key_provider` | `string` | - | Select the key that contains your AI provider API credentials. Not needed when using the AI module (keys are managed server-side). |
| `model` | `string` | `'gpt-4o'` | AI model to use. When the AI module is installed, the default provider's model is used automatically. |
| `endpointUrl` | `string` | - | API endpoint URL. Automatically set when using the AI module. Only change if using a custom endpoint or direct API access. |
| **Advanced Settings** ||||
| `temperature` | `number` | `0.7` | Controls the creativity of AI responses. Low values (0.0-0.5) produce consistent, deterministic responses ideal for factual content. Medium values (0.6-1.0) offer balanced creativity. High values (1.1-2.0) generate more diverse and unexpected responses |
| `maxOutputTokens` | `number` | Model's max limit | Maximum number of tokens for AI response. If not set, uses model's maximum limit |
| `maxInputTokens` | `number` | Model's max limit | Maximum number of tokens for combined prompt and context. If not set, uses model's maximum limit |
| `contextSize` | `number` | 75% of max input tokens | How many tokens to use for surrounding content. Must be less than Total Token Limit. Recommended: 75% of Total Token Limit to leave room for AI instructions |
| `editorContextRatio` | `number` | `0.3` | Portion of context for editor content. Default: 0.3 (30%) |
| **Performance Settings** ||||
| `timeoutDuration` | `number` | `120000` | Maximum wait time for AI response in milliseconds |
| `retryAttempts` | `number` | `1` | Number of retry attempts for failed requests |
| **Behavior Settings** ||||
| `debugMode` | `boolean` | `false` | Enable detailed logging for troubleshooting purposes |
| `showErrorDuration` | `number` | `5000` | How long to display error messages in milliseconds |
| **Content Moderation** ||||
| `moderation.enable` | `boolean` | `false` | Filter inappropriate or unsafe content. Recommended for public-facing implementations |
| `moderation.key` | `string` | - | Separate API key for content moderation service. Required if using a different service than the main AI |
| `moderation.disableFlags` | `array` | `[]` | Select content types to exclude from moderation (e.g., sexual, harassment, hate, violence, self-harm, illicit). Use with caution |

## Features

- **Slash Command Integration**: Type "/" to trigger AI commands
- **Real-time Content Generation**: See AI-generated content as it's created
- **Secure Server-Side Proxy**: AI requests route through Drupal via the AI module — API keys never reach the browser
- **400+ AI Models**: Works with any provider supported by the Drupal AI module (DXPR, OpenAI, Anthropic, Ollama, and more)
- **Context-Aware Responses**: AI considers surrounding content
- **Content Moderation**: Optional content filtering
- **Multiple Language Support**: Works with CKEditor's language settings
- **Customizable Prompts**: Configure response formatting and rules
- **RAG Support**: Include URLs in prompts for reference material
- **Streaming Responses**: View content generation in real-time
- **Advanced Controls**: Manage temperature, tokens, and other parameters

## Usage

1. **Basic Commands**
   - Type "/" in the editor to start an AI command
   - Use predefined commands from the AI Agent toolbar button
   - Press Enter to execute the command

2. **Advanced Usage**
   - Include URLs in prompts for reference material
   - Use Shift+Enter for multiline prompts
   - Cancel generation with Ctrl/Cmd + Backspace

3. **Command Examples**
   ```
   /write about open source software
   ```
   ```
   /Create a blog post summary:
   https://example.com/article1
   https://example.com/article2
   ```

## Permissions

- **"Administer CKEditor AI Agent"** — Access to global settings at `/admin/config/content/ckeditor-ai-agent`
- **"Use CKEditor AI Agent"** — Required for sending AI requests through the server-side proxy endpoint. Grant this to roles that should have AI access in the editor.

## Troubleshooting

1. **Debug Mode**
   - Enable debug mode in settings to view detailed logs
   - Check browser console for error messages
   - Verify API key and endpoint configurations

2. **Common Issues**
   - Token limits: Adjust max token settings if responses are truncated
   - Timeout errors: Increase timeout duration for longer responses
   - Moderation blocks: Review and adjust moderation flags

3. **Performance Tips**
   - Optimize context size for better response times
   - Use streaming mode for better user experience
   - Configure retry attempts based on network reliability

## Additional Resources

- [CKEditor 5 Documentation](https://ckeditor.com/docs/ckeditor5/latest/)
- [OpenAI API Documentation](https://platform.openai.com/docs/api-reference)
- [Drupal CKEditor 5 Integration Guide](https://www.drupal.org/docs/core-modules-and-themes/core-modules/ckeditor-5-module)

## Support

For bug reports and feature requests, please use the [issue queue](https://www.drupal.org/project/issues/ckeditor_ai_agent).

## License

This project is licensed under the GPL-2.0+ license. See the LICENSE file for
details.
