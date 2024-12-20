# CKEditor AI Agent for Drupal

## Overview

CKEditor AI Agent is a Drupal module that integrates AI-powered content generation capabilities into CKEditor 5. It provides an intuitive interface for generating, modifying, and enhancing content directly within your editor.

## Requirements

- Drupal 10.4+ or 11
- CKEditor 5
- PHP 8.1 or higher
- OpenAI API key

## Installation

1. **Install the Module**
   - Download and place the module in your Drupal installation's modules directory
   - Enable the module through Drupal's admin interface or using Drush:
     ```bash
     drush en ckeditor_ai_agent
     ```

2. **Configure CKEditor Integration**
   - Go to **Administration > Configuration > Content authoring > Text formats and editors** (`admin/config/content/formats`)
   - Edit your desired text format (typically Full HTML)
   - Drag and drop the "AI Agent" button into the CKEditor toolbar to make it available for content editors

3. **Development**

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

   - **Configuration**

     These checks use:
     - PHP_CodeSniffer with Drupal coding standards
     - PHPStan for static analysis
     - Targets Drupal 10.x compatibility

## Configuration

### Global Settings

Navigate to `Administration > Configuration > Content authoring > CKEditor AI Agent Settings` (`/admin/config/content/ckeditor-ai-agent`) to configure global settings:

1. **Basic Settings**
   - API Key: Your OpenAI API key
   - Model: Select AI model (default: gpt-4o)
   - Endpoint URL: API endpoint URL (default: https://api.openai.com/v1/chat/completions)
   - Temperature: Controls response randomness (0-2)

2. **Advanced Settings**
   - Max Output Tokens: Maximum tokens in AI responses
   - Max Input Tokens: Maximum tokens for input context
   - Context Size: Size of context window (default: 75% of model's max input token limit)
   - Editor Context Ratio: Ratio of editor content in context (default: 0.3)

3. **Performance Settings**
   - Timeout Duration: Request timeout in milliseconds (default: 45000)
   - Retry Attempts: Number of retry attempts on failure (default: 1)

4. **Behavior Settings**
   - Debug Mode: Enable detailed logging (default: false)
   - Stream Content: Enable real-time content streaming (default: true)
   - Show Error Duration: Duration for error message display (default: 5000ms)

5. **Moderation Settings**
   - Enable Moderation: Toggle content moderation
   - Moderation API Key: Key for moderation service
   - Disable Flags: Configure moderation categories to ignore:
     - Sexual content
     - Harassment
     - Hate speech
     - Violence
     - Self-harm
     - Illicit content

### Editor-Specific Settings

You can configure settings per text format:

1. Go to `Administration > Configuration > Content authoring > Text formats and editors`
2. Edit your desired text format
3. In the CKEditor toolbar, locate and configure the "AI Agent" button
4. Configure editor-specific settings that will override global defaults

### Prompt Settings

The module allows customization of AI behavior through prompt components:

1. **Response Rules**
   - Configure basic response generation guidelines
   - Set default formatting preferences

2. **HTML Formatting**
   - Define HTML tag usage rules
   - Set structure preferences

3. **Content Structure**
   - Configure document organization rules
   - Set hierarchy preferences

4. **Tone Settings**
   - Define language style
   - Set formality levels

5. **Inline Content**
   - Configure handling of inline elements
   - Set integration rules

6. **Image Handling**
   - Define image processing rules
   - Set alt text requirements

## Features

- **Slash Command Integration**: Type "/" to trigger AI commands
- **Real-time Content Generation**: See AI-generated content as it's created
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

To manage access to CKEditor AI Agent settings, grant the following permission:
- "Administer CKEditor AI Agent"

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

This project is licensed under the GPL-2.0+ license. See the LICENSE file for details.
