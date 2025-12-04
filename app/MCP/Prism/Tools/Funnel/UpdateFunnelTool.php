<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Funnel;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Enums\FunnelStatus;
use App\Managers\Tenant\FunnelManager;
use App\Repositories\Tenant\FunnelRepository;
use App\ValueObjects\FunnelProduct;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

/**
 * Update Funnel Tool
 *
 * Updates an existing funnel's details and configuration.
 * Only fields provided will be updated; others remain unchanged.
 */
class UpdateFunnelTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('update_funnel')
            ->for('Update an existing funnel\'s details and configuration. Only provided fields will be updated.')
            ->withObjectParameter(
                name: 'input',
                description: 'Funnel update parameters',
                properties: [
                    new NumberSchema('funnel_id', 'The unique identifier of the funnel to update'),
                    new StringSchema('name', 'The name of the funnel', nullable: true),
                    new StringSchema('slug', 'The URL-friendly slug for the funnel', nullable: true),
                    new NumberSchema('status', 'Status of the funnel: 0=DISABLED, 1=ENABLED', nullable: true),
                    new StringSchema('support_email', 'Support email address for the funnel', nullable: true),
                    new StringSchema('language_code', 'Language code (e.g., "en", "es")', nullable: true),
                    new StringSchema('currency_code', 'Currency code (e.g., "USD", "EUR")', nullable: true),
                    new NumberSchema('main_product_id', 'The ID of the main product', nullable: true),
                    new ArraySchema('bump_offer_ids', 'Array of bump offer product IDs (max 5)', new NumberSchema('id', 'Product ID'), nullable: true),
                    new BooleanSchema('tax_enabled', 'Whether tax is enabled', nullable: true),
                    new NumberSchema('processor_id', 'Payment processor integration ID', nullable: true),
                    new NumberSchema('template_id', 'Template ID for the funnel', nullable: true),
                    new StringSchema('fulfillment', 'Fulfillment type: "invoice" or "redirect"', nullable: true),
                    new StringSchema('fulfillment_url', 'Fulfillment URL (required if fulfillment is "redirect")', nullable: true),
                ],
                requiredFields: [
                    'funnel_id',
                ]
            )
            ->using($this);
    }

    /**
     * Execute the tool to update an existing funnel.
     *
     * @param  array{
     *     funnel_id: int,
     *     name?: string,
     *     slug?: string,
     *     status?: int,
     *     support_email?: string,
     *     language_code?: string,
     *     currency_code?: string,
     *     main_product_id?: int,
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
            $funnelId = (int) $input['funnel_id'];
            $funnelRepository = app(FunnelRepository::class);
            $funnel = $funnelRepository->findById($funnelId);

            if (empty($funnel)) {
                return json_encode([
                    'error' => 'Funnel not found',
                    'funnel_id' => $funnelId,
                ]);
            }

            // Validate status if provided
            $status = $funnel->status;

            if (isset($input['status'])) {
                $newStatus = FunnelStatus::tryFrom($input['status']);

                if ($newStatus === null) {
                    return json_encode([
                        'error' => 'Invalid status value',
                        'message' => 'Status must be 0 (DISABLED) or 1 (ENABLED)',
                    ]);
                }
                $status = $newStatus;
            }

            // Validate fulfillment if provided
            if (isset($input['fulfillment']) && !empty($input['fulfillment']) && !in_array($input['fulfillment'], ['invoice', 'redirect'])) {
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

            // Get current funnel data to use as defaults
            $currentMainProductId = $funnel->main_product->productId ?? 0;
            $currentBumpOfferIds = array_map(fn (FunnelProduct $offer): int => $offer->productId, $funnel->bump_offers);
            $currentTaxEnabled = $funnel->tax_enabled;
            $currentProcessorId = $funnel->payment_processor?->integrationId;

            $funnelManager = app(FunnelManager::class);

            $updatedFunnel = $funnelManager->updateFunnel(
                funnelId: $funnelId,
                name: $input['name'] ?? $funnel->name,
                slug: $input['slug'] ?? $funnel->slug,
                status: $status,
                supportEmail: $input['support_email'] ?? $funnel->support_email,
                languageCode: $input['language_code'] ?? $funnel->language_code,
                currencyCode: $input['currency_code'] ?? $funnel->currency_code,
                mainProductId: $input['main_product_id'] ?? $currentMainProductId,
                bumpOfferIds: $input['bump_offer_ids'] ?? $currentBumpOfferIds,
                taxEnabled: $input['tax_enabled'] ?? $currentTaxEnabled,
                processorId: $input['processor_id'] ?? $currentProcessorId,
                templateId: $input['template_id'] ?? $funnel->template_id,
                fulfillment: $input['fulfillment'] ?? null,
                fulfillmentUrl: $input['fulfillment_url'] ?? null,
            );

            return json_encode([
                'success' => true,
                'message' => 'Funnel updated successfully',
                'funnel' => $updatedFunnel->toArray(),
            ]);
        }
        catch (\Throwable $e) {
            return json_encode([
                'error' => 'Failed to update funnel',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
