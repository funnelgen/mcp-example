<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Template;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Repositories\TemplateRepository;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Tool;

/**
 * Get Template Tool
 *
 * Retrieves detailed information about a specific funnel template by its ID.
 * This tool handles tenant context switching and uses the TemplateRepository
 * for data access. Returns complete template details including HTML, CSS, and JavaScript content.
 */
class GetTemplateTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('get_template')
            ->for('Get detailed information about a specific template by ID, including HTML, CSS, and JavaScript content')
            ->withObjectParameter(
                name: 'input',
                description: 'Template lookup parameters',
                properties: [
                    new NumberSchema('template_id', 'The ID of the template to fetch'),
                ],
                requiredFields: ['template_id']
            )
            ->using($this);
    }

    /**
     * Execute the tool to retrieve template information.
     *
     * @param  array{template_id: int|string}  $input
     * @return string JSON-encoded template data or error message
     */
    public function __invoke(array $input): string
    {
        SwitchTenantContextAction::run($this->accountId);

        $templateId = (int) $input['template_id'];
        $templateRepository = app(TemplateRepository::class);
        $template = $templateRepository->findById($templateId);

        if (empty($template)) {
            return json_encode([
                'error' => 'Template not found',
                'template_id' => $templateId,
            ]);
        }

        // Return detailed template data
        $templateData = [
            'id' => $template->id,
            'name' => $template->name,
            'account_id' => $template->account_id,
            'html_content' => $template->html_content ?? '',
            'css_content' => $template->css_content ?? '',
            'js_content' => $template->js_content ?? '',
            'is_active' => $template->is_active,
            'is_default' => $template->is_default,
            'created_at' => $template->created_at->toISOString(),
            'updated_at' => $template->updated_at->toISOString(),
        ];

        return json_encode($templateData, JSON_PRETTY_PRINT);
    }
}
