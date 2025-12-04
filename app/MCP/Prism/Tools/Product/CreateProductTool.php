<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Product;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Enums\BillingInterval;
use App\Enums\ProductPricingType;
use App\Managers\Tenant\ProductManager;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

/**
 * Create Product Tool
 *
 * Creates a new product with pricing configuration.
 * Products can be one-time purchases or subscriptions with optional setup fees and trial periods.
 */
class CreateProductTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('create_product')
            ->for('Create a new product with pricing configuration. Products can be one-time purchases or subscriptions.')
            ->withObjectParameter(
                name: 'input',
                description: 'Product creation parameters',
                properties: [
                    new StringSchema('name', 'The product name'),
                    new StringSchema('label', 'The product label shown to customers'),
                    new NumberSchema('pricing_type', 'The pricing type: 1 for one-time, 2 for subscription'),
                    new NumberSchema('price', 'Price in cents for one-time products', nullable: true),
                    new NumberSchema('setup_fee', 'Setup fee in cents (for subscriptions)', nullable: true),
                    new NumberSchema('recurring_price', 'Recurring price in cents (for subscriptions)', nullable: true),
                    new StringSchema('billing_interval', 'Billing interval: day, week, month, or year (for subscriptions)', nullable: true),
                    new NumberSchema('trial_days', 'Number of trial days (for subscriptions)', nullable: true),
                ],
                requiredFields: [
                    'name',
                    'label',
                    'pricing_type',
                ]
            )
            ->using($this);
    }

    /**
     * Execute the tool to create a new product.
     *
     * @param  array{
     *     name: string,
     *     label: string,
     *     pricing_type: int,
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
            $pricingType = ProductPricingType::tryFrom((int) $input['pricing_type']);

            if ($pricingType === null) {
                return json_encode([
                    'error' => 'Invalid pricing_type value',
                    'message' => 'Pricing type must be 1 (ONETIME) or 2 (SUBSCRIPTION)',
                ]);
            }

            // Validate pricing type specific requirements
            if ($pricingType === ProductPricingType::ONETIME && !isset($input['price'])) {
                return json_encode([
                    'error' => 'Missing price',
                    'message' => 'One-time products require a price.',
                ]);
            }

            if ($pricingType === ProductPricingType::SUBSCRIPTION) {
                if (!isset($input['recurring_price'])) {
                    return json_encode([
                        'error' => 'Missing recurring_price',
                        'message' => 'Subscription products require a recurring_price.',
                    ]);
                }

                if (!isset($input['billing_interval'])) {
                    return json_encode([
                        'error' => 'Missing billing_interval',
                        'message' => 'Subscription products require a billing_interval.',
                    ]);
                }
            }

            // Validate billing interval if provided
            $billingInterval = null;

            if (isset($input['billing_interval'])) {
                $billingInterval = BillingInterval::tryFrom($input['billing_interval']);

                if ($billingInterval === null) {
                    return json_encode([
                        'error' => 'Invalid billing_interval',
                        'message' => 'Billing interval must be day, week, month, or year.',
                    ]);
                }
            }

            $productManager = app(ProductManager::class);

            $product = $productManager->createProduct(
                name: $input['name'],
                label: $input['label'],
                pricingType: $pricingType,
                price: $input['price'] ?? null,
                setupFee: $input['setup_fee'] ?? null,
                recurringPrice: $input['recurring_price'] ?? null,
                billingInterval: $billingInterval,
                trialDays: $input['trial_days'] ?? null,
            );

            return json_encode([
                'success' => true,
                'message' => 'Product created successfully',
                'product' => [
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
                ],
            ]);
        }
        catch (\Throwable $e) {
            return json_encode([
                'error' => 'Failed to create product',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
