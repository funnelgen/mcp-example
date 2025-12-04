<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Managers\TemplateManager;
use App\Models\Tenant\FunnelTemplate;
use App\Repositories\TemplateRepository;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTemplateTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Update an existing funnel template's content and configuration.
        Only fields provided will be updated; others remain unchanged.
        HTML content must include {{CHECKOUT_COMPONENT}} placeholder for dynamic checkout elements.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'id' => 'required|integer',
            'name' => 'sometimes|string|max:255',
            'html_content' => 'sometimes|string',
            'css_content' => 'sometimes|string',
            'js_content' => 'sometimes|string',
            'variables' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
        ]);

        $templateRepository = app(TemplateRepository::class);
        $template = $templateRepository->findById($request->get('id'));

        if (!$template) {
            return Response::error("Template with ID {$request->get('id')} not found.");
        }

        // Validate HTML content contains checkout placeholder if html_content is provided
        $htmlContent = $request->get('html_content');

        if ($htmlContent) {
            $decodedHtml = html_entity_decode((string) $htmlContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (stripos($decodedHtml, FunnelTemplate::CHECKOUT_PLACEHOLDER) === false) {
                return Response::error('HTML content must include the placeholder ' . FunnelTemplate::CHECKOUT_PLACEHOLDER . ' for dynamic checkout elements.');
            }
        }

        // Only update fields that were provided
        $updateData = array_filter([
            'name' => $request->get('name'),
            'html_content' => $request->has('html_content') ? $request->get('html_content') : null,
            'css_content' => $request->has('css_content') ? $request->get('css_content') : null,
            'js_content' => $request->has('js_content') ? $request->get('js_content') : null,
            'variables' => $request->get('variables'),
            'is_active' => $request->get('is_active'),
            'is_default' => $request->get('is_default'),
        ], fn (mixed $value): bool => $value !== null);

        $templateManager = app(TemplateManager::class);
        $templateManager->updateTemplate($template, $updateData);

        // Refresh the template to get updated data
        $template->refresh();

        return Response::text(json_encode([
            'id' => $template->id,
            'name' => $template->name,
            'account_id' => $template->account_id,
            'is_active' => $template->is_active,
            'is_default' => $template->is_default,
            'updated_at' => $template->updated_at->toISOString(),
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
            'id' => $schema->integer()->description('The unique identifier of the template to update.')->required(),
            'account_id' => $schema->integer()->description('The account ID that owns the template.'),
            'name' => $schema->string()->description('The human-readable name of the template.'),
            'html_content' => $schema->string()->description('The HTML content of the template.'),
            'css_content' => $schema->string()->description('The CSS content of the template.'),
            'js_content' => $schema->string()->description('The JavaScript content of the template.'),
            'variables' => $schema->array()->description('Array of template variables that can be customized.')->items($schema->object()),
            'is_active' => $schema->boolean()->description('Whether the template should be active and available for use.'),
            'is_default' => $schema->boolean()->description('Whether this should be the default template for the account.'),
        ];
    }
}
