<?php

declare(strict_types=1);

use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\UpdateTemplateTool;
use App\Models\Tenant\FunnelTemplate;

test('updates a template with partial data', function () {
    $template = FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Original Template',
        'html_content' => '<div>Original {{CHECKOUT_COMPONENT}}</div>',
        'css_content' => 'body { color: red; }',
        'js_content' => 'console.log("original");',
        'is_active' => true,
        'is_default' => false,
    ]);

    $updateData = [
        'id' => $template->id,
        'account_id' => $this->account->id,
        'name' => 'Updated Template',
        'is_active' => false,
    ];

    $response = FunnelGenServer::tool(UpdateTemplateTool::class, $updateData);

    $response->assertOk();
    $response->assertSee('Updated Template');
    $response->assertSee('false'); // is_active
    $response->assertSee('"updated_at"');

    // Verify in database
    $template->refresh();
    expect($template->name)->toBe('Updated Template');
    expect($template->is_active)->toBe(false);
    // Other fields should remain unchanged
    expect($template->html_content)->toBe('<div>Original {{CHECKOUT_COMPONENT}}</div>');
    expect($template->css_content)->toBe('body { color: red; }');
    expect($template->js_content)->toBe('console.log("original");');
});

test('updates a template with all fields', function () {
    $template = FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Original Template',
        'html_content' => '<div>Original {{CHECKOUT_COMPONENT}}</div>',
        'css_content' => 'body { color: red; }',
        'js_content' => 'console.log("original");',
        'variables' => [],
        'is_active' => true,
        'is_default' => false,
    ]);

    $updateData = [
        'id' => $template->id,
        'account_id' => $this->account->id,
        'name' => 'Fully Updated Template',
        'html_content' => '<div>Updated {{CHECKOUT_COMPONENT}} {{ PRODUCT_NAME }}</div>',
        'css_content' => 'body { color: blue; }',
        'js_content' => 'console.log("updated");',
        'is_active' => false,
        'is_default' => true,
    ];

    $response = FunnelGenServer::tool(UpdateTemplateTool::class, $updateData);

    $response->assertOk();
    $response->assertSee('Fully Updated Template');
    $response->assertSee('false'); // is_active
    $response->assertSee('true'); // is_default

    // Verify in database
    $template->refresh();
    expect($template->name)->toBe('Fully Updated Template');
    expect($template->html_content)->toBe('<div>Updated {{CHECKOUT_COMPONENT}} {{ PRODUCT_NAME }}</div>');
    expect($template->css_content)->toBe('body { color: blue; }');
    expect($template->js_content)->toBe('console.log("updated");');
    expect($template->variables)->toBe(['PRODUCT_NAME']);
    expect($template->is_active)->toBe(false);
    expect($template->is_default)->toBe(true);
});

test('returns error for non-existent template', function () {
    $updateData = [
        'id' => 99999, // Non-existent ID
        'account_id' => $this->account->id,
        'name' => 'Should Not Work',
    ];

    $response = FunnelGenServer::tool(UpdateTemplateTool::class, $updateData);

    $response->assertHasErrors();
    $response->assertSee('Template with ID 99999 not found');
});

test('returns error when updating html_content without checkout placeholder', function () {
    $template = FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Original Template',
        'html_content' => '<div>Original {{CHECKOUT_COMPONENT}}</div>', // Has placeholder initially
        'css_content' => 'body { color: red; }',
        'js_content' => 'console.log("original");',
    ]);

    $updateData = [
        'id' => $template->id,
        'account_id' => $this->account->id,
        'html_content' => '<div>Updated without placeholder</div>', // Missing placeholder
    ];

    $response = FunnelGenServer::tool(UpdateTemplateTool::class, $updateData);

    $response->assertHasErrors();
    $response->assertSee('HTML content must include the placeholder {{CHECKOUT_COMPONENT}} for dynamic checkout elements.');

    // Verify template was not updated
    $template->refresh();
    expect($template->html_content)->toBe('<div>Original {{CHECKOUT_COMPONENT}}</div>');
});

test('decodes HTML entities when updating template', function () {
    $template = FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Original Template',
        'html_content' => '<div>Original {{CHECKOUT_COMPONENT}}</div>',
        'css_content' => 'body { color: red; }',
        'js_content' => 'console.log("original");',
    ]);

    $encodedHtml = '&lt;!DOCTYPE html&gt;&lt;html&gt;&lt;body&gt;&lt;h1&gt;Updated&lt;/h1&gt;&lt;div&gt;{{CHECKOUT_COMPONENT}}&lt;/div&gt;&lt;/body&gt;&lt;/html&gt;';
    $expectedDecodedHtml = '<!DOCTYPE html><html><body><h1>Updated</h1><div>{{CHECKOUT_COMPONENT}}</div></body></html>';

    $updateData = [
        'id' => $template->id,
        'account_id' => $this->account->id,
        'html_content' => $encodedHtml,
        'css_content' => '.class &gt; div { color: blue; }',
        'js_content' => 'const msg = &quot;updated&quot;;',
    ];

    $response = FunnelGenServer::tool(UpdateTemplateTool::class, $updateData);

    $response->assertOk();

    // Verify the HTML was decoded and stored properly
    $template->refresh();
    expect($template->html_content)->toBe($expectedDecodedHtml);
    expect($template->css_content)->toBe('.class > div { color: blue; }');
    expect($template->js_content)->toBe('const msg = "updated";');
});

test('sanitizes malicious HTML when updating template', function () {
    $template = FunnelTemplate::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Original Template',
        'html_content' => '<div>Original {{CHECKOUT_COMPONENT}}</div>',
    ]);

    $maliciousHtml = '<h1>Updated</h1><div>{{CHECKOUT_COMPONENT}}</div><script>alert("XSS")</script><img src=x onerror="alert()">';

    $updateData = [
        'id' => $template->id,
        'account_id' => $this->account->id,
        'html_content' => $maliciousHtml,
    ];

    $response = FunnelGenServer::tool(UpdateTemplateTool::class, $updateData);

    $response->assertOk();

    // Verify dangerous content was removed
    $template->refresh();
    expect($template->html_content)->not->toContain('<script>');
    expect($template->html_content)->not->toContain('onerror');
    expect($template->html_content)->toContain('{{CHECKOUT_COMPONENT}}');
    expect($template->html_content)->toContain('<h1>Updated</h1>');
});
