<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Server\Prompt;

class CreateFunnelWorkflowPrompt extends Prompt
{
    public string $name = 'create-funnel-workflow';

    public string $description = 'Guide for creating new funnels (sales funnels) that connect products with templates and payment processors';

    public string $prompt = <<<'MARKDOWN'
        # Funnel Creation Workflow

        When a user wants to create a new funnel, follow this structured process:

        ## Step 1: Gather Basic Requirements
        Ask the user for:
        - account_id (numeric ID of the account)
        - funnel name (descriptive name for the funnel)
        - slug (URL-friendly identifier, e.g., "premium-checkout")
        - main_product_id (the primary product being sold)

        ## Step 2: Determine Configuration Options

        ### Essential Configuration
        Ask about these important settings:
        - **Status**: What status should the funnel have?
          - `disabled` (0) - Funnel is turned off
          - `test` (1) - Testing mode
          - `draft` (2) - Work in progress (default)
          - `live` (3) - Active and accepting payments

        - **Support Email**: Email for customer support (defaults to support@example.com)

        - **Template**: Which template should be used for the checkout page?
          - Ask for template_id or use ListTemplateTool to show available templates

        ### Payment Configuration
        - **Payment Processor**: Which payment processor integration to use?
          - Ask for processor_id (e.g., Stripe integration ID)

        - **Currency**: 3-letter ISO currency code (defaults to "USD")
          - Examples: USD, EUR, GBP, CAD

        - **Tax Collection**: Should tax be collected? (defaults to false)

        ### Optional Enhancements
        - **Bump Offers**: Up to 5 additional products to offer during checkout
          - Ask for bump_offer_ids array (product IDs)
          - Use ListProductTool to show available products

        - **Language**: 2-letter ISO language code (defaults to "en")
          - Examples: en, es, fr, de

        - **Fulfillment**: What happens after purchase?
          - `invoice` - Show invoice/confirmation page
          - `redirect` - Redirect to external URL (requires fulfillment_url)

        ## Step 3: Create the Funnel

        ### Minimal Example (Required Fields Only)
        ```json
        {
          "account_id": 123,
          "name": "Premium Product Funnel",
          "slug": "premium-product",
          "main_product_id": 456
        }
        ```

        ### Basic Example with Template and Processor
        ```json
        {
          "account_id": 123,
          "name": "Premium Product Funnel",
          "slug": "premium-product",
          "main_product_id": 456,
          "template_id": 789,
          "processor_id": 1,
          "status": "draft"
        }
        ```

        ### Complete Example with All Options
        ```json
        {
          "account_id": 123,
          "name": "Complete Product Bundle",
          "slug": "complete-bundle",
          "status": "live",
          "support_email": "support@mycompany.com",
          "language_code": "en",
          "currency_code": "USD",
          "main_product_id": 456,
          "bump_offer_ids": [789, 790, 791],
          "tax_enabled": true,
          "processor_id": 1,
          "template_id": 10,
          "fulfillment": "redirect",
          "fulfillment_url": "https://mycompany.com/thank-you"
        }
        ```

        ## Important Rules and Validation

        ### Required Fields
        - `account_id` - Must be provided
        - `name` - Funnel name (max 255 characters)
        - `slug` - URL-friendly slug (max 255 characters)
        - `main_product_id` - Must reference an existing product

        ### Optional Field Defaults
        - `status` - Defaults to "draft"
        - `owner` - Defaults to user_id or 1
        - `support_email` - Defaults to "support@example.com"
        - `language_code` - Defaults to "en"
        - `currency_code` - Defaults to "USD"
        - `tax_enabled` - Defaults to false

        ### Validation Rules
        - Status must be one of: disabled, test, draft, live
        - Language code must be 2 characters
        - Currency code must be 3 characters
        - Bump offers limited to 5 products maximum
        - Fulfillment must be: invoice or redirect
        - If fulfillment is "redirect", fulfillment_url is required
        - Support email must be valid email format

        ### Prerequisites
        Before creating a funnel, ensure:
        1. The main product exists (use GetProductTool or ListProductTool)
        2. Any bump offer products exist
        3. The template exists if specifying template_id (use GetTemplateTool or ListTemplateTool)
        4. The payment processor integration exists if specifying processor_id

        ## Step 4: Verify Prerequisites (If Needed)

        If the user hasn't created products or templates yet, guide them:

        **Missing Products?**
        - Suggest using the `create-product-workflow` prompt
        - Or use ListProductTool to see existing products

        **Missing Templates?**
        - Suggest using the `create-template-workflow` prompt
        - Or use ListTemplateTool to see existing templates

        **Missing Payment Processor?**
        - Inform user they need to configure payment processor integration first
        - Funnel can be created without processor_id for testing

        ## Example Scenarios

        ### Scenario 1: Simple Funnel for Testing
        User wants: "Create a test funnel for my eBook"
        ```json
        {
          "account_id": 1,
          "name": "eBook Test Funnel",
          "slug": "ebook-test",
          "main_product_id": 42,
          "status": "test"
        }
        ```

        ### Scenario 2: Live Funnel with Bump Offers
        User wants: "Launch my course with 2 upsells"
        ```json
        {
          "account_id": 1,
          "name": "Course Launch Funnel",
          "slug": "course-launch",
          "main_product_id": 100,
          "bump_offer_ids": [101, 102],
          "template_id": 5,
          "processor_id": 1,
          "status": "live",
          "support_email": "help@mycourse.com"
        }
        ```

        ### Scenario 3: Subscription with Custom Thank You Page
        User wants: "Monthly membership that redirects to welcome page"
        ```json
        {
          "account_id": 1,
          "name": "Monthly Membership Funnel",
          "slug": "monthly-membership",
          "main_product_id": 200,
          "template_id": 8,
          "processor_id": 1,
          "fulfillment": "redirect",
          "fulfillment_url": "https://members.mysite.com/welcome",
          "tax_enabled": true,
          "currency_code": "USD",
          "status": "live"
        }
        ```

        ## After Creation

        The tool will return the created funnel with:
        - Funnel ID and slug
        - Status and status label
        - All configuration details
        - Product IDs (main product and bump offers)
        - Template and processor IDs
        - Timestamps

        ## Next Steps

        After creating a funnel, you can:
        1. Update it using UpdateFunnelTool to modify configuration
        2. View full details using GetFunnelTool
        3. Test the checkout page at the funnel's URL
        4. Add or remove bump offers
        5. Change status from draft/test to live when ready

        ## Common Issues to Avoid

        - Don't forget to set status to "live" when ready for production
        - Ensure processor_id is set before accepting real payments
        - Validate that main_product_id exists before creating funnel
        - If using redirect fulfillment, always include fulfillment_url
        - Slugs should be unique and URL-safe (lowercase, hyphens, no spaces)
    MARKDOWN;
}
