<?php

declare(strict_types=1);

use App\Enums\FunnelStatus;
use App\Enums\ProductPricingType;
use App\Mcp\Prism\Tools\Funnel\CreateFunnelTool;
use App\Models\Tenant\Funnel;
use App\Models\Tenant\Product;

test('creates a basic funnel with main product', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 9900,
    ]);

    $initialCount = Funnel::count();

    $funnelData = [
        'name' => 'Test Funnel',
        'slug' => 'test-funnel',
        'status' => FunnelStatus::ENABLED->value,
        'support_email' => 'support@example.com',
        'language_code' => 'en',
        'currency_code' => 'USD',
        'main_product_id' => $product->id,
    ];

    $tool = new CreateFunnelTool($this->account->id);
    $response = $tool($funnelData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toBe('Funnel created successfully')
        ->and($data)->toHaveKey('funnel')
        ->and($data['funnel'])->toBeArray()
        ->and($data['funnel']['name'])->toBe('Test Funnel')
        ->and($data['funnel']['slug'])->toBe('test-funnel')
        ->and($data['funnel']['status'])->toBe(FunnelStatus::ENABLED->value);

    // Verify the funnel was created in the database
    $finalCount = Funnel::count();
    expect($finalCount)->toBe($initialCount + 1);

    $funnel = Funnel::where('name', 'Test Funnel')->first();
    expect($funnel)->not->toBeNull()
        ->and($funnel->slug)->toBe('test-funnel')
        ->and($funnel->status)->toBe(FunnelStatus::ENABLED);
});

test('creates funnel with bump offers', function () {
    $mainProduct = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $bumpProduct1 = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 1000,
    ]);

    $bumpProduct2 = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 2000,
    ]);

    $funnelData = [
        'name' => 'Funnel with Bumps',
        'slug' => 'funnel-with-bumps',
        'status' => FunnelStatus::ENABLED->value,
        'support_email' => 'support@example.com',
        'language_code' => 'en',
        'currency_code' => 'USD',
        'main_product_id' => $mainProduct->id,
        'bump_offer_ids' => [$bumpProduct1->id, $bumpProduct2->id],
    ];

    $tool = new CreateFunnelTool($this->account->id);
    $response = $tool($funnelData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['name'])->toBe('Funnel with Bumps');

    $funnel = Funnel::where('name', 'Funnel with Bumps')->first();
    expect($funnel)->not->toBeNull();
    $funnel->load('funnelData');
    expect($funnel->bump_offers)->toHaveCount(2);
});

test('creates funnel with tax enabled', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $funnelData = [
        'name' => 'Tax Funnel',
        'slug' => 'tax-funnel',
        'status' => FunnelStatus::ENABLED->value,
        'support_email' => 'support@example.com',
        'language_code' => 'en',
        'currency_code' => 'USD',
        'main_product_id' => $product->id,
        'tax_enabled' => true,
    ];

    $tool = new CreateFunnelTool($this->account->id);
    $response = $tool($funnelData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue();

    $funnel = Funnel::where('name', 'Tax Funnel')->first();
    expect($funnel)->not->toBeNull();
    $funnel->load('funnelData');
    expect($funnel->tax_enabled)->toBeTrue();
});

test('creates funnel with redirect fulfillment', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $funnelData = [
        'name' => 'Redirect Funnel',
        'slug' => 'redirect-funnel',
        'status' => FunnelStatus::ENABLED->value,
        'support_email' => 'support@example.com',
        'language_code' => 'en',
        'currency_code' => 'USD',
        'main_product_id' => $product->id,
        'fulfillment' => 'redirect',
        'fulfillment_url' => 'https://example.com/thank-you',
    ];

    $tool = new CreateFunnelTool($this->account->id);
    $response = $tool($funnelData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue();

    $funnel = Funnel::where('name', 'Redirect Funnel')->first();
    expect($funnel)->not->toBeNull();
    $funnel->load('funnelData');
    expect($funnel->fulfillment)->toBe('redirect')
        ->and($funnel->fulfillment_url)->toBe('https://example.com/thank-you');
});

