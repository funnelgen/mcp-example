<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Facades\CurrentAccount;
use App\Models\Tenant\FunnelTemplate;
use App\Repositories\TemplateRepository;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTemplateTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        List all funnel templates for a given account.
        Returns template metadata (id, name, status) without HTML/CSS/JS content.
        Use GetTemplateTool to retrieve full template content.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TemplateRepository $templateRepository): Response
    {
        $request->validate([]);

        $templates = $templateRepository->getShortListByAccount(CurrentAccount::get()->id);

        $templatesData = $templates->map(fn (FunnelTemplate $template): array => [
            'id' => $template->id,
            'name' => $template->name,
        ]);

        return Response::text(json_encode([
            'total' => $templatesData->count(),
            'templates' => $templatesData,
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
            'account_id' => $schema->integer()->description('The account ID to list templates for.'),
        ];
    }
}
