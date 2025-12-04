<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Facades\CurrentAccount;
use App\Managers\TemplateManager;
use App\Models\Tenant\FunnelTemplate;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTemplateTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Create a new funnel template for checkout pages.
        Templates can be created with optional HTML, CSS, and JS content, or content can be added later using UpdateTemplateTool.
        HTML content must include {{CHECKOUT_COMPONENT}} placeholder for dynamic checkout elements.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'html_content' => 'nullable|string',
            'css_content' => 'nullable|string',
            'js_content' => 'nullable|string',
            'variables' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        // Validate HTML content contains checkout placeholder if html_content is provided
        $htmlContent = $request->get('html_content');

        if ($htmlContent) {
            $decodedHtml = html_entity_decode((string) $htmlContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (stripos($decodedHtml, FunnelTemplate::CHECKOUT_PLACEHOLDER) === false) {
                return Response::error('HTML content must include the placeholder ' . FunnelTemplate::CHECKOUT_PLACEHOLDER . ' for dynamic checkout elements.');
            }
        }

        $templateData = [
            'account_id' => CurrentAccount::get()->id,
            'name' => $request->get('name'),
            'html_content' => $request->get('html_content', ''),
            'css_content' => $request->get('css_content', ''),
            'js_content' => $request->get('js_content', ''),
            'variables' => $request->get('variables'),
            'is_active' => $request->get('is_active', true),
            'is_default' => $request->get('is_default', false),
        ];

        $templateManager = app(TemplateManager::class);
        $template = $templateManager->createTemplate($templateData);

        return Response::text(json_encode([
            'id' => $template->id,
            'name' => $template->name,
            'account_id' => $template->account_id,
            'is_active' => $template->is_active,
            'is_default' => $template->is_default,
            'created_at' => $template->created_at->toISOString(),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('The account ID that will own the template.'),
            'name' => $schema->string()->description('The human-readable name of the template.')->required(),
            'html_content' => $schema->string()->description('The HTML content of the template. Optional - can be updated later.'),
            'css_content' => $schema->string()->description('The CSS content of the template. Optional - can be updated later.'),
            'js_content' => $schema->string()->description('The JavaScript content of the template. Optional - can be updated later.'),
            'variables' => $schema->array()->description('Optional array of template variables that can be customized.')->items($schema->object()),
            'is_active' => $schema->boolean()->description('Whether the template should be active and available for use. Defaults to true.'),
            'is_default' => $schema->boolean()->description('Whether this should be the default template for the account. Defaults to false.'),
        ];
    }
}
