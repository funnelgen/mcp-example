<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Template;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Models\Tenant\FunnelTemplate;
use App\Repositories\TemplateRepository;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

/**
 * List Templates Tool
 *
 * Retrieves a list of all available funnel templates with their details.
 * This tool handles tenant context switching and uses the TemplateRepository
 * for data access. Returns template metadata without HTML/CSS/JS content.
 */
class ListTemplatesTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('list_templates')
            ->for('List all available funnel templates with their details')
            ->withObjectParameter(
                name: 'input',
                description: 'Optional parameters',
                properties: [
                    new StringSchema('filter', 'Optional filter (not used, can be omitted)', nullable: true),
                ],
                requiredFields: []
            )
            ->using($this);
    }

    /**
     * Execute the tool to retrieve all templates.
     *
     * @param  array<string, mixed>  $input  Empty array since this tool takes no parameters
     * @return string JSON-encoded array of templates
     */
    public function __invoke(mixed $input = []): string
    {
        SwitchTenantContextAction::run($this->accountId);

        $templateRepository = app(TemplateRepository::class);

        $templates = $templateRepository->getShortListByAccount($this->accountId);

        $templatesData = $templates->map(fn (FunnelTemplate $template): array => [
            'id' => $template->id,
            'name' => $template->name,
        ]);

        $result = json_encode([
            'total' => $templates->count(),
            'templates' => $templatesData,
        ], JSON_PRETTY_PRINT);

        return $result ?: json_encode(['total' => 0, 'templates' => []]);
    }
}
