<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Server\Prompt;

class CreateProductWorkflowPrompt extends Prompt
{
    public string $name = 'create-product-workflow';

    public string $description = 'Guide for creating new products with proper pricing configuration';

    public string $prompt = <<<'MARKDOWN'
        # Product Creation Workflow

        When a user wants to create a new product, follow this process:

        ## Step 1: Gather Basic Requirements
        Ask the user for:
        - account_id (numeric ID of the account)
        - product name (internal name for the product)
        - product label (customer-facing label shown during checkout)

        ## Step 2: Determine Pricing Type
        Ask the user which pricing model they want:
        - **One-time purchase** (pricing_type: 1) - Single payment products
        - **Subscription** (pricing_type: 2) - Recurring payment products

        ## Step 3A: For One-Time Products
        If the user selects one-time purchase, ask for:
        - price (in cents, e.g., 9900 for $99.00)

        Use CreateProductTool with:
        ```json
        {
          "account_id": 123,
          "name": "Premium Package",
          "label": "Premium Package - Lifetime Access",
          "pricing_type": 1,
          "price": 9900
        }
        ```

        ## Step 3B: For Subscription Products
        If the user selects subscription, ask for:
        - recurring_price (in cents, e.g., 2900 for $29.00)
        - billing_interval (day, week, month, or year)
        - setup_fee (optional, in cents)
        - trial_days (optional, number of free trial days)

        Use CreateProductTool with:
        ```json
        {
          "account_id": 123,
          "name": "Monthly Membership",
          "label": "Monthly Membership Plan",
          "pricing_type": 2,
          "recurring_price": 2900,
          "billing_interval": "month",
          "setup_fee": 5000,
          "trial_days": 14
        }
        ```

        ## Important Rules
        - All prices must be in cents (multiply dollar amount by 100)
        - One-time products REQUIRE a price field
        - Subscription products REQUIRE recurring_price and billing_interval
        - Billing intervals must be one of: day, week, month, or year
        - Setup fees and trial days are optional for subscriptions
        - Trial days must be 0 or greater
        - user_id is optional (defaults to current user)

        ## Validation Reminders
        - Validate that one-time products have a price
        - Validate that subscriptions have recurring_price and billing_interval
        - Ensure all price values are positive integers in cents
        - Confirm billing_interval is valid (day/week/month/year)

        ## Example Scenarios

        ### Example 1: Simple One-Time Product
        User wants: "eBook for $19.99"
        ```json
        {
          "account_id": 1,
          "name": "Complete Guide eBook",
          "label": "Complete Guide eBook",
          "pricing_type": 1,
          "price": 1999
        }
        ```

        ### Example 2: Monthly Subscription with Trial
        User wants: "Monthly plan at $49/month with 7-day free trial"
        ```json
        {
          "account_id": 1,
          "name": "Pro Monthly Plan",
          "label": "Pro Plan - Billed Monthly",
          "pricing_type": 2,
          "recurring_price": 4900,
          "billing_interval": "month",
          "trial_days": 7
        }
        ```

        ### Example 3: Annual Subscription with Setup Fee
        User wants: "Annual plan at $299/year with $99 setup fee"
        ```json
        {
          "account_id": 1,
          "name": "Annual Enterprise Plan",
          "label": "Enterprise Plan - Billed Annually",
          "pricing_type": 2,
          "recurring_price": 29900,
          "billing_interval": "year",
          "setup_fee": 9900
        }
        ```

        ## After Creation
        The tool will return the created product with:
        - Product ID
        - All pricing details
        - Computed properties like display_price
        - Timestamps

        You can then use this product_id when creating funnels with CreateFunnelTool.
    MARKDOWN;
}
