<?php

declare(strict_types=1);

use App\Enums\FunnelStatus;
use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\UpdateFunnelTool;
use App\Models\Tenant\Funnel;
use App\Models\Tenant\Product;

beforeEach(function () {
    $this->funnel = Funnel::factory()
        ->withMainProduct()
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'status' => FunnelStatus::ENABLED,
        ]);
});

it('updates funnel name', function () {
    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'id' => $this->funnel->id,
        'account_id' => $this->account->id,
        'name' => 'Updated Name',
    ]);

    $response->assertOk();
    $response->assertSee('Updated Name');

    expect($this->funnel->fresh()->name)->toBe('Updated Name');
});

it('updates funnel slug', function () {
    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'id' => $this->funnel->id,
        'account_id' => $this->account->id,
        'slug' => 'updated-slug',
    ]);

    $response->assertOk();
    $response->assertSee('updated-slug');

    expect($this->funnel->fresh()->slug)->toBe('updated-slug');
});

it('updates funnel status', function () {
    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'id' => $this->funnel->id,
        'account_id' => $this->account->id,
        'status' => 'enabled',
    ]);

    $response->assertOk();
    $response->assertSee('enabled');

    expect($this->funnel->fresh()->status)->toBe(FunnelStatus::ENABLED);
});

it('updates main product', function () {
    $newProduct = Product::factory()->create();

    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'id' => $this->funnel->id,
        'account_id' => $this->account->id,
        'main_product_id' => $newProduct->id,
    ]);

    $response->assertOk();
    $response->assertSee('"main_product_id"');
    $response->assertSee((string) $newProduct->id);
});

it('updates bump offers', function () {
    $bump1 = Product::factory()->create();
    $bump2 = Product::factory()->create();

    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'id' => $this->funnel->id,
        'account_id' => $this->account->id,
        'bump_offer_ids' => [$bump1->id, $bump2->id],
    ]);

    $response->assertOk();
    $response->assertSee('"bump_offers"');
    $response->assertSee((string) $bump1->id);
    $response->assertSee((string) $bump2->id);
});

it('updates multiple fields at once', function () {
    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'id' => $this->funnel->id,
        'account_id' => $this->account->id,
        'name' => 'New Name',
        'slug' => 'new-slug',
        'status' => 'enabled',
    ]);

    $response->assertOk();
    $response->assertSee('New Name');
    $response->assertSee('new-slug');
    $response->assertSee('enabled');

    $updated = $this->funnel->fresh();
    expect($updated)
        ->name->toBe('New Name')
        ->slug->toBe('new-slug')
        ->status->toBe(FunnelStatus::ENABLED);
});

it('validates required id', function () {
    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'account_id' => $this->account->id,
        'name' => 'Updated Name',
    ]);

    $response->assertHasErrors();
    $response->assertSee('id');
});

it('validates status enum values', function () {
    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'id' => $this->funnel->id,
        'account_id' => $this->account->id,
        'status' => 'invalid_status',
    ]);

    $response->assertHasErrors();
    $response->assertSee('status');
});

it('validates bump_offer_ids is an array', function () {
    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'id' => $this->funnel->id,
        'account_id' => $this->account->id,
        'bump_offer_ids' => 'not-an-array',
    ]);

    $response->assertHasErrors();
    $response->assertSee('bump offer ids');
});

it('validates maximum of 5 bump offers', function () {
    $bumps = collect(range(1, 6))->map(fn () => Product::factory()->create()->id);

    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'id' => $this->funnel->id,
        'account_id' => $this->account->id,
        'bump_offer_ids' => $bumps->toArray(),
    ]);

    $response->assertHasErrors();
    $response->assertSee('bump offer ids');
});

it('returns error for non-existent funnel', function () {
    $response = FunnelGenServer::tool(UpdateFunnelTool::class, [
        'id' => 99999,
        'account_id' => $this->account->id,
        'name' => 'Updated Name',
    ]);

    $response->assertHasErrors();
});
