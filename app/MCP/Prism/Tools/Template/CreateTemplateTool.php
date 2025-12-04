<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Template;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Managers\TemplateManager;
use App\Models\Tenant\FunnelTemplate;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

/**
 * Create Template Tool
 *
 * Creates a new funnel template for checkout pages.
 * Templates can be created with optional HTML, CSS, and JS content, or content can be added later.
 * HTML content must include {{CHECKOUT_COMPONENT}} placeholder for dynamic checkout elements.
 */
class CreateTemplateTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('create_template')
            ->for('Create a new funnel template for checkout pages with optional HTML, CSS, and JavaScript content')
            ->withObjectParameter(
                name: 'input',
                description: 'Template creation parameters',
                properties: [
                    new StringSchema('name', 'The human-readable name of the template'),
                    new StringSchema('html_content', 'The HTML content of the template. Must include {{CHECKOUT_COMPONENT}} placeholder if provided', nullable: true),
                    new StringSchema('css_content', 'The CSS content of the template', nullable: true),
                    new StringSchema('js_content', 'The JavaScript content of the template', nullable: true),
                    new BooleanSchema('is_active', 'Whether the template should be active and available for use. Defaults to true', nullable: true),
                    new BooleanSchema('is_default', 'Whether this should be the default template for the account. Defaults to false', nullable: true),
                ],
                requiredFields: ['name']
            )
            ->using($this);
    }

    /**
     * Execute the tool to create a template.
     *
     * @param  array{name: string, html_content?: string|null, css_content?: string|null, js_content?: string|null, is_active?: bool|null, is_default?: bool|null}  $input
     * @return string JSON-encoded template data or error message
     */
    public function __invoke(array $input): string
    {
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

        SwitchTenantContextAction::run($this->accountId);

        $templateData = [
            'account_id' => $this->accountId,
            'name' => $input['name'],
            'html_content' => $input['html_content'] ?? '',
            'css_content' => $input['css_content'] ?? '',
            'js_content' => $input['js_content'] ?? '',
            'variables' => null,
            'is_active' => $input['is_active'] ?? true,
            'is_default' => $input['is_default'] ?? false,
        ];

        $templateManager = app(TemplateManager::class);
        $template = $templateManager->createTemplate($templateData);

        return json_encode([
            'id' => $template->id,
            'name' => $template->name,
            'account_id' => $template->account_id,
            'is_active' => $template->is_active,
            'is_default' => $template->is_default,
            'created_at' => $template->created_at->toISOString(),
        ], JSON_PRETTY_PRINT);
    }
}
