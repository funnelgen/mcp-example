<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class TemplateSchemaResource extends Resource
{
    /**
     * The resource's URI.
     */
    protected string $uri = 'funnelgen://resources/template-schema';

    /**
     * The resource's MIME type.
     */
    protected string $mimeType = 'application/json';

    /**
     * The resource's name.
     */
    protected string $name = 'template-schema';

    /**
     * The resource's title.
     */
    protected string $title = 'Template Schema';

    /**
     * The resource's description.
     */
    protected string $description = <<<'MARKDOWN'
        Template structure reference for creating funnel templates.
        Shows all available fields and their purposes.
        Use this to understand template anatomy before creating new templates.
    MARKDOWN;

    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Unique identifier for the template'
                ],
                'account_id' => [
                    'type' => 'integer',
                    'description' => 'ID of the account that owns this template'
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Human-readable name of the template'
                ],
                'html_content' => [
                    'type' => 'string',
                    'description' => 'HTML content of the template'
                ],
                'css_content' => [
                    'type' => 'string',
                    'description' => 'CSS content of the template'
                ],
                'js_content' => [
                    'type' => 'string',
                    'description' => 'JavaScript content of the template'
                ],
                'variables' => [
                    'type' => 'array',
                    'description' => 'Array of template variables that can be customized',
                    'items' => [
                        'type' => 'object'
                    ]
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Whether the template is active and available for use'
                ],
                'is_default' => [
                    'type' => 'boolean',
                    'description' => 'Whether this is the default template for the account'
                ]
            ],
            'required' => ['account_id', 'name']
        ];

        return Response::text(json_encode($schema, JSON_PRETTY_PRINT));
    }
}
