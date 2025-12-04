<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Server\Prompt;

class CreateTemplateWorkflowPrompt extends Prompt
{
    public string $name = 'create-template-workflow';

    public string $description = 'Guide for creating new funnel templates following the proper workflow';

    public string $prompt = <<<'MARKDOWN'
        # Template Creation Workflow

        When a user wants to create a new funnel template, follow this EXACT 6-step process:

        ## Step 1: Gather Requirements
        Ask the user for:
        - account_id (numeric ID of the account)
        - template name (descriptive name for the template)

        ## Step 2: Get Design Requirements
        Ask the user for a description of the desired page style and layout, including:
        - Overall design theme (modern, minimalist, professional, etc.)
        - Color scheme preferences
        - Layout structure (hero section, features, testimonials, etc.)
        - Any specific branding requirements

        ## Step 3: Create Template Metadata
        Use CreateTemplateTool with ONLY account_id and name:
        ```json
        {
          "account_id": 123,
          "name": "My Template Name"
        ```
        This creates an empty template record.

        ## Step 4: Add HTML Content
        Generate complete HTML structure based on the design requirements.
        IMPORTANT: Include {{CHECKOUT_COMPONENT}} placeholder where the checkout form should appear.
        Use UpdateTemplateTool with html_content field.

        ## Step 5: Add CSS Styling
        Create comprehensive CSS based on the design requirements.
        Use UpdateTemplateTool with css_content field.

        ## Step 6: Add JavaScript
        Add any interactive JavaScript functionality.
        Use UpdateTemplateTool with js_content field.

        ## Important Rules
        - Never combine steps - each content update must be separate
        - Always validate HTML includes {{CHECKOUT_COMPONENT}} placeholder
        - Use existing templates as reference with DescribeTemplateTool
        - Ask clarifying questions if requirements are unclear
    MARKDOWN;
}
