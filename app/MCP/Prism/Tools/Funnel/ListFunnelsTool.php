<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Funnel;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Models\Tenant\Funnel;
use App\Repositories\Tenant\FunnelRepository;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

/**
 * List Funnels Tool
 *
 * Retrieves a list of all available funnels with their details.
 * This tool handles tenant context switching and uses the FunnelRepository
 * for data access.
 */
class ListFunnelsTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('list_funnels')
            ->for('List all available funnels with their details')
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
     * Execute the tool to retrieve all funnels.
     *
     * @param  array<string, mixed>  $input  Empty array since this tool takes no parameters
     * @return string JSON-encoded array of funnels
     */
    public function __invoke(mixed $input = []): string
    {
        SwitchTenantContextAction::run($this->accountId);

        $funnelRepository = app(FunnelRepository::class);
        $funnels = $funnelRepository->getAll();

        // Create a cleaner summary structure for better AI parsing
        $funnelsData = $funnels->map(fn (Funnel $funnel): array => [
            'id' => $funnel->id,
            'name' => $funnel->name,
            'slug' => $funnel->slug,
            'status' => $funnel->status->value,
            'support_email' => $funnel->support_email,
            'language_code' => $funnel->language_code,
            'currency_code' => $funnel->currency_code,
            'template_id' => $funnel->template_id,
            'main_product_id' => $funnel->main_product?->productId,
            'processor_id' => $funnel->payment_processor?->integrationId,
            'tax_enabled' => $funnel->tax_enabled,
            'fulfillment' => $funnel->fulfillment,
            'fulfillment_url' => $funnel->fulfillment_url,
            'bump_offers_count' => count($funnel->bump_offers),
            'created_at' => $funnel->created_at->toISOString(),
            'updated_at' => $funnel->updated_at->toISOString(),
        ])->values();

        $result = json_encode([
            'total' => $funnels->count(),
            'funnels' => $funnelsData,
        ], JSON_PRETTY_PRINT);

        return $result ?: json_encode(['total' => 0, 'funnels' => []]);
    }
}
