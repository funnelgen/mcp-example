<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Funnel;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Enums\FunnelStatus;
use App\Facades\CurrentAccount;
use App\Managers\Tenant\FunnelManager;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

/**
 * Create Funnel Tool
 *
 * Creates a new funnel with products and configuration.
 * This tool handles tenant context switching and uses the FunnelManager
 * for data persistence.
 */
class CreateFunnelTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('create_funnel')
            ->for('Create a new funnel with products and configuration')
            ->withObjectParameter(
                name: 'input',
                description: 'Funnel creation parameters',
                properties: [
                    new StringSchema('name', 'The name of the funnel'),
                    new StringSchema('slug', 'The URL-friendly slug for the funnel'),
                    new NumberSchema('status', 'Status of the funnel: 0=DISABLED, 1=ENABLED'),
                    new StringSchema('support_email', 'Support email address for the funnel'),
                    new StringSchema('language_code', 'Language code (e.g., "en", "es")'),
                    new StringSchema('currency_code', 'Currency code (e.g., "USD", "EUR")'),
                    new NumberSchema('main_product_id', 'The ID of the main product'),
                    new ArraySchema('bump_offer_ids', 'Array of bump offer product IDs (max 5)', new NumberSchema('id', 'Product ID'), nullable: true),
                    new BooleanSchema('tax_enabled', 'Whether tax is enabled', nullable: true),
                    new NumberSchema('processor_id', 'Payment processor integration ID', nullable: true),
                    new NumberSchema('template_id', 'Template ID for the funnel', nullable: true),
                    new StringSchema('fulfillment', 'Fulfillment type: "invoice" or "redirect"', nullable: true),
                    new StringSchema('fulfillment_url', 'Fulfillment URL (required if fulfillment is "redirect")', nullable: true),
                ],
                requiredFields: [
                    'name',
                    'slug',
                    'status',
                    'support_email',
                    'language_code',
                    'currency_code',
                    'main_product_id',
                ]
            )
            ->using($this);
    }

    /**
     * Execute the tool to create a new funnel.
     *
     * @param  array{
     *     name: string,
     *     slug: string,
     *     status: int,
     *     support_email: string,
     *     language_code: string,
     *     currency_code: string,
     *     main_product_id: int,
     *     bump_offer_ids?: array<int>,
     *     tax_enabled?: bool,
     *     processor_id?: int,
     *     template_id?: int,
     *     fulfillment?: string,
     *     fulfillment_url?: string
     * }  $input
     * @return string JSON-encoded funnel data or error message
     */
    public function __invoke(array $input): string
    {
        SwitchTenantContextAction::run($this->accountId);

        try {
            // Validate status
            $status = FunnelStatus::tryFrom((int) $input['status']);

            if ($status === null) {
                return json_encode([
                    'error' => 'Invalid status value',
                    'message' => 'Status must be 0 (DISABLED) or 1 (ENABLED)',
                ]);
            }

            // Validate fulfillment
            if (!empty($input['fulfillment']) && !in_array($input['fulfillment'], ['invoice', 'redirect'])) {
                return json_encode([
                    'error' => 'Invalid fulfillment value',
                    'message' => 'Fulfillment must be "invoice" or "redirect"',
                ]);
            }

            if (isset($input['fulfillment']) && $input['fulfillment'] === 'redirect' && empty($input['fulfillment_url'])) {
                return json_encode([
                    'error' => 'Missing fulfillment_url',
                    'message' => 'fulfillment_url is required when fulfillment is "redirect"',
                ]);
            }

            $funnelManager = app(FunnelManager::class);
            $account = CurrentAccount::get();

            $funnel = $funnelManager->createFunnel(
                accountId: $this->accountId,
                name: $input['name'],
                slug: $input['slug'],
                status: $status,
                owner: $account->owner,
                supportEmail: $input['support_email'],
                languageCode: $input['language_code'],
                currencyCode: $input['currency_code'],
                mainProductId: (int) $input['main_product_id'],
                bumpOfferIds: $input['bump_offer_ids'] ?? [],
                taxEnabled: $input['tax_enabled'] ?? false,
                processorId: $input['processor_id'] ?? null,
                templateId: $input['template_id'] ?? null,
                fulfillment: $input['fulfillment'] ?? null,
                fulfillmentUrl: $input['fulfillment_url'] ?? null,
            );

            return json_encode([
                'success' => true,
                'message' => 'Funnel created successfully',
                'funnel' => $funnel->toArray(),
            ]);
        }
        catch (\Throwable $e) {
            return json_encode([
                'error' => 'Failed to create funnel',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
