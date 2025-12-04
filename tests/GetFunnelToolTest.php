<?php

declare(strict_types=1);

use App\Enums\FunnelStatus;
use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\GetFunnelTool;
use App\Models\Tenant\Funnel;

it('gets a funnel by id', function () {
    $funnel = Funnel::factory()
        ->withMainProduct()
        ->create([
            'account_id' => $this->account->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel',
            'status' => FunnelStatus::ENABLED,
        ]);

    $response = FunnelGenServer::tool(GetFunnelTool::class, [
        'account_id' => $this->account->id,
        'id' => $funnel->id,
    ]);

    $response->assertOk();
    $response->assertSee('"id"');
    $response->assertSee((string) $funnel->id);
    $response->assertSee('"name"');
    $response->assertSee('Test Funnel');
    $response->assertSee('"slug"');
    $response->assertSee('test-funnel');
    $response->assertSee('"status"');
    $response->assertSee('enabled');
});

it('includes main product details', function () {
    $funnel = Funnel::factory()
        ->withMainProduct()
        ->create(['account_id' => $this->account->id]);

    $response = FunnelGenServer::tool(GetFunnelTool::class, [
        'account_id' => $this->account->id,
        'id' => $funnel->id,
    ]);

    $response->assertOk();
    $response->assertSee('"main_product"');
    $response->assertSee('"id"');
    $response->assertSee('"name"');
    $response->assertSee('"price"');
});

it('includes bump offers', function () {
    $funnel = Funnel::factory()
        ->withMainProduct()
        ->withBumpOffers([
            ['name' => 'Bump 1', 'price' => 1999],
            ['name' => 'Bump 2', 'price' => 2999],
        ])
        ->create(['account_id' => $this->account->id]);

    $response = FunnelGenServer::tool(GetFunnelTool::class, [
        'account_id' => $this->account->id,
        'id' => $funnel->id,
    ]);

    $response->assertOk();
    $response->assertSee('"bump_offers"');
    $response->assertSee('Bump 1');
    $response->assertSee('1999');
    $response->assertSee('Bump 2');
    $response->assertSee('2999');
});

it('includes payment processor details', function () {
    $funnel = Funnel::factory()
        ->withMainProduct()
        ->withPaymentProcessor()
        ->create(['account_id' => $this->account->id]);

    $response = FunnelGenServer::tool(GetFunnelTool::class, [
        'account_id' => $this->account->id,
        'id' => $funnel->id,
    ]);

    $response->assertOk();
    $response->assertSee('"payment_processor"');
    $response->assertSee('"id"');
    $response->assertSee('stripe');
});

it('includes appearance settings', function () {
    $funnel = Funnel::factory()
        ->withMainProduct()
        ->create(['account_id' => $this->account->id]);

    $response = FunnelGenServer::tool(GetFunnelTool::class, [
        'account_id' => $this->account->id,
        'id' => $funnel->id,
    ]);

    $response->assertOk();
    $response->assertSee('"appearance"');
});

it('validates required id', function () {
    $response = FunnelGenServer::tool(GetFunnelTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertHasErrors();
    $response->assertSee('id');
});

it('returns error for non-existent funnel', function () {
    $response = FunnelGenServer::tool(GetFunnelTool::class, [
        'account_id' => $this->account->id,
        'id' => 99999,
    ]);

    $response->assertHasErrors();
});
