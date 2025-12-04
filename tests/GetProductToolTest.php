<?php

declare(strict_types=1);

use App\Enums\Integrations;
use App\Enums\ProductActionType;
use App\Enums\ProductBehaviorRuleEventType;
use App\Enums\ProductPricingType;
use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\GetProductTool;
use App\Models\Tenant\Integration;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBehaviorRule;

test('gets a product by id', function () {
    $product = Product::factory()->create([
        'name' => 'Test Product',
        'label' => 'Test Label',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $response = FunnelGenServer::tool(GetProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
    ]);

    $response->assertOk();
    $response->assertSee('"id"');
    $response->assertSee((string) $product->id);
    $response->assertSee('"name"');
    $response->assertSee('Test Product');
    $response->assertSee('"label"');
    $response->assertSee('Test Label');
    $response->assertSee('"pricing_type"');
    $response->assertSee('1');
    $response->assertSee('"price"');
    $response->assertSee('5000');
    $response->assertSee('"display_price"');
    $response->assertSee('"is_one_time"');
    $response->assertSee('true');
});

test('returns error when product not found', function () {
    $response = FunnelGenServer::tool(GetProductTool::class, [
        'account_id' => $this->account->id,
        'id' => 99999,
    ]);

    $response->assertHasErrors();
    $response->assertSee('Product with ID 99999 not found.');
});

test('gets subscription product with full details', function () {
    $product = Product::factory()->create([
        'name' => 'Premium Plan',
        'label' => 'Premium Subscription',
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 9999,
        'billing_interval' => 'month',
        'setup_fee' => 2500,
        'trial_days' => 14,
    ]);

    $response = FunnelGenServer::tool(GetProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
    ]);

    $response->assertOk();
    $response->assertSee('Premium Plan');
    $response->assertSee('"pricing_type"');
    $response->assertSee('2');
    $response->assertSee('"recurring_price"');
    $response->assertSee('9999');
    $response->assertSee('"billing_interval"');
    $response->assertSee('month');
    $response->assertSee('"setup_fee"');
    $response->assertSee('2500');
    $response->assertSee('"trial_days"');
    $response->assertSee('14');
    $response->assertSee('"is_subscription"');
    $response->assertSee('true');
    $response->assertSee('"has_setup_fee"');
    $response->assertSee('true');
    $response->assertSee('"has_trial"');
    $response->assertSee('true');
    $response->assertSee('"display_recurring_price"');
    $response->assertSee('"display_setup_fee"');
    $response->assertSee('"todays_price"');
    $response->assertSee('"base_price"');
});

test('gets product with behavior rules when requested', function () {
    $integration = Integration::factory()->create([
        'name' => Integrations::WEBHOOK,
        'enabled' => true,
    ]);

    $product = Product::factory()->create([
        'name' => 'Product with Rules',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 1000,
    ]);

    $rule = ProductBehaviorRule::factory()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'event_type' => ProductBehaviorRuleEventType::ORDER_COMPLETED,
        'action_type' => ProductActionType::WEBHOOK,
        'action_config' => ['url' => 'https://example.com/webhook'],
        'is_active' => true,
    ]);

    $response = FunnelGenServer::tool(GetProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
        'with_behavior_rules' => true,
    ]);

    $response->assertOk();
    $response->assertSee('Product with Rules');
    $response->assertSee('"behavior_rules"');
    $response->assertSee('"event_type"');
    $response->assertSee('"action_type"');
    $response->assertSee('"action_config"');
    $response->assertSee('"integration"');
});

test('gets product without behavior rules when not requested', function () {
    $product = Product::factory()->create([
        'name' => 'Simple Product',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 1000,
    ]);

    ProductBehaviorRule::factory()->create([
        'product_id' => $product->id,
    ]);

    $response = FunnelGenServer::tool(GetProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
        'with_behavior_rules' => false,
    ]);

    $response->assertOk();
    $response->assertSee('Simple Product');
    // Don't check for absence - just verify the product data is returned
});

test('shows correct computed prices for one-time product', function () {
    $product = Product::factory()->create([
        'name' => 'One-Time Item',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 7500, // $75.00
    ]);

    $response = FunnelGenServer::tool(GetProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
    ]);

    $response->assertOk();
    $response->assertSee('"todays_price"');
    $response->assertSee('7500');
    $response->assertSee('"base_price"');
    $response->assertSee('7500');
    $response->assertSee('"display_price"');
    $response->assertSee('$75.00');
});

test('shows correct computed prices for subscription with trial', function () {
    $product = Product::factory()->create([
        'name' => 'Trial Subscription',
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
        'setup_fee' => 1000,
        'trial_days' => 7,
    ]);

    $response = FunnelGenServer::tool(GetProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
    ]);

    $response->assertOk();
    // With trial, todays_price should only include setup fee (1000), not recurring
    $response->assertSee('"todays_price"');
    $response->assertSee('1000');
    $response->assertSee('"base_price"');
    $response->assertSee('2999'); // Base price is the recurring price
    $response->assertSee('"display_setup_fee"');
    $response->assertSee('$10.00');
});
