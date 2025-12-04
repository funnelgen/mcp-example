<?php

declare(strict_types=1);

use App\Enums\ProductPricingType;
use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\UpdateProductTool;
use App\Models\Tenant\Product;

test('updates product name and label', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'label' => 'Old Label',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $response = FunnelGenServer::tool(UpdateProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
        'name' => 'New Name',
        'label' => 'New Label',
    ]);

    $response->assertOk();
    $response->assertSee('"name"');
    $response->assertSee('New Name');
    $response->assertSee('"label"');
    $response->assertSee('New Label');

    $product->refresh();
    expect($product->name)->toBe('New Name');
    expect($product->label)->toBe('New Label');
    expect($product->price)->toBe(5000); // Unchanged
});

test('updates product price', function () {
    $product = Product::factory()->create([
        'name' => 'Test Product',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $response = FunnelGenServer::tool(UpdateProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
        'price' => 7500,
    ]);

    $response->assertOk();
    $response->assertSee('"price"');
    $response->assertSee('7500');
    $response->assertSee('"display_price"');
    $response->assertSee('$75.00');

    $product->refresh();
    expect($product->price)->toBe(7500);
});

test('updates subscription recurring price and billing interval', function () {
    $product = Product::factory()->create([
        'name' => 'Subscription',
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
    ]);

    $response = FunnelGenServer::tool(UpdateProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
        'recurring_price' => 4999,
        'billing_interval' => 'year',
    ]);

    $response->assertOk();
    $response->assertSee('"recurring_price"');
    $response->assertSee('4999');
    $response->assertSee('"billing_interval"');
    $response->assertSee('year');

    $product->refresh();
    expect($product->recurring_price)->toBe(4999);
    expect($product->billing_interval->value)->toBe('year');
});

test('updates subscription setup fee and trial days', function () {
    $product = Product::factory()->create([
        'name' => 'Subscription',
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
        'setup_fee' => 500,
        'trial_days' => 0,
    ]);

    $response = FunnelGenServer::tool(UpdateProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
        'setup_fee' => 1000,
        'trial_days' => 14,
    ]);

    $response->assertOk();
    $response->assertSee('"setup_fee"');
    $response->assertSee('1000');
    $response->assertSee('"trial_days"');
    $response->assertSee('14');

    $product->refresh();
    expect($product->setup_fee)->toBe(1000);
    expect($product->trial_days)->toBe(14);
    expect($product->has_setup_fee)->toBeTrue();
    expect($product->has_trial)->toBeTrue();
});

test('returns error when product not found', function () {
    $response = FunnelGenServer::tool(UpdateProductTool::class, [
        'account_id' => $this->account->id,
        'id' => 99999,
        'name' => 'New Name',
    ]);

    $response->assertHasErrors();
    $response->assertSee('Product with ID 99999 not found.');
});

test('updates only provided fields', function () {
    $product = Product::factory()->create([
        'name' => 'Original Name',
        'label' => 'Original Label',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    // Only update the name
    $response = FunnelGenServer::tool(UpdateProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
        'name' => 'Updated Name',
    ]);

    $response->assertOk();
    $response->assertSee('Updated Name');
    $response->assertSee('Original Label'); // Should remain unchanged

    $product->refresh();
    expect($product->name)->toBe('Updated Name');
    expect($product->label)->toBe('Original Label'); // Unchanged
    expect($product->price)->toBe(5000); // Unchanged
});

test('changes pricing type from one-time to subscription', function () {
    $product = Product::factory()->create([
        'name' => 'Product',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $response = FunnelGenServer::tool(UpdateProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
    ]);

    $response->assertOk();
    $response->assertSee('"pricing_type"');
    $response->assertSee('2');
    $response->assertSee('"is_subscription"');
    $response->assertSee('true');
    $response->assertSee('"recurring_price"');
    $response->assertSee('2999');

    $product->refresh();
    expect($product->pricing_type)->toBe(ProductPricingType::SUBSCRIPTION);
    expect($product->recurring_price)->toBe(2999);
    expect($product->is_subscription)->toBeTrue();
});

test('removes trial period by setting to zero', function () {
    $product = Product::factory()->create([
        'name' => 'Subscription',
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
        'trial_days' => 14,
    ]);

    expect($product->has_trial)->toBeTrue();

    $response = FunnelGenServer::tool(UpdateProductTool::class, [
        'account_id' => $this->account->id,
        'id' => $product->id,
        'trial_days' => 0,
    ]);

    $response->assertOk();
    $response->assertSee('"trial_days"');

    $product->refresh();
    // Setting trial_days to 0 results in null due to empty() check in ProductManager
    expect($product->trial_days)->toBeNull();
    expect($product->has_trial)->toBeFalse();
});
