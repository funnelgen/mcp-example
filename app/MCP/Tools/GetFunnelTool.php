<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Repositories\Tenant\FunnelRepository;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetFunnelTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get detailed information about a specific funnel by ID.
        Returns complete funnel details including products, template, processor, and appearance settings.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, FunnelRepository $funnelRepository): Response
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $funnel = $funnelRepository->findById($request->get('id'));

        if (!$funnel) {
            return Response::error("Funnel with ID {$request->get('id')} not found.");
        }

        // Load template relationship
        $funnel->load('template');

        // Load product details
        $mainProduct = null;

        if ($funnel->main_product?->productId) {
            $mainProduct = \App\Models\Tenant\Product::find($funnel->main_product->productId);
        }

        $bumpProducts = [];

        foreach ($funnel->bump_offers as $offer) {
            if ($offer->productId) {
                $product = \App\Models\Tenant\Product::find($offer->productId);

                if ($product) {
                    $bumpProducts[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price ?? $product->recurring_price,
                        'product_id' => $offer->productId,
                        'type' => $offer->type->value,
                        'stripe_product_id' => $offer->stripeProductId,
                        'stripe_price_id' => $offer->stripePriceId,
                    ];
                }
            }
        }

        $funnelData = [
            'id' => $funnel->id,
            'account_id' => $funnel->account_id,
            'name' => $funnel->name,
            'slug' => $funnel->slug,
            'status' => match ($funnel->status) {
                \App\Enums\FunnelStatus::DISABLED => 'disabled',
                \App\Enums\FunnelStatus::ENABLED => 'enabled',
            },
            'status_label' => $funnel->status->getLabel(),
            'owner' => $funnel->owner,
            'support_email' => $funnel->support_email,
            'language_code' => $funnel->language_code,
            'currency_code' => $funnel->currency_code,
            'main_product' => $mainProduct ? [
                'id' => $mainProduct->id,
                'name' => $mainProduct->name,
                'price' => $mainProduct->price ?? $mainProduct->recurring_price,
                'product_id' => $funnel->main_product?->productId,
                'stripe_product_id' => $funnel->main_product?->stripeProductId,
                'stripe_price_id' => $funnel->main_product?->stripePriceId,
            ] : null,
            'bump_offers' => $bumpProducts,
            'tax_enabled' => $funnel->tax_enabled,
            'payment_processor' => $funnel->payment_processor ? [
                'integration_id' => $funnel->payment_processor->integrationId,
                'account_id' => $funnel->payment_processor->accountId,
                'type' => $funnel->payment_processor->type->value,
                'is_sandbox' => $funnel->payment_processor->isSandbox,
            ] : null,
            'template' => $funnel->template ? [
                'id' => $funnel->template->id,
                'name' => $funnel->template->name,
            ] : null,
            'fulfillment' => $funnel->fulfillment,
            'fulfillment_url' => $funnel->fulfillment_url,
            'appearance' => [
                'theme' => $funnel->appearance->theme->value,
                'font_family' => $funnel->appearance->fontFamily,
                'font_size_base' => $funnel->appearance->fontSizeBase,
                'color_background' => $funnel->appearance->colorBackground,
                'color_primary' => $funnel->appearance->colorPrimary,
                'color_primary_text' => $funnel->appearance->colorPrimaryText,
                'color_text' => $funnel->appearance->colorText,
                'color_danger' => $funnel->appearance->colorDanger,
                'spacing_unit' => $funnel->appearance->spacingUnit,
                'border_radius' => $funnel->appearance->borderRadius,
            ],
            'created_at' => $funnel->created_at->toISOString(),
            'updated_at' => $funnel->updated_at->toISOString(),
        ];

        return Response::text(json_encode($funnelData, JSON_PRETTY_PRINT));
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
            'account_id' => $schema->integer()->description('The account ID associated with the funnel.'),
            'id' => $schema->integer()->description('The unique identifier of the funnel.')->required(),
        ];
    }
}
