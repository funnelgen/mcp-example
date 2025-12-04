<?php

declare(strict_types=1);

use App\Enums\ProductPricingType;
use App\Mcp\Prism\Tools\Product\UpdateProductTool;
use App\Models\Tenant\Product;

test('updates product name', function () {
    $product = Product::factory()->create([
        'name' => 'Original Name',
        'label' => 'Original Label',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $updateData = [
        'product_id' => $product->id,
        'name' => 'Updated Name',
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['name'])->toBe('Updated Name')
        ->and($data['product']['label'])->toBe('Original Label'); // Unchanged

    $product->refresh();
    expect($product->name)->toBe('Updated Name')
        ->and($product->label)->toBe('Original Label');
});

test('updates product label', function () {
    $product = Product::factory()->create([
        'name' => 'Product Name',
        'label' => 'Original Label',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $updateData = [
        'product_id' => $product->id,
        'label' => 'New Label',
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['label'])->toBe('New Label');

    $product->refresh();
    expect($product->label)->toBe('New Label');
});

test('updates one-time product price', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $updateData = [
        'product_id' => $product->id,
        'price' => 7500,
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['price'])->toBe(7500);

    $product->refresh();
    expect($product->price)->toBe(7500);
});

test('updates subscription recurring price', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
    ]);

    $updateData = [
        'product_id' => $product->id,
        'recurring_price' => 3999,
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['recurring_price'])->toBe(3999);

    $product->refresh();
    expect($product->recurring_price)->toBe(3999);
});

test('updates subscription billing interval', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
    ]);

    $updateData = [
        'product_id' => $product->id,
        'billing_interval' => 'year',
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['billing_interval'])->toBe('year');

    $product->refresh();
    expect($product->billing_interval->value)->toBe('year');
});

test('updates subscription setup fee', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
        'setup_fee' => 500,
    ]);

    $updateData = [
        'product_id' => $product->id,
        'setup_fee' => 1000,
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['setup_fee'])->toBe(1000);

    $product->refresh();
    expect($product->setup_fee)->toBe(1000);
});

test('updates subscription trial days', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
        'trial_days' => 7,
    ]);

    $updateData = [
        'product_id' => $product->id,
        'trial_days' => 14,
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['trial_days'])->toBe(14);

    $product->refresh();
    expect($product->trial_days)->toBe(14);
});

test('updates multiple fields at once', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'label' => 'Old Label',
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $updateData = [
        'product_id' => $product->id,
        'name' => 'New Name',
        'label' => 'New Label',
        'price' => 7500,
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['name'])->toBe('New Name')
        ->and($data['product']['label'])->toBe('New Label')
        ->and($data['product']['price'])->toBe(7500);

    $product->refresh();
    expect($product->name)->toBe('New Name')
        ->and($product->label)->toBe('New Label')
        ->and($product->price)->toBe(7500);
});

test('returns error when product not found', function () {
    $updateData = [
        'product_id' => 99999,
        'name' => 'New Name',
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Product not found')
        ->and($data['product_id'])->toBe(99999);
});

test('returns error when pricing_type is invalid', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $updateData = [
        'product_id' => $product->id,
        'pricing_type' => 99, // Invalid type
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid pricing_type value')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Pricing type must be 1 (ONETIME) or 2 (SUBSCRIPTION)');
});

test('returns error when billing_interval is invalid', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
    ]);

    $updateData = [
        'product_id' => $product->id,
        'billing_interval' => 'century', // Invalid interval
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid billing_interval')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Billing interval must be day, week, month, or year');
});

test('changes product from one-time to subscription', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $updateData = [
        'product_id' => $product->id,
        'pricing_type' => ProductPricingType::SUBSCRIPTION->value,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['pricing_type'])->toBe(ProductPricingType::SUBSCRIPTION->value)
        ->and($data['product']['recurring_price'])->toBe(2999)
        ->and($data['product']['billing_interval'])->toBe('month')
        ->and($data['product']['is_subscription'])->toBeTrue();

    $product->refresh();
    expect($product->pricing_type)->toBe(ProductPricingType::SUBSCRIPTION)
        ->and($product->is_subscription)->toBeTrue()
        ->and($product->is_one_time)->toBeFalse();
});

test('changes product from subscription to one-time', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
    ]);

    $updateData = [
        'product_id' => $product->id,
        'pricing_type' => ProductPricingType::ONETIME->value,
        'price' => 5000,
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['pricing_type'])->toBe(ProductPricingType::ONETIME->value)
        ->and($data['product']['price'])->toBe(5000)
        ->and($data['product']['is_one_time'])->toBeTrue();

    $product->refresh();
    expect($product->pricing_type)->toBe(ProductPricingType::ONETIME)
        ->and($product->is_one_time)->toBeTrue()
        ->and($product->is_subscription)->toBeFalse();
});

test('updates only specified fields leaving others unchanged', function () {
    $product = Product::factory()->create([
        'name' => 'Original Name',
        'label' => 'Original Label',
        'pricing_type' => ProductPricingType::SUBSCRIPTION,
        'recurring_price' => 2999,
        'billing_interval' => 'month',
        'setup_fee' => 500,
        'trial_days' => 7,
    ]);

    // Only update the name
    $updateData = [
        'product_id' => $product->id,
        'name' => 'Updated Name',
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['product']['name'])->toBe('Updated Name')
        ->and($data['product']['label'])->toBe('Original Label')
        ->and($data['product']['recurring_price'])->toBe(2999)
        ->and($data['product']['billing_interval'])->toBe('month')
        ->and($data['product']['setup_fee'])->toBe(500)
        ->and($data['product']['trial_days'])->toBe(7);
});

test('updates product to have zero price', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $updateData = [
        'product_id' => $product->id,
        'price' => 0,
    ];

    $tool = new UpdateProductTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['product']['price'])->toBe(0)
        ->and($data['product']['display_price'])->toBe('$0.00');

    $product->refresh();
    expect($product->price)->toBe(0);
});
