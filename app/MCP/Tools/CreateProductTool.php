<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\BillingInterval;
use App\Enums\ProductPricingType;
use App\Managers\Tenant\ProductManager;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateProductTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Create a new product with pricing configuration.
        Products can be one-time purchases or subscriptions with optional setup fees and trial periods.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, ProductManager $productManager): Response
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'required|string|max:255',
            'pricing_type' => 'required|integer|in:1,2',
            'price' => 'nullable|integer|min:0',
            'setup_fee' => 'nullable|integer|min:0',
            'recurring_price' => 'nullable|integer|min:0',
            'billing_interval' => 'nullable|string|in:day,week,month,year',
            'trial_days' => 'nullable|integer|min:0',
            'user_id' => 'nullable|integer',
        ]);

        $pricingType = ProductPricingType::from($request->get('pricing_type'));

        // Validate pricing type specific requirements
        if ($pricingType === ProductPricingType::ONETIME && !$request->has('price')) {
            return Response::error('One-time products require a price.');
        }

        if ($pricingType === ProductPricingType::SUBSCRIPTION) {
            if (!$request->has('recurring_price')) {
                return Response::error('Subscription products require a recurring_price.');
            }

            if (!$request->has('billing_interval')) {
                return Response::error('Subscription products require a billing_interval.');
            }
        }

        $product = $productManager->createProduct(
            name: $request->get('name'),
            label: $request->get('label'),
            pricingType: $pricingType,
            price: $request->get('price'),
            setupFee: $request->get('setup_fee'),
            recurringPrice: $request->get('recurring_price'),
            billingInterval: $request->has('billing_interval') ? BillingInterval::from($request->get('billing_interval')) : null,
            trialDays: $request->get('trial_days'),
            userId: $request->get('user_id'),
        );

        return Response::text(json_encode([
            'id' => $product->id,
            'name' => $product->name,
            'label' => $product->label,
            'pricing_type' => $product->pricing_type->value,
            'price' => $product->price,
            'setup_fee' => $product->setup_fee,
            'recurring_price' => $product->recurring_price,
            'billing_interval' => $product->billing_interval?->value,
            'trial_days' => $product->trial_days,
            'display_price' => $product->display_price,
            'is_subscription' => $product->is_subscription,
            'is_one_time' => $product->is_one_time,
            'created_at' => $product->created_at->toISOString(),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('The account ID that will own the product.'),
            'name' => $schema->string()->description('The product name.')->required(),
            'label' => $schema->string()->description('The product label shown to customers.')->required(),
            'pricing_type' => $schema->integer()->description('The pricing type: 1 for one-time, 2 for subscription.')->required(),
            'price' => $schema->integer()->description('Price in cents for one-time products.'),
            'setup_fee' => $schema->integer()->description('Setup fee in cents (for subscriptions).'),
            'recurring_price' => $schema->integer()->description('Recurring price in cents (for subscriptions).'),
            'billing_interval' => $schema->string()->description('Billing interval: day, week, month, or year (for subscriptions).'),
            'trial_days' => $schema->integer()->description('Number of trial days (for subscriptions).'),
            'user_id' => $schema->integer()->description('User ID creating the product.'),
        ];
    }
}
