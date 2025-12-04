<?php

declare(strict_types=1);

use App\Mcp\Resources\TemplateSchemaResource;
use App\Mcp\Servers\FunnelGenServer;

test('provides template schema as JSON', function () {
    $response = FunnelGenServer::resource(TemplateSchemaResource::class);

    $response->assertOk();
    $response->assertSee('"type": "object"');
    $response->assertSee('"properties"');
    $response->assertSee('"id"');
    $response->assertSee('"account_id"');
    $response->assertSee('"name"');
    $response->assertSee('"html_content"');
    $response->assertSee('"css_content"');
    $response->assertSee('"js_content"');
    $response->assertSee('"variables"');
    $response->assertSee('"is_active"');
    $response->assertSee('"is_default"');
    $response->assertSee('"required"');
    $response->assertSee('"account_id"');
    $response->assertSee('"name"');
    $response->assertSee('"html_content"');
    $response->assertSee('"css_content"');
    $response->assertSee('"js_content"');
});
