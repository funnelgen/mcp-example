<?php

declare(strict_types=1);

use App\Enums\FunnelStatus;
use App\Enums\ProductPricingType;
use App\Mcp\Prism\Tools\Funnel\GetFunnelTool;
use App\Models\Tenant\Funnel;

test('gets a funnel by id', function () {
    $funnel = Funnel::factory()
        ->withMainProduct([
            'name' => 'Main Product',
            'pricing_type' => ProductPricingType::ONETIME,
            'price' => 5000,
        ])
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

    $tool = new GetFunnelTool($this->account->id);
    $response = $tool(['funnel_id' => $funnel->id]);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data['id'])->toBe($funnel->id)
        ->and($data['name'])->toBe('Test Funnel')
        ->and($data['slug'])->toBe('test-funnel')
        ->and($data['status'])->toBe(FunnelStatus::ENABLED->value)
        ->and($data['support_email'])->toBe('support@example.com')
        ->and($data['language_code'])->toBe('en')
        ->and($data['currency_code'])->toBe('USD');
});

test('returns error when funnel not found', function () {
    $tool = new GetFunnelTool($this->account->id);
    $response = $tool(['funnel_id' => 99999]);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Funnel not found')
        ->and($data['funnel_id'])->toBe(99999);
});

test('gets funnel with main product details', function () {
    $funnel = Funnel::factory()
        ->withMainProduct([
            'name' => 'Premium Course',
            'label' => 'Learn Now',
            'pricing_type' => ProductPricingType::ONETIME,
            'price' => 9900,
        ])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Course Funnel',
            'slug' => 'course-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'help@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $tool = new GetFunnelTool($this->account->id);
    $response = $tool(['funnel_id' => $funnel->id]);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data['name'])->toBe('Course Funnel')
        ->and($data)->toHaveKey('main_product')
        ->and($data['main_product'])->toBeInt();
});

test('gets funnel with payment processor', function () {
    $funnel = Funnel::factory()
        ->withMainProduct([
            'pricing_type' => ProductPricingType::ONETIME,
            'price' => 5000,
        ])
        ->withPaymentProcessor()
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Processor Funnel',
            'slug' => 'processor-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $tool = new GetFunnelTool($this->account->id);
    $response = $tool(['funnel_id' => $funnel->id]);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data['name'])->toBe('Processor Funnel')
        ->and($data)->toHaveKey('processor_id')
        ->and($data['processor_id'])->toBeInt();
});

test('gets funnel with tax enabled', function () {
    $funnel = Funnel::factory()
        ->withMainProduct([
            'pricing_type' => ProductPricingType::ONETIME,
            'price' => 5000,
        ])
        ->withTaxEnabled(true)
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Tax Funnel',
            'slug' => 'tax-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $tool = new GetFunnelTool($this->account->id);
    $response = $tool(['funnel_id' => $funnel->id]);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data['name'])->toBe('Tax Funnel')
        ->and($data)->toHaveKey('tax_enabled')
        ->and($data['tax_enabled'])->toBeTrue();
});

test('gets funnel with bump offers', function () {
    $funnel = Funnel::factory()
        ->withMainProduct([
            'name' => 'Main Product',
            'pricing_type' => ProductPricingType::ONETIME,
            'price' => 5000,
        ])
        ->withBumpOffers([
            ['name' => 'Bump 1', 'pricing_type' => ProductPricingType::ONETIME, 'price' => 1000],
            ['name' => 'Bump 2', 'pricing_type' => ProductPricingType::ONETIME, 'price' => 2000],
        ])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Bumps Funnel',
            'slug' => 'bumps-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $tool = new GetFunnelTool($this->account->id);
    $response = $tool(['funnel_id' => $funnel->id]);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data['name'])->toBe('Bumps Funnel')
        ->and($data)->toHaveKey('main_product')
        ->and($data['main_product'])->toBeInt();
});

test('gets funnel with fulfillment redirect', function () {
    $funnel = Funnel::factory()
        ->withMainProduct([
            'pricing_type' => ProductPricingType::ONETIME,
            'price' => 5000,
        ])
        ->withFulfillment('redirect', 'https://example.com/thank-you')
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Redirect Funnel',
            'slug' => 'redirect-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $tool = new GetFunnelTool($this->account->id);
    $response = $tool(['funnel_id' => $funnel->id]);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data['name'])->toBe('Redirect Funnel')
        ->and($data)->toHaveKey('fulfillment')
        ->and($data['fulfillment'])->toBe('redirect')
        ->and($data)->toHaveKey('fulfillment_url')
        ->and($data['fulfillment_url'])->toBe('https://example.com/thank-you');
});
