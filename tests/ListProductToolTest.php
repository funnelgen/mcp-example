<?php

declare(strict_types=1);

use App\Enums\ProductPricingType;
use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\ListProductTool;
use App\Models\Tenant\Product;

test('lists all products for an account', function () {
    // Create test products
    Product::factory()->create([
        'name' => 'Product One',
        'label' => 'First Product',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    Product::factory()->create([
        'name' => 'Product Two',
        'label' => 'Second Product',
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
    ]);

    Product::factory()->create([
        'name' => 'Product Three',
        'label' => 'Third Product',
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 9999,
        'billing_interval' => 'year',
        'trial_days' => 30,
    ]);

    $response = FunnelGenServer::tool(ListProductTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertOk();
    $response->assertSee('"total"');
    $response->assertSee('3');
    $response->assertSee('Product One');
    $response->assertSee('Product Two');
    $response->assertSee('Product Three');
    $response->assertSee('"pricing_type"');
    $response->assertSee('"display_price"');
});

test('returns empty list when no products exist', function () {
    $response = FunnelGenServer::tool(ListProductTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertOk();
    $response->assertSee('"total"');
    $response->assertSee('0');
    $response->assertSee('"products"');
    $response->assertSee('[]');
});

test('lists products with behavior rules when requested', function () {
    $product = Product::factory()->create([
        'name' => 'Product with Rules',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 1000,
    ]);

    $response = FunnelGenServer::tool(ListProductTool::class, [
        'account_id' => $this->account->id,
        'with_behavior_rules' => true,
    ]);

    $response->assertOk();
    $response->assertSee('Product with Rules');
    $response->assertSee('"behavior_rules_count"');
});

test('shows correct pricing information for one-time products', function () {
    Product::factory()->create([
        'name' => 'One-Time Product',
        'label' => 'Buy Once',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 7500, // $75.00
    ]);

    $response = FunnelGenServer::tool(ListProductTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertOk();
    $response->assertSee('One-Time Product');
    $response->assertSee('"price"');
    $response->assertSee('7500');
    $response->assertSee('"is_one_time"');
    $response->assertSee('true');
    $response->assertSee('"is_subscription"');
    $response->assertSee('false');
});

test('shows correct pricing information for subscription products', function () {
    Product::factory()->create([
        'name' => 'Subscription Product',
        'label' => 'Monthly Plan',
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 1999,
        'billing_interval' => 'month',
        'setup_fee' => 500,
        'trial_days' => 7,
    ]);

    $response = FunnelGenServer::tool(ListProductTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertOk();
    $response->assertSee('Subscription Product');
    $response->assertSee('"recurring_price"');
    $response->assertSee('1999');
    $response->assertSee('"billing_interval"');
    $response->assertSee('month');
    $response->assertSee('"setup_fee"');
    $response->assertSee('500');
    $response->assertSee('"trial_days"');
    $response->assertSee('7');
    $response->assertSee('"is_subscription"');
    $response->assertSee('true');
    $response->assertSee('"has_setup_fee"');
    $response->assertSee('true');
    $response->assertSee('"has_trial"');
    $response->assertSee('true');
});
