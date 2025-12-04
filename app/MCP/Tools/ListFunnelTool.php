<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Tenant\Funnel;
use App\Repositories\Tenant\FunnelRepository;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListFunnelTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        List all funnels for a given account.
        Returns funnel details including products, templates, and configuration.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, FunnelRepository $funnelRepository): Response
    {
        $request->validate([]);

        /* @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant\Funnel> $funnels */
        $funnels = $funnelRepository->getAll();

        // Load relationships for all funnels
        $funnels->load(['funnelData', 'template']);

        $funnelsData = $funnels->map(fn (Funnel $funnel): array => [
            'id' => $funnel->id,
            'account_id' => $funnel->account_id,
            'name' => $funnel->name,
            'slug' => $funnel->slug,
            'status' => match ($funnel->status) {
                \App\Enums\FunnelStatus::DISABLED => 'disabled',
                \App\Enums\FunnelStatus::ENABLED => 'enabled',
            },
            'status_label' => $funnel->status->getLabel(),
            'support_email' => $funnel->support_email,
            'language_code' => $funnel->language_code,
            'currency_code' => $funnel->currency_code,
            'main_product_id' => $funnel->main_product?->productId,
            'bump_offers_count' => count($funnel->bump_offers),
            'tax_enabled' => $funnel->tax_enabled,
            'processor_id' => $funnel->payment_processor?->integrationId,
            'template_id' => $funnel->template_id,
            'template_name' => $funnel->template?->name,
            'fulfillment' => $funnel->fulfillment,
            'created_at' => $funnel->created_at->toISOString(),
            'updated_at' => $funnel->updated_at->toISOString(),
        ]);

        return Response::text(json_encode([
            'total' => $funnelsData->count(),
            'funnels' => $funnelsData,
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
            'account_id' => $schema->integer()->description('The account ID to list funnels for.'),
        ];
    }
}
