<?php

declare(strict_types=1);

use App\Enums\ProductPricingType;
use App\Mcp\Prism\Tools\Product\CreateProductTool;
use App\Models\Tenant\Product;

test('creates a one-time product', function () {
    $initialCount = Product::count();

    $productData = [
        'name' => 'Test One-Time Product',
        'label' => 'Buy Now Product',
        'pricing_type' => ProductPricingType::ONETIME->value,
        'price' => 9900, // $99.00
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toBe('Product created successfully')
        ->and($data)->toHaveKey('product')
        ->and($data['product']['name'])->toBe('Test One-Time Product')
        ->and($data['product']['label'])->toBe('Buy Now Product')
        ->and($data['product']['pricing_type'])->toBe(ProductPricingType::ONETIME->value)
        ->and($data['product']['price'])->toBe(9900)
        ->and($data['product']['is_one_time'])->toBeTrue()
        ->and($data['product']['is_subscription'])->toBeFalse();

    // Verify the product was created in the database
    $finalCount = Product::count();
    expect($finalCount)->toBe($initialCount + 1);

    $product = Product::where('name', 'Test One-Time Product')->first();
    expect($product)->not->toBeNull()
        ->and($product->pricing_type)->toBe(ProductPricingType::ONETIME)
        ->and($product->price)->toBe(9900);
});

test('creates a subscription product with recurring price and billing interval', function () {
    $productData = [
        'name' => 'Monthly Subscription',
        'label' => 'Subscribe Monthly',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 2999, // $29.99/month
        'billing_interval' => 'month',
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['name'])->toBe('Monthly Subscription')
        ->and($data['product']['pricing_type'])->toBe(ProductPricingType::SUBSCRIPTION->value)
        ->and($data['product']['recurring_price'])->toBe(2999)
        ->and($data['product']['billing_interval'])->toBe('month')
        ->and($data['product']['is_subscription'])->toBeTrue()
        ->and($data['product']['is_one_time'])->toBeFalse();

    $product = Product::where('name', 'Monthly Subscription')->first();
    expect($product)->not->toBeNull()
        ->and($product->pricing_type)->toBe(ProductPricingType::SUBSCRIPTION)
        ->and($product->recurring_price)->toBe(2999)
        ->and($product->billing_interval->value)->toBe('month');
});

test('creates a subscription product with setup fee and trial', function () {
    $productData = [
        'name' => 'Premium Plan',
        'label' => 'Premium Subscription',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 4999,
        'billing_interval' => 'month',
        'setup_fee' => 1000, // $10.00
        'trial_days' => 14,
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['setup_fee'])->toBe(1000)
        ->and($data['product']['trial_days'])->toBe(14);

    $product = Product::where('name', 'Premium Plan')->first();
    expect($product)->not->toBeNull()
        ->and($product->setup_fee)->toBe(1000)
        ->and($product->trial_days)->toBe(14)
        ->and($product->has_setup_fee)->toBeTrue()
        ->and($product->has_trial)->toBeTrue();
});

test('returns error when one-time product missing price', function () {
    $productData = [
        'name' => 'Invalid Product',
        'label' => 'Missing Price',
        'pricing_type' => ProductPricingType::ONETIME->value,
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Missing price')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('One-time products require a price');
});

test('returns error when subscription missing recurring_price', function () {
    $productData = [
        'name' => 'Invalid Subscription',
        'label' => 'Missing Recurring Price',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'billing_interval' => 'month',
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Missing recurring_price')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Subscription products require a recurring_price');
});

test('returns error when subscription missing billing_interval', function () {
    $productData = [
        'name' => 'Invalid Subscription',
        'label' => 'Missing Billing Interval',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 2999,
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Missing billing_interval')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Subscription products require a billing_interval');
});

test('returns error when pricing_type is invalid', function () {
    $productData = [
        'name' => 'Invalid Product',
        'label' => 'Invalid Type',
        'pricing_type' => 99, // Invalid type
        'price' => 1000,
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid pricing_type value')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Pricing type must be 1 (ONETIME) or 2 (SUBSCRIPTION)');
});

test('returns error when billing_interval is invalid', function () {
    $productData = [
        'name' => 'Invalid Product',
        'label' => 'Invalid Interval',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 2999,
        'billing_interval' => 'decade', // Invalid interval
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid billing_interval')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Billing interval must be day, week, month, or year');
});

test('creates subscription with different billing intervals', function () {
    $intervals = ['day', 'week', 'month', 'year'];

    foreach ($intervals as $interval) {
        $productData = [
            'name' => "Product {$interval}",
            'label' => "Label {$interval}",
            'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
            'recurring_price' => 1000,
            'billing_interval' => $interval,
        ];

        $tool = new CreateProductTool($this->account->id);
        $response = $tool($productData);
        $data = json_decode($response, true);

        expect($data)->toHaveKey('success')
            ->and($data['success'])->toBeTrue()
            ->and($data['product']['billing_interval'])->toBe($interval);
    }
});

test('creates product with zero price', function () {
    $productData = [
        'name' => 'Free Product',
        'label' => 'Get It Free',
        'pricing_type' => ProductPricingType::ONETIME->value,
        'price' => 0,
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['price'])->toBe(0)
        ->and($data['product']['display_price'])->toBe('$0.00');
});

test('creates subscription without optional setup fee', function () {
    $productData = [
        'name' => 'Basic Subscription',
        'label' => 'Basic Plan',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 1999,
        'billing_interval' => 'month',
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['setup_fee'])->toBeNull();

    $product = Product::where('name', 'Basic Subscription')->first();
    expect($product->has_setup_fee)->toBeFalse();
});

test('creates subscription without optional trial', function () {
    $productData = [
        'name' => 'No Trial Subscription',
        'label' => 'No Trial Plan',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 1999,
        'billing_interval' => 'month',
    ];

    $tool = new CreateProductTool($this->account->id);
    $response = $tool($productData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['trial_days'])->toBeNull();

    $product = Product::where('name', 'No Trial Subscription')->first();
    expect($product->has_trial)->toBeFalse();
});
