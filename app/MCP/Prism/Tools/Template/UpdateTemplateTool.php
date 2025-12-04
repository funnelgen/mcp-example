<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Template;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Managers\TemplateManager;
use App\Models\Tenant\FunnelTemplate;
use App\Repositories\TemplateRepository;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

/**
 * Update Template Tool
 *
 * Updates an existing funnel template's content and configuration.
 * Only fields provided will be updated; others remain unchanged.
 * HTML content must include {{CHECKOUT_COMPONENT}} placeholder for dynamic checkout elements.
 */
class UpdateTemplateTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('update_template')
            ->for('Update an existing funnel template\'s content and configuration. Only provided fields will be updated')
            ->withObjectParameter(
                name: 'input',
                description: 'Template update parameters',
                properties: [
                    new NumberSchema('template_id', 'The unique identifier of the template to update'),
                    new StringSchema('name', 'The human-readable name of the template', nullable: true),
                    new StringSchema('html_content', 'The HTML content of the template. Must include {{CHECKOUT_COMPONENT}} placeholder', nullable: true),
                    new StringSchema('css_content', 'The CSS content of the template', nullable: true),
                    new StringSchema('js_content', 'The JavaScript content of the template', nullable: true),
                    new BooleanSchema('is_active', 'Whether the template should be active and available for use', nullable: true),
                    new BooleanSchema('is_default', 'Whether this should be the default template for the account', nullable: true),
                ],
                requiredFields: ['template_id']
            )
            ->using($this);
    }

    /**
     * Execute the tool to update a template.
     *
     * @param  array{template_id: int|string, name?: string|null, html_content?: string|null, css_content?: string|null, js_content?: string|null, is_active?: bool|null, is_default?: bool|null}  $input
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

        // Validate HTML content contains checkout placeholder if html_content is provided
        $htmlContent = $input['html_content'] ?? null;

        if ($htmlContent) {
            $decodedHtml = html_entity_decode($htmlContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (stripos($decodedHtml, FunnelTemplate::CHECKOUT_PLACEHOLDER) === false) {
                return json_encode([
                    'error' => 'HTML content must include the placeholder ' . FunnelTemplate::CHECKOUT_PLACEHOLDER . ' for dynamic checkout elements.',
                ]);
            }
        }

        // Build update data - only include fields that were provided
        $updateData = [];

        if (isset($input['name'])) {
            $updateData['name'] = $input['name'];
        }

        if (isset($input['html_content'])) {
            $updateData['html_content'] = $input['html_content'];
        }

        if (isset($input['css_content'])) {
            $updateData['css_content'] = $input['css_content'];
        }

        if (isset($input['js_content'])) {
            $updateData['js_content'] = $input['js_content'];
        }

        if (isset($input['is_active'])) {
            $updateData['is_active'] = $input['is_active'];
        }

        if (isset($input['is_default'])) {
            $updateData['is_default'] = $input['is_default'];
        }

        if (empty($updateData)) {
            return json_encode([
                'error' => 'No fields provided to update',
                'template_id' => $templateId,
            ]);
        }

        $templateManager = app(TemplateManager::class);
        $templateManager->updateTemplate($template, $updateData);

        // Refresh the template to get updated data
        $template->refresh();

        return json_encode([
            'id' => $template->id,
            'name' => $template->name,
            'account_id' => $template->account_id,
            'is_active' => $template->is_active,
            'is_default' => $template->is_default,
            'updated_at' => $template->updated_at->toISOString(),
        ], JSON_PRETTY_PRINT);
    }
}
