<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Repositories\TemplateRepository;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTemplateTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get detailed information about a specific template by ID.
        Returns complete template details including HTML, CSS, and JavaScript content.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'id' => 'required|integer',
        ]);
        $templateId = $request->get('id');

        $templateRepository = app(TemplateRepository::class);
        $template = $templateRepository->findById($templateId);

        if (!$template) {
            return Response::error("Template with ID {$templateId} not found.");
        }

        $templateData = [
            'id' => $template->id,
            'name' => $template->name,
            'account_id' => $template->account_id,
            'html_content' => $template->html_content ? htmlspecialchars($template->html_content, ENT_QUOTES, 'UTF-8') : '',
            'css_content' => $template->css_content ? htmlspecialchars($template->css_content, ENT_QUOTES, 'UTF-8') : '',
            'js_content' => $template->js_content ? htmlspecialchars($template->js_content, ENT_QUOTES, 'UTF-8') : '',
            'is_active' => $template->is_active,
        ];

        return Response::text(json_encode($templateData, JSON_PRETTY_PRINT));
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
            'account_id' => $schema->integer()->description('The account ID associated with the template.'),
            'id' => $schema->integer()->description('The unique identifier of the funnel template.')->required(),
        ];
    }
}
