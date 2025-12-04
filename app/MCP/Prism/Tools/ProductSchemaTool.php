<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools;

use App\Enums\BillingInterval;
use App\Enums\ProductPricingType;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

class ProductSchemaTool extends Tool
{
    public function __construct()
    {
        $this->as('product-schema')
            ->for('Understanding the product details by schema of an example product');
    }

    public function __invoke(): string | false
    {
        return json_encode(new ObjectSchema(
            name: 'product',
            description: 'A product with pricing information and billing details',
            properties: [
                new NumberSchema(
                    name: 'id',
                    description: 'The unique identifier for the product'
                ),
                new StringSchema(
                    name: 'name',
                    description: 'The internal name of the product'
                ),
                new StringSchema(
                    name: 'label',
                    description: 'The display label for the product shown to customers'
                ),
                new EnumSchema(
                    name: 'pricing_type',
                    description: 'The pricing type: "' . ProductPricingType::ONETIME->toStringValue() . '" for one-time purchases or "' . ProductPricingType::SUBSCRIPTION->toStringValue() . '" for recurring billing',
                    options: [ProductPricingType::ONETIME->toStringValue(), ProductPricingType::SUBSCRIPTION->toStringValue()]
                ),
                new NumberSchema(
                    name: 'price',
                    description: 'The price in cents for one-time products'
                ),
                new NumberSchema(
                    name: 'setup_fee',
                    description: 'The one-time setup fee in cents charged at subscription start'
                ),
                new NumberSchema(
                    name: 'recurring_price',
                    description: 'The recurring price in cents charged per billing interval for subscriptions'
                ),
                new EnumSchema(
                    name: 'billing_interval',
                    description: 'The billing frequency for subscriptions',
                    options: [BillingInterval::DAY->value, BillingInterval::WEEK->value, BillingInterval::MONTH->value, BillingInterval::YEAR->value]
                ),
                new NumberSchema(
                    name: 'trial_days',
                    description: 'The number of days in the trial period before charging begins'
                ),
            ],
            requiredFields: [
                'id',
                'name',
                'label',
                'pricing_type',
            ]
        )->toArray(), JSON_PRETTY_PRINT);
    }
}
