<?php

declare(strict_types=1);

use App\Enums\ProductPricingType;
use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\CreateProductTool;
use App\Models\Tenant\Product;

test('creates a one-time product', function () {
    $initialCount = Product::count();

    $productData = [
        'account_id' => $this->account->id,
        'name' => 'Test One-Time Product',
        'label' => 'Buy Now Product',
        'pricing_type' => ProductPricingType::ONETIME->value,
        'price' => 9900, // $99.00
    ];

    $response = FunnelGenServer::tool(CreateProductTool::class, $productData);

    $response->assertOk();
    $response->assertSee('"name"');
    $response->assertSee('Test One-Time Product');
    $response->assertSee('"label"');
    $response->assertSee('Buy Now Product');
    $response->assertSee('"pricing_type"');
    $response->assertSee('1');
    $response->assertSee('"price"');
    $response->assertSee('9900');
    $response->assertSee('"is_one_time"');
    $response->assertSee('true');

    // Verify the product was created in the database
    $finalCount = Product::count();
    expect($finalCount)->toBe($initialCount + 1);

    $product = Product::where('name', 'Test One-Time Product')->first();
    expect($product)->not->toBeNull();
    expect($product->pricing_type)->toBe(ProductPricingType::ONETIME);
    expect($product->price)->toBe(9900);
});

test('creates a subscription product with recurring price and billing interval', function () {
    $productData = [
        'account_id' => $this->account->id,
        'name' => 'Monthly Subscription',
        'label' => 'Subscribe Monthly',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 2999, // $29.99/month
        'billing_interval' => 'month',
    ];

    $response = FunnelGenServer::tool(CreateProductTool::class, $productData);

    $response->assertOk();
    $response->assertSee('"name"');
    $response->assertSee('Monthly Subscription');
    $response->assertSee('"pricing_type"');
    $response->assertSee('2');
    $response->assertSee('"recurring_price"');
    $response->assertSee('2999');
    $response->assertSee('"billing_interval"');
    $response->assertSee('month');
    $response->assertSee('"is_subscription"');
    $response->assertSee('true');

    $product = Product::where('name', 'Monthly Subscription')->first();
    expect($product)->not->toBeNull();
    expect($product->pricing_type)->toBe(ProductPricingType::SUBSCRIPTION);
    expect($product->recurring_price)->toBe(2999);
    expect($product->billing_interval->value)->toBe('month');
});

test('creates a subscription product with setup fee and trial', function () {
    $productData = [
        'account_id' => $this->account->id,
        'name' => 'Premium Plan',
        'label' => 'Premium Subscription',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 4999,
        'billing_interval' => 'month',
        'setup_fee' => 1000, // $10.00
        'trial_days' => 14,
    ];

    $response = FunnelGenServer::tool(CreateProductTool::class, $productData);

    $response->assertOk();
    $response->assertSee('Premium Plan');
    $response->assertSee('"setup_fee"');
    $response->assertSee('1000');
    $response->assertSee('"trial_days"');
    $response->assertSee('14');

    $product = Product::where('name', 'Premium Plan')->first();
    expect($product)->not->toBeNull();
    expect($product->setup_fee)->toBe(1000);
    expect($product->trial_days)->toBe(14);
    expect($product->has_setup_fee)->toBeTrue();
    expect($product->has_trial)->toBeTrue();
});

test('returns error when one-time product missing price', function () {
    $productData = [
        'account_id' => $this->account->id,
        'name' => 'Invalid Product',
        'label' => 'Missing Price',
        'pricing_type' => ProductPricingType::ONETIME->value,
    ];

    $response = FunnelGenServer::tool(CreateProductTool::class, $productData);

    $response->assertHasErrors();
    $response->assertSee('One-time products require a price.');
});

test('returns error when subscription missing recurring_price', function () {
    $productData = [
        'account_id' => $this->account->id,
        'name' => 'Invalid Subscription',
        'label' => 'Missing Recurring Price',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'billing_interval' => 'month',
    ];

    $response = FunnelGenServer::tool(CreateProductTool::class, $productData);

    $response->assertHasErrors();
    $response->assertSee('Subscription products require a recurring_price.');
});

test('returns error when subscription missing billing_interval', function () {
    $productData = [
        'account_id' => $this->account->id,
        'name' => 'Invalid Subscription',
        'label' => 'Missing Billing Interval',
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 2999,
    ];

    $response = FunnelGenServer::tool(CreateProductTool::class, $productData);

    $response->assertHasErrors();
    $response->assertSee('Subscription products require a billing_interval.');
});
