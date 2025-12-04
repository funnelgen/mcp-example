<?php

declare(strict_types=1);

use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\CreateTemplateTool;
use App\Models\Tenant\FunnelTemplate;

test('creates a new template with required fields', function () {
    $initialCount = FunnelTemplate::where('account_id', $this->account->id)->count();

    $templateData = [
        'account_id' => $this->account->id,
        'name' => 'New Test Template',
        'html_content' => '<div>Hello World {{CHECKOUT_COMPONENT}}</div>',
        'css_content' => 'body { color: red; }',
        'js_content' => 'console.log("Hello");',
    ];

    $response = FunnelGenServer::tool(CreateTemplateTool::class, $templateData);

    $response->assertOk();
    $response->assertSee('"name"');
    $response->assertSee('New Test Template');
    $response->assertSee('"account_id"');
    $response->assertSee((string) $this->account->id);
    $response->assertSee('"is_active"');
    $response->assertSee('true');
    $response->assertSee('"is_default"');
    $response->assertSee('false');
    $response->assertSee('"created_at"');

    // Verify the template was actually created in the database
    $finalCount = FunnelTemplate::where('account_id', $this->account->id)->count();
    expect($finalCount)->toBe($initialCount + 1);

    $template = FunnelTemplate::where('account_id', $this->account->id)
        ->where('name', 'New Test Template')
        ->first();
    expect($template)->not->toBeNull();
    expect($template->html_content)->toBe('<div>Hello World {{CHECKOUT_COMPONENT}}</div>');
    expect($template->css_content)->toBe('body { color: red; }');
    expect($template->js_content)->toBe('console.log("Hello");');
});

test('creates a template with optional fields', function () {
    $templateData = [
        'account_id' => $this->account->id,
        'name' => 'Custom Template',
        'html_content' => '<div>Custom {{CHECKOUT_COMPONENT}}</div>',
        'css_content' => '.custom { color: blue; }',
        'js_content' => 'alert("Custom");',
        'variables' => ['color' => 'blue', 'size' => 'large'],
        'is_active' => true,
        'is_default' => false,
    ];

    $response = FunnelGenServer::tool(CreateTemplateTool::class, $templateData);

    $response->assertOk();
    $response->assertSee('Custom Template');
    $response->assertSee('false'); // is_active
    $response->assertSee('true'); // is_default

    // Verify in database
    $template = FunnelTemplate::where('account_id', $this->account->id)
        ->where('name', 'Custom Template')
        ->first();
    expect($template)->not()->toBeNull();
    expect($template->html_content)->toBe('<div>Custom {{CHECKOUT_COMPONENT}}</div>');
    expect($template->is_active)->toBe(true);
    expect($template->is_default)->toBe(false);
});

test('decodes HTML entities when creating template', function () {
    $encodedHtml = '&lt;!DOCTYPE html&gt;&lt;html&gt;&lt;head&gt;&lt;title&gt;Test&lt;/title&gt;&lt;/head&gt;&lt;body&gt;&lt;div&gt;{{CHECKOUT_COMPONENT}}&lt;/div&gt;&lt;/body&gt;&lt;/html&gt;';
    $expectedDecodedHtml = '<!DOCTYPE html><html><head><title>Test</title></head><body><div>{{CHECKOUT_COMPONENT}}</div></body></html>';

    $templateData = [
        'account_id' => $this->account->id,
        'name' => 'Encoded HTML Template',
        'html_content' => $encodedHtml,
        'css_content' => 'body &gt; div { color: red; }',
        'js_content' => 'console.log(&quot;Hello&quot;);',
    ];

    $response = FunnelGenServer::tool(CreateTemplateTool::class, $templateData);

    $response->assertOk();

    // Verify the HTML was decoded and stored properly
    $template = FunnelTemplate::where('account_id', $this->account->id)
        ->where('name', 'Encoded HTML Template')
        ->first();

    expect($template)->not->toBeNull();
    expect($template->html_content)->toBe($expectedDecodedHtml);
    expect($template->css_content)->toBe('body > div { color: red; }');
    expect($template->js_content)->toBe('console.log("Hello");');
});

test('sanitizes malicious HTML when creating template', function () {
    $maliciousHtml = '<div>{{CHECKOUT_COMPONENT}}</div><script>alert("XSS")</script><div onclick="alert()">Click me</div>';
    $expectedSanitizedHtml = '<div>{{CHECKOUT_COMPONENT}}</div><div>Click me</div>';

    $templateData = [
        'account_id' => $this->account->id,
        'name' => 'Malicious HTML Template',
        'html_content' => $maliciousHtml,
    ];

    $response = FunnelGenServer::tool(CreateTemplateTool::class, $templateData);

    $response->assertOk();

    // Verify dangerous content was removed
    $template = FunnelTemplate::where('account_id', $this->account->id)
        ->where('name', 'Malicious HTML Template')
        ->first();

    expect($template)->not->toBeNull();
    expect($template->html_content)->not->toContain('<script>');
    expect($template->html_content)->not->toContain('onclick');
    expect($template->html_content)->toContain('{{CHECKOUT_COMPONENT}}');
    expect($template->html_content)->toContain('<div>Click me</div>');
});

test('sanitizes malicious CSS when creating template', function () {
    $maliciousCss = 'body { color: red; background: url(javascript:alert("XSS")); } div { behavior: url(xss.htc); }';

    $templateData = [
        'account_id' => $this->account->id,
        'name' => 'Malicious CSS Template',
        'html_content' => '<div>{{CHECKOUT_COMPONENT}}</div>',
        'css_content' => $maliciousCss,
    ];

    $response = FunnelGenServer::tool(CreateTemplateTool::class, $templateData);

    $response->assertOk();

    // Verify dangerous CSS was removed
    $template = FunnelTemplate::where('account_id', $this->account->id)
        ->where('name', 'Malicious CSS Template')
        ->first();

    expect($template)->not->toBeNull();
    expect($template->css_content)->not->toContain('javascript:');
    expect($template->css_content)->not->toContain('behavior:');
    expect($template->css_content)->toContain('color: red');
});
