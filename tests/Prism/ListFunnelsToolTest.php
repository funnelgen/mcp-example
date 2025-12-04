<?php

declare(strict_types=1);

use App\Enums\FunnelStatus;
use App\Enums\ProductPricingType;
use App\Mcp\Prism\Tools\Funnel\ListFunnelsTool;
use App\Models\Tenant\Funnel;

test('lists all funnels for an account', function () {
    // Create test funnels
    Funnel::factory()
        ->withMainProduct([
            'name' => 'Product One',
            'pricing_type' => ProductPricingType::ONETIME,
            'price' => 5000,
        ])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Funnel One',
            'slug' => 'funnel-one',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support1@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    Funnel::factory()
        ->withMainProduct([
            'name' => 'Product Two',
            'pricing_type' => ProductPricingType::SUBSCRIPTION,
            'recurring_price' => 2999,
            'billing_interval' => 'month',
        ])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Funnel Two',
            'slug' => 'funnel-two',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support2@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    Funnel::factory()
        ->withMainProduct([
            'name' => 'Product Three',
            'pricing_type' => ProductPricingType::ONETIME,
            'price' => 9999,
        ])
        ->withBumpOffers([
            ['pricing_type' => ProductPricingType::ONETIME, 'price' => 1000],
        ])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Funnel Three',
            'slug' => 'funnel-three',
            'status' => FunnelStatus::DISABLED,
            'support_email' => 'support3@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $tool = new ListFunnelsTool($this->account->id);
    $response = $tool([]);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('total')
        ->and($data['total'])->toBe(3)
        ->and($data)->toHaveKey('funnels')
        ->and($data['funnels'])->toBeArray()
        ->and($data['funnels'])->toHaveCount(3);

    // Verify first funnel
    expect($data['funnels'][0])->toHaveKey('name')
        ->and($data['funnels'][0])->toHaveKey('slug')
        ->and($data['funnels'][0])->toHaveKey('status')
        ->and($data['funnels'][0])->toHaveKey('main_product_id')
        ->and($data['funnels'][0])->toHaveKey('support_email')
        ->and($data['funnels'][0])->toHaveKey('language_code')
        ->and($data['funnels'][0])->toHaveKey('currency_code');
});

test('returns empty list when no funnels exist', function () {
    $tool = new ListFunnelsTool($this->account->id);
    $response = $tool([]);
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('total')
        ->and($data['total'])->toBe(0)
        ->and($data)->toHaveKey('funnels')
        ->and($data['funnels'])->toBeArray()
        ->and($data['funnels'])->toBeEmpty();
});

test('shows correct status for different funnel states', function () {
    Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 1000])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Disabled Funnel',
            'slug' => 'disabled-funnel',
            'status' => FunnelStatus::DISABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 1000])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Live Funnel',
            'slug' => 'live-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $tool = new ListFunnelsTool($this->account->id);
    $response = $tool([]);
    $data = json_decode($response, true);

    expect($data['total'])->toBe(2);

    $statuses = array_column($data['funnels'], 'status');
    expect($statuses)->toContain(FunnelStatus::DISABLED->value)
        ->and($statuses)->toContain(FunnelStatus::ENABLED->value);
});

test('shows bump offers count for funnels', function () {
    Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 5000])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'No Bumps Funnel',
            'slug' => 'no-bumps',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 5000])
        ->withBumpOffers([
            ['pricing_type' => ProductPricingType::ONETIME, 'price' => 1000],
            ['pricing_type' => ProductPricingType::ONETIME, 'price' => 2000],
            ['pricing_type' => ProductPricingType::ONETIME, 'price' => 3000],
        ])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Three Bumps Funnel',
            'slug' => 'three-bumps',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $tool = new ListFunnelsTool($this->account->id);
    $response = $tool([]);
    $data = json_decode($response, true);

    expect($data['total'])->toBe(2);

    // Find the funnel with no bumps
    $noBumpsFunnel = collect($data['funnels'])->firstWhere('name', 'No Bumps Funnel');
    expect($noBumpsFunnel)->toHaveKey('bump_offers_count')
        ->and($noBumpsFunnel['bump_offers_count'])->toBe(0);

    // Find the funnel with three bumps
    $threeBumpsFunnel = collect($data['funnels'])->firstWhere('name', 'Three Bumps Funnel');
    expect($threeBumpsFunnel)->toHaveKey('bump_offers_count')
        ->and($threeBumpsFunnel['bump_offers_count'])->toBe(3);
});

test('shows processor id when available', function () {
    Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 5000])
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

    $tool = new ListFunnelsTool($this->account->id);
    $response = $tool([]);
    $data = json_decode($response, true);

    expect($data['total'])->toBe(1)
        ->and($data['funnels'][0])->toHaveKey('processor_id')
        ->and($data['funnels'][0]['processor_id'])->toBeInt();
});

test('shows tax enabled status', function () {
    Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 5000])
        ->withTaxEnabled(true)
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Tax Enabled Funnel',
            'slug' => 'tax-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    Funnel::factory()
        ->withMainProduct(['pricing_type' => ProductPricingType::ONETIME, 'price' => 5000])
        ->create([
            'account_id' => $this->account->id,
            'name' => 'No Tax Funnel',
            'slug' => 'no-tax-funnel',
            'status' => FunnelStatus::ENABLED,
            'support_email' => 'support@example.com',
            'language_code' => 'en',
            'currency_code' => 'USD',
            'owner' => $this->user->id,
        ]);

    $tool = new ListFunnelsTool($this->account->id);
    $response = $tool([]);
    $data = json_decode($response, true);

    expect($data['total'])->toBe(2);

    $taxFunnel = collect($data['funnels'])->firstWhere('name', 'Tax Enabled Funnel');
    expect($taxFunnel)->toHaveKey('tax_enabled')
        ->and($taxFunnel['tax_enabled'])->toBeTrue();

    $noTaxFunnel = collect($data['funnels'])->firstWhere('name', 'No Tax Funnel');
    expect($noTaxFunnel)->toHaveKey('tax_enabled')
        ->and($noTaxFunnel['tax_enabled'])->toBeFalse();
});