test('creates funnel with invoice fulfillment', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $funnelData = [
        'name' => 'Invoice Funnel',
        'slug' => 'invoice-funnel',
        'status' => FunnelStatus::ENABLED->value,
        'support_email' => 'support@example.com',
        'language_code' => 'en',
        'currency_code' => 'USD',
        'main_product_id' => $product->id,
        'fulfillment' => 'invoice',
    ];

    $tool = new CreateFunnelTool($this->account->id);
    $response = $tool($funnelData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue();

    $funnel = Funnel::where('name', 'Invoice Funnel')->first();
    expect($funnel)->not->toBeNull();
    $funnel->load('funnelData');
    expect($funnel->fulfillment)->toBe('invoice');
});

test('returns error when status is invalid', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $funnelData = [
        'name' => 'Invalid Status Funnel',
        'slug' => 'invalid-status',
        'status' => 99, // Invalid status
        'support_email' => 'support@example.com',
        'language_code' => 'en',
        'currency_code' => 'USD',
        'main_product_id' => $product->id,
    ];

    $tool = new CreateFunnelTool($this->account->id);
    $response = $tool($funnelData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid status value')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Status must be');
});

test('returns error when fulfillment type is invalid', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $funnelData = [
        'name' => 'Invalid Fulfillment Funnel',
        'slug' => 'invalid-fulfillment',
        'status' => FunnelStatus::ENABLED->value,
        'support_email' => 'support@example.com',
        'language_code' => 'en',
        'currency_code' => 'USD',
        'main_product_id' => $product->id,
        'fulfillment' => 'email', // Invalid fulfillment type
    ];

    $tool = new CreateFunnelTool($this->account->id);
    $response = $tool($funnelData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid fulfillment value')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Fulfillment must be');
});

test('returns error when redirect fulfillment missing url', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $funnelData = [
        'name' => 'Missing URL Funnel',
        'slug' => 'missing-url',
        'status' => FunnelStatus::ENABLED->value,
        'support_email' => 'support@example.com',
        'language_code' => 'en',
        'currency_code' => 'USD',
        'main_product_id' => $product->id,
        'fulfillment' => 'redirect',
        // Missing fulfillment_url
    ];

    $tool = new CreateFunnelTool($this->account->id);
    $response = $tool($funnelData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Missing fulfillment_url')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('required when fulfillment is "redirect"');
});

test('creates funnel with all status types', function () {
    $statuses = [
        ['status' => FunnelStatus::DISABLED, 'name' => 'Disabled Funnel'],
        ['status' => FunnelStatus::DISABLED, 'name' => 'Test Funnel'],
        ['status' => FunnelStatus::ENABLED, 'name' => 'Draft Funnel'],
        ['status' => FunnelStatus::ENABLED, 'name' => 'Live Funnel'],
    ];

    foreach ($statuses as $statusConfig) {
        $product = Product::factory()->create([
            'pricing_type' => ProductPricingType::ONETIME,
            'price' => 5000,
        ]);

        $funnelData = [
            'name' => $statusConfig['name'],
            'slug' => Illuminate\Support\Str::slug($statusConfig['name']),
            'status' => $statusConfig['status']->value,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'main_product_id' => $product->id,
        ];

        $tool = new CreateFunnelTool($this->account->id);
        $response = $tool($funnelData);
        $data = json_decode($response, true);

        expect($data)->toHaveKey('success')
            ->and($data['success'])->toBeTrue()
            ->and($data['funnel']['status'])->toBe($statusConfig['status']->value);

        $funnel = Funnel::where('name', $statusConfig['name'])->first();
        expect($funnel)->not->toBeNull()
            ->and($funnel->status)->toBe($statusConfig['status']);
    }
});

test('creates funnel with maximum bump offers', function () {
    $mainProduct = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $bumpProducts = [];

    for ($i = 0; $i < 5; $i++) {
        $bumpProducts[] = Product::factory()->create([
            'pricing_type' => ProductPricingType::ONETIME,
            'price' => 1000 * ($i + 1),
        ])->id;
    }

    $funnelData = [
        'name' => 'Max Bumps Funnel',
        'slug' => 'max-bumps-funnel',
        'status' => FunnelStatus::ENABLED->value,
        'support_email' => 'support@example.com',
        'language_code' => 'en',
        'currency_code' => 'USD',
        'main_product_id' => $mainProduct->id,
        'bump_offer_ids' => $bumpProducts,
    ];

    $tool = new CreateFunnelTool($this->account->id);
    $response = $tool($funnelData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue();

    $funnel = Funnel::where('name', 'Max Bumps Funnel')->first();
    expect($funnel)->not->toBeNull();
    $funnel->load('funnelData');
    expect($funnel->bump_offers)->toHaveCount(5);
});
