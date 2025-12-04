<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Product;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Enums\BillingInterval;
use App\Enums\ProductPricingType;
use App\Managers\Tenant\ProductManager;
use App\Repositories\Tenant\ProductRepository;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

/**
 * Update Product Tool
 *
 * Updates an existing product's details and pricing configuration.
 * Only fields provided will be updated; others remain unchanged.
 */
class UpdateProductTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('update_product')
            ->for('Update an existing product\'s details and pricing configuration. Only provided fields will be updated.')
            ->withObjectParameter(
                name: 'input',
                description: 'Product update parameters',
                properties: [
                    new NumberSchema('product_id', 'The unique identifier of the product to update'),
                    new StringSchema('name', 'The product name', nullable: true),
                    new StringSchema('label', 'The product label shown to customers', nullable: true),
                    new NumberSchema('pricing_type', 'The pricing type: 1 for one-time, 2 for subscription', nullable: true),
                    new NumberSchema('price', 'Price in cents for one-time products', nullable: true),
                    new NumberSchema('setup_fee', 'Setup fee in cents (for subscriptions)', nullable: true),
                    new NumberSchema('recurring_price', 'Recurring price in cents (for subscriptions)', nullable: true),
                    new StringSchema('billing_interval', 'Billing interval: day, week, month, or year (for subscriptions)', nullable: true),
                    new NumberSchema('trial_days', 'Number of trial days (for subscriptions)', nullable: true),
                ],
                requiredFields: [
                    'product_id',
                ]
            )
            ->using($this);
    }

    /**
     * Execute the tool to update an existing product.
     *
     * @param  array{
     *     product_id: int,
     *     name?: string,
     *     label?: string,
     *     pricing_type?: int,
     *     price?: int,
     *     setup_fee?: int,
     *     recurring_price?: int,
     *     billing_interval?: string,
     *     trial_days?: int
     * }  $input
     * @return string JSON-encoded product data or error message
     */
    public function __invoke(array $input): string
    {
        SwitchTenantContextAction::run($this->accountId);

        try {
            $productId = (int) $input['product_id'];
            $productRepository = app(ProductRepository::class);
            $product = $productRepository->findById($productId);

            if (empty($product)) {
                return json_encode([
                    'error' => 'Product not found',
                    'product_id' => $productId,
                ]);
            }

            // Prepare update parameters
            $pricingType = $product->pricing_type;

            if (isset($input['pricing_type'])) {
                $newPricingType = ProductPricingType::tryFrom($input['pricing_type']);

                if ($newPricingType === null) {
                    return json_encode([
                        'error' => 'Invalid pricing_type value',
                        'message' => 'Pricing type must be 1 (ONETIME) or 2 (SUBSCRIPTION)',
                    ]);
                }
                $pricingType = $newPricingType;
            }

            // Validate billing interval if provided
            $billingInterval = $product->billing_interval;

            if (isset($input['billing_interval'])) {
                $newBillingInterval = BillingInterval::tryFrom($input['billing_interval']);

                if ($newBillingInterval === null) {
                    return json_encode([
                        'error' => 'Invalid billing_interval',
                        'message' => 'Billing interval must be day, week, month, or year.',
                    ]);
                }
                $billingInterval = $newBillingInterval;
            }

            $productManager = app(ProductManager::class);

            $updatedProduct = $productManager->updateProduct(
                productId: $productId,
                name: $input['name'] ?? $product->name,
                label: $input['label'] ?? $product->label,
                pricingType: $pricingType,
                price: $input['price'] ?? $product->price,
                setupFee: $input['setup_fee'] ?? $product->setup_fee,
                recurringPrice: $input['recurring_price'] ?? $product->recurring_price,
                billingInterval: $billingInterval,
                trialDays: $input['trial_days'] ?? $product->trial_days,
            );

            // Refresh to get latest data
            $updatedProduct->refresh();

            return json_encode([
                'success' => true,
                'message' => 'Product updated successfully',
                'product' => [
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
                ],
            ]);
        }
        catch (\Throwable $e) {
            return json_encode([
                'error' => 'Failed to update product',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
