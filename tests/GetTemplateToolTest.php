<?php

declare(strict_types=1);

use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\GetTemplateTool;
use App\Models\Tenant\FunnelTemplate;
use Tests\Fixtures\Templates\GolfTemplate;

test('gets template with all fields', function () {
    $template = FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Test Template',
        'html_content' => GolfTemplate::getHTML(),
        'css_content' => GolfTemplate::getCSS(),
        'js_content' => GolfTemplate::getJavascript(),
        'variables' => ['color' => 'blue', 'font' => 'Arial'],
        'is_active' => true,
        'is_default' => false,
    ]);

    $response = FunnelGenServer::tool(GetTemplateTool::class, ['id' => $template->id, 'account_id' => $this->account->id]);

    $response->assertOk();
    $response->assertSee('"name"');
    $response->assertSee('Test Template');
    $response->assertSee('"account_id"');
    $response->assertSee((string) $this->account->id);
    $response->assertSee('"html_content"');
    $response->assertSee('ProStrike');
    $response->assertSee('"js_content"');
    $response->assertSee('document.querySelect');
});

test('returns error when template not found', function () {
    $response = FunnelGenServer::tool(GetTemplateTool::class, [
        'account_id' => $this->account->id,
        'id' => 99999,
    ]);

    $response->assertHasErrors();
    $response->assertSee('Template with ID 99999 not found.');
});

test('validates required id', function () {
    $response = FunnelGenServer::tool(GetTemplateTool::class, [
        'account_id' => $this->account->id,
    ]);

    $response->assertHasErrors();
});
