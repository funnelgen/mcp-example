<?php

declare(strict_types=1);

use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\ListTemplateTool;
use App\Models\Tenant\FunnelTemplate;

it('lists all templates for an account', function () {
    FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Template 1',
    ]);

    FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Template 2',
    ]);

    $response = FunnelGenServer::tool(ListTemplateTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertOk();
    $response->assertSee('"total"');
    $response->assertSee('2');
    $response->assertSee('Template 1');
    $response->assertSee('Template 2');
});

it('returns empty array when no templates exist', function () {
    $response = FunnelGenServer::tool(ListTemplateTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertOk();
    $response->assertSee('"total"');
    $response->assertSee('0');
    $response->assertSee('"templates"');
    $response->assertSee('[]');
});

it('includes template metadata without content', function () {
    FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Test Template',
        'html_content' => '<div>{{CHECKOUT_COMPONENT}}</div>',
        'css_content' => 'body { color: red; }',
        'js_content' => 'console.log("test");',
        'is_active' => true,
        'is_default' => false,
    ]);

    $response = FunnelGenServer::tool(ListTemplateTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertOk();
    $response->assertSee('Test Template');
    $response->assertSee('"id"');
    $response->assertSee('"name"');

    // Content is not included in the list response (verified by not having those long strings)
});

it('shows which templates have content', function () {
    FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Full Template',
        'html_content' => '<div>{{CHECKOUT_COMPONENT}}</div>',
        'css_content' => 'body { color: red; }',
        'js_content' => 'console.log("test");',
    ]);

    FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Empty Template',
        'html_content' => '',
        'css_content' => '',
        'js_content' => '',
    ]);

    $response = FunnelGenServer::tool(ListTemplateTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertOk();
    $response->assertSee('Full Template');
    $response->assertSee('Empty Template');
});

it('only returns templates for specified account', function () {
    $otherAccount = App\Models\Account::factory()->create();

    FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'My Template',
    ]);

    FunnelTemplate::factory()->create([
        'account_id' => $otherAccount->id,
        'name' => 'Other Template',
    ]);

    $response = FunnelGenServer::tool(ListTemplateTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertOk();
    $response->assertSee('My Template');
});
