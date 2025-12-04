<?php

declare(strict_types=1);

use App\Enums\FunnelStatus;
use App\Enums\ProductPricingType;
use App\Mcp\Prism\Tools\Funnel\UpdateFunnelTool;
use App\Models\Tenant\Funnel;
use App\Models\Tenant\Product;

test('updates funnel name', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 9900,
    ]);

    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Original Funnel',
            'slug' => 'original-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'name' => 'Updated Funnel Name',
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['name'])->toBe('Updated Funnel Name')
        ->and($data['funnel']['slug'])->toBe('original-funnel'); // Unchanged

    $funnel->refresh();
    expect($funnel->name)->toBe('Updated Funnel Name')
        ->and($funnel->slug)->toBe('original-funnel');
});

test('updates funnel slug', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'original-slug',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'slug' => 'updated-slug',
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['slug'])->toBe('updated-slug');

    $funnel->refresh();
    expect($funnel->slug)->toBe('updated-slug');
});

test('updates funnel status', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'status' => FunnelStatus::ENABLED->value,
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['status'])->toBe(FunnelStatus::ENABLED->value);

    $funnel->refresh();
    expect($funnel->status)->toBe(FunnelStatus::ENABLED);
});

test('updates funnel support email', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'old@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'support_email' => 'new@example.com',
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['support_email'])->toBe('new@example.com');

    $funnel->refresh();
    expect($funnel->support_email)->toBe('new@example.com');
});

test('updates funnel language and currency codes', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'language_code' => 'es',
        'currency_code' => 'EUR',
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['language_code'])->toBe('es')
        ->and($data['funnel']['currency_code'])->toBe('EUR');

    $funnel->refresh();
    expect($funnel->language_code)->toBe('es')
        ->and($funnel->currency_code)->toBe('EUR');
});

test('updates funnel main product', function () {
    $originalProduct = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 5000,
    ]);

    $newProduct = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 7500,
    ]);

    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 5000])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'main_product_id' => $newProduct->id,
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['main_product'])->toBe($newProduct->id);

    $funnel->refresh();
    expect($funnel->main_product?->productId)->toBe($newProduct->id);
});

test('updates funnel bump offers', function () {
    $mainProduct = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 9900,
    ]);

    $bumpProduct1 = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 1900,
    ]);

    $bumpProduct2 = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 2900,
    ]);

    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'bump_offer_ids' => [$bumpProduct1->id, $bumpProduct2->id],
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue();

    $funnel->refresh();
    $bumpOfferIds = array_map(fn ($offer) => $offer->productId, $funnel->bump_offers);
    expect($bumpOfferIds)->toContain($bumpProduct1->id)
        ->and($bumpOfferIds)->toContain($bumpProduct2->id);
});

test('updates funnel tax enabled setting', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    // Test enabling tax
    $updateData = [
        'funnel_id' => $funnel->id,
        'tax_enabled' => true,
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['tax_enabled'])->toBeTrue();

    $funnel->refresh();
    expect($funnel->tax_enabled)->toBeTrue();

    // Test disabling tax
    $updateData = [
        'funnel_id' => $funnel->id,
        'tax_enabled' => false,
    ];

    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['tax_enabled'])->toBeFalse();

    $funnel->refresh();
    expect($funnel->tax_enabled)->toBeFalse();
});

test('updates funnel fulfillment settings', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'fulfillment' => 'redirect',
        'fulfillment_url' => 'https://example.com/thank-you',
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['fulfillment'])->toBe('redirect')
        ->and($data['funnel']['fulfillment_url'])->toBe('https://example.com/thank-you');

    $funnel->refresh();
    expect($funnel->fulfillment)->toBe('redirect')
        ->and($funnel->fulfillment_url)->toBe('https://example.com/thank-you');
});

test('updates multiple fields at once', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'old@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $newProduct = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 7500,
    ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'name' => 'Updated Name',
        'slug' => 'updated-slug',
        'status' => FunnelStatus::ENABLED->value,
        'support_email' => 'new@example.com',
        'main_product_id' => $newProduct->id,
        'tax_enabled' => true,
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['name'])->toBe('Updated Name')
        ->and($data['funnel']['slug'])->toBe('updated-slug')
        ->and($data['funnel']['status'])->toBe(FunnelStatus::ENABLED->value)
        ->and($data['funnel']['support_email'])->toBe('new@example.com')
        ->and($data['funnel']['main_product'])->toBe($newProduct->id)
        ->and($data['funnel']['tax_enabled'])->toBeTrue();

    $funnel->refresh();
    expect($funnel->name)->toBe('Updated Name')
        ->and($funnel->slug)->toBe('updated-slug')
        ->and($funnel->status)->toBe(FunnelStatus::ENABLED)
        ->and($funnel->support_email)->toBe('new@example.com')
        ->and($funnel->main_product?->productId)->toBe($newProduct->id)
        ->and($funnel->tax_enabled)->toBeTrue();
});

test('returns error when funnel not found', function () {
    $updateData = [
        'funnel_id' => 99999,
        'name' => 'New Name',
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Funnel not found')
        ->and($data['funnel_id'])->toBe(99999);
});

test('returns error when status is invalid', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'status' => 99, // Invalid status
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid status value')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Status must be 0 (DISABLED) or 1 (ENABLED)');
});

test('returns error when fulfillment is invalid', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'fulfillment' => 'invalid_type', // Invalid fulfillment
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid fulfillment value')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Fulfillment must be "invoice" or "redirect"');
});

test('returns error when fulfillment_url is missing for redirect', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'fulfillment' => 'redirect',
        // Missing fulfillment_url
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Missing fulfillment_url')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('fulfillment_url is required when fulfillment is "redirect"');
});

test('updates only specified fields leaving others unchanged', function () {
    $product = Product::factory()->create([
        'pricing_type' => ProductPricingType::ONETIME,
        'price' => 9900,
    ]);

    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'original@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    // Only update the name
    $updateData = [
        'funnel_id' => $funnel->id,
        'name' => 'Updated Name',
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['funnel']['name'])->toBe('Updated Name')
        ->and($data['funnel']['slug'])->toBe('original-slug')
        ->and($data['funnel']['status'])->toBe(FunnelStatus::ENABLED->value)
        ->and($data['funnel']['support_email'])->toBe('original@example.com')
        ->and($data['funnel']['language_code'])->toBe('en')
        ->and($data['funnel']['currency_code'])->toBe('USD');

    $funnel->refresh();
    expect($funnel->name)->toBe('Updated Name')
        ->and($funnel->slug)->toBe('original-slug')
        ->and($funnel->status)->toBe(FunnelStatus::ENABLED)
        ->and($funnel->support_email)->toBe('original@example.com');
});

test('sets template_id when provided', function () {
    $funnel = Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 9900])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
            'template_id' => null,
        ]);

    $updateData = [
        'funnel_id' => $funnel->id,
        'template_id' => 123,
    ];

    $tool = new UpdateFunnelTool($this->account->id);
    $response = $tool($updateData);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['funnel']['template_id'])->toBe(123);

    $funnel->refresh();
    expect($funnel->template_id)->toBe(123);
});
