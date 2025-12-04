<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\BillingInterval;
use App\Enums\ProductPricingType;
use App\Managers\Tenant\ProductManager;
use App\Repositories\Tenant\ProductRepository;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateProductTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Update an existing product's details and pricing configuration.
        Only fields provided will be updated; others remain unchanged.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, ProductManager $productManager, ProductRepository $productRepository): Response
    {
        $request->validate([
            'id' => 'required|integer',
            'name' => 'sometimes|string|max:255',
            'label' => 'sometimes|string|max:255',
            'pricing_type' => 'sometimes|integer|in:1,2',
            'price' => 'sometimes|integer|min:0',
            'setup_fee' => 'sometimes|integer|min:0',
            'recurring_price' => 'sometimes|integer|min:0',
            'billing_interval' => 'sometimes|string|in:day,week,month,year',
            'trial_days' => 'sometimes|integer|min:0',
            'user_id' => 'sometimes|integer',
        ]);

        // Check if product exists
        $product = $productRepository->findById($request->get('id'));

        if (!$product) {
            return Response::error("Product with ID {$request->get('id')} not found.");
        }

        // Prepare update parameters
        $pricingType = $request->has('pricing_type')
            ? ProductPricingType::from($request->get('pricing_type'))
            : $product->pricing_type;

        $billingInterval = null;

        if ($request->has('billing_interval')) {
            $billingInterval = BillingInterval::from($request->get('billing_interval'));
        }
        elseif ($product->billing_interval) {
            $billingInterval = $product->billing_interval;
        }

        // Update the product
        $updatedProduct = $productManager->updateProduct(
            productId: $request->get('id'),
            name: $request->get('name', $product->name),
            label: $request->get('label', $product->label),
            pricingType: $pricingType,
            price: $request->get('price', $product->price),
            setupFee: $request->get('setup_fee', $product->setup_fee),
            recurringPrice: $request->get('recurring_price', $product->recurring_price),
            billingInterval: $billingInterval,
            trialDays: $request->get('trial_days', $product->trial_days),
            userId: $request->get('user_id'),
        );

        // Refresh to get latest data
        $updatedProduct->refresh();

        return Response::text(json_encode([
            'id' => $updatedProduct->id,
            'name' => $updatedProduct->name,
            'label' => $updatedProduct->label,
            'pricing_type' => $updatedProduct->pricing_type->value,
            'price' => $updatedProduct->price,
            'setup_fee' => $updatedProduct->setup_fee,
            'recurring_price' => $updatedProduct->recurring_price,
            'billing_interval' => $updatedProduct->billing_interval?->value,
            'trial_days' => $updatedProduct->trial_days,
            'display_price' => $updatedProduct->display_price,
            'is_subscription' => $updatedProduct->is_subscription,
            'is_one_time' => $updatedProduct->is_one_time,
            'updated_at' => $updatedProduct->updated_at->toISOString(),
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
            'account_id' => $schema->integer()->description('The account ID that owns the product.'),
            'id' => $schema->integer()->description('The unique identifier of the product to update.')->required(),
            'name' => $schema->string()->description('The product name.'),
            'label' => $schema->string()->description('The product label shown to customers.'),
            'pricing_type' => $schema->integer()->description('The pricing type: 1 for one-time, 2 for subscription.'),
            'price' => $schema->integer()->description('Price in cents for one-time products.'),
            'setup_fee' => $schema->integer()->description('Setup fee in cents (for subscriptions).'),
            'recurring_price' => $schema->integer()->description('Recurring price in cents (for subscriptions).'),
            'billing_interval' => $schema->string()->description('Billing interval: day, week, month, or year (for subscriptions).'),
            'trial_days' => $schema->integer()->description('Number of trial days (for subscriptions).'),
            'user_id' => $schema->integer()->description('User ID updating the product.'),
        ];
    }
}
