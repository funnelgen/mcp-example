<?php

declare(strict_types=1);

use App\Enums\FunnelStatus;
use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\CreateFunnelTool;
use App\Models\Tenant\Funnel;
use App\Models\Tenant\Product;

beforeEach(function () {
    $this->mainProduct = Product::factory()->create();
    $this->bumpProduct1 = Product::factory()->create();
    $this->bumpProduct2 = Product::factory()->create();
});

it('creates a basic funnel', function () {
    $initialCount = Funnel::count();

    $response = FunnelGenServer::tool(CreateFunnelTool::class, [
        'account_id' => $this->account->id,
        'name' => 'Test Funnel',
        'slug' => 'test-funnel',
        'main_product_id' => $this->mainProduct->id,
    ]);

    $response->assertOk();
    $response->assertSee('"name"');
    $response->assertSee('Test Funnel');
    $response->assertSee('"slug"');
    $response->assertSee('test-funnel');
    $response->assertSee('"status"');
    $response->assertSee('"main_product_id"');
    $response->assertSee((string) $this->mainProduct->id);

    expect(Funnel::count())->toBe($initialCount + 1);

    $funnel = Funnel::latest()->first();
    expect($funnel)
        ->not->toBeNull()
        ->account_id->toBe($this->account->id)
        ->name->toBe('Test Funnel')
        ->slug->toBe('test-funnel')
        ->status->toBe(FunnelStatus::ENABLED);
});

it('creates a funnel with custom status', function () {
    $response = FunnelGenServer::tool(CreateFunnelTool::class, [
        'account_id' => $this->account->id,
        'name' => 'Enabled Funnel',
        'slug' => 'enabled-funnel',
        'main_product_id' => $this->mainProduct->id,
        'status' => 'enabled',
    ]);

    $response->assertOk();
    $response->assertSee('"status"');
    $response->assertSee('enabled');

    $funnel = Funnel::latest()->first();
    expect($funnel->status)->toBe(FunnelStatus::ENABLED);
});

it('creates a funnel with bump offers', function () {
    $response = FunnelGenServer::tool(CreateFunnelTool::class, [
        'account_id' => $this->account->id,
        'name' => 'Funnel with Bumps',
        'slug' => 'funnel-with-bumps',
        'main_product_id' => $this->mainProduct->id,
        'bump_offer_ids' => [$this->bumpProduct1->id, $this->bumpProduct2->id],
    ]);

    $response->assertOk();
    $response->assertSee('"bump_offers"');
    $response->assertSee((string) $this->bumpProduct1->id);
    $response->assertSee((string) $this->bumpProduct2->id);
});

it('validates required name', function () {
    $response = FunnelGenServer::tool(CreateFunnelTool::class, [
        'account_id' => $this->account->id,
        'slug' => 'test-funnel',
        'main_product_id' => $this->mainProduct->id,
    ]);

    $response->assertHasErrors();
    $response->assertSee('name');
});

it('validates required slug', function () {
    $response = FunnelGenServer::tool(CreateFunnelTool::class, [
        'account_id' => $this->account->id,
        'name' => 'Test Funnel',
        'main_product_id' => $this->mainProduct->id,
    ]);

    $response->assertHasErrors();
    $response->assertSee('slug');
});

it('validates required main_product_id', function () {
    $response = FunnelGenServer::tool(CreateFunnelTool::class, [
        'account_id' => $this->account->id,
        'name' => 'Test Funnel',
        'slug' => 'test-funnel',
    ]);

    $response->assertHasErrors();
    $response->assertSee('main product id');
});

it('validates status enum values', function () {
    $response = FunnelGenServer::tool(CreateFunnelTool::class, [
        'account_id' => $this->account->id,
        'name' => 'Test Funnel',
        'slug' => 'test-funnel',
        'main_product_id' => $this->mainProduct->id,
        'status' => 'invalid_status',
    ]);

    $response->assertHasErrors();
    $response->assertSee('status');
});

it('validates bump_offer_ids is an array', function () {
    $response = FunnelGenServer::tool(CreateFunnelTool::class, [
        'account_id' => $this->account->id,
        'name' => 'Test Funnel',
        'slug' => 'test-funnel',
        'main_product_id' => $this->mainProduct->id,
        'bump_offer_ids' => 'not-an-array',
    ]);

    $response->assertHasErrors();
    $response->assertSee('bump offer ids');
});

it('validates maximum of 5 bump offers', function () {
    $bump1 = Product::factory()->create();
    $bump2 = Product::factory()->create();
    $bump3 = Product::factory()->create();
    $bump4 = Product::factory()->create();
    $bump5 = Product::factory()->create();
    $bump6 = Product::factory()->create();

    $response = FunnelGenServer::tool(CreateFunnelTool::class, [
        'account_id' => $this->account->id,
        'name' => 'Test Funnel',
        'slug' => 'test-funnel',
        'main_product_id' => $this->mainProduct->id,
        'bump_offer_ids' => [
            $bump1->id,
            $bump2->id,
            $bump3->id,
            $bump4->id,
            $bump5->id,
            $bump6->id,
        ],
    ]);

    $response->assertHasErrors();
    $response->assertSee('bump offer ids');
});
