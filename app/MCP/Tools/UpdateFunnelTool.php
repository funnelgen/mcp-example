<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\FunnelStatus;
use App\Managers\Tenant\FunnelManager;
use App\Repositories\Tenant\FunnelRepository;
use App\ValueObjects\FunnelProduct;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateFunnelTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Update an existing funnel's details, products, and configuration.
        Only fields provided will be updated; others remain unchanged.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, FunnelManager $funnelManager, FunnelRepository $funnelRepository): Response
    {
        $request->validate([
            'id' => 'required|integer',
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:disabled,enabled',
            'support_email' => 'sometimes|email|max:255',
            'language_code' => 'sometimes|string|size:2',
            'currency_code' => 'sometimes|string|size:3',
            'main_product_id' => 'sometimes|integer',
            'bump_offer_ids' => 'sometimes|array|max:5',
            'bump_offer_ids.*' => 'integer',
            'tax_enabled' => 'sometimes|boolean',
            'processor_id' => 'sometimes|integer',
            'template_id' => 'sometimes|integer',
            'fulfillment' => 'sometimes|string|in:invoice,redirect',
            'fulfillment_url' => 'sometimes|string|url',
            'user_id' => 'sometimes|integer',
        ]);

        // Check if funnel exists
        $funnel = $funnelRepository->findById($request->get('id'));

        if (!$funnel) {
            return Response::error("Funnel with ID {$request->get('id')} not found.");
        }

        // Map string status to enum if provided
        $statusMap = [
            'disabled' => FunnelStatus::DISABLED,
            'enabled' => FunnelStatus::ENABLED,
        ];
        $status = $request->has('status') ? ($statusMap[$request->get('status')] ?? $funnel->status) : $funnel->status;

        // Prepare update parameters using existing values as defaults
        $funnel = $funnelManager->updateFunnel(
            funnelId: $request->get('id'),
            name: $request->get('name', $funnel->name),
            slug: $request->get('slug', $funnel->slug),
            status: $status,
            supportEmail: $request->get('support_email', $funnel->support_email),
            languageCode: $request->get('language_code', $funnel->language_code),
            currencyCode: $request->get('currency_code', $funnel->currency_code),
            mainProductId: $request->get('main_product_id', $funnel->main_product?->productId),
            bumpOfferIds: $request->get('bump_offer_ids', array_values(array_map(fn (FunnelProduct $offer): int => $offer->productId, $funnel->bump_offers))),
            taxEnabled: $request->get('tax_enabled', $funnel->tax_enabled),
            processorId: $request->has('processor_id') ? $request->get('processor_id') : $funnel->payment_processor?->integrationId,
            templateId: $request->has('template_id') ? $request->get('template_id') : $funnel->template_id,
            fulfillment: $request->get('fulfillment', $funnel->fulfillment),
            fulfillmentUrl: $request->get('fulfillment_url', $funnel->fulfillment_url),
            userId: $request->get('user_id'),
        );

        // Reload with funnel data
        $funnel->load(['funnelData', 'template']);

        return Response::text(json_encode([
            'id' => $funnel->id,
            'name' => $funnel->name,
            'slug' => $funnel->slug,
            'status' => match ($funnel->status) {
                FunnelStatus::DISABLED => 'disabled',
                FunnelStatus::ENABLED => 'enabled',
            },
            'status_label' => $funnel->status->getLabel(),
            'support_email' => $funnel->support_email,
            'language_code' => $funnel->language_code,
            'currency_code' => $funnel->currency_code,
            'main_product_id' => $funnel->main_product?->productId,
            'bump_offers' => array_values(array_map(fn (FunnelProduct $offer): int => $offer->productId, $funnel->bump_offers)),
            'tax_enabled' => $funnel->tax_enabled,
            'processor_id' => $funnel->payment_processor?->integrationId,
            'template_id' => $funnel->template_id,
            'fulfillment' => $funnel->fulfillment,
            'fulfillment_url' => $funnel->fulfillment_url,
            'updated_at' => $funnel->updated_at->toISOString(),
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
            'account_id' => $schema->integer()->description('The account ID that owns the funnel.'),
            'id' => $schema->integer()->description('The unique identifier of the funnel to update.')->required(),
            'name' => $schema->string()->description('The funnel name.'),
            'slug' => $schema->string()->description('URL-friendly slug for the funnel.'),
            'status' => $schema->string()->description('Funnel status: disabled or enabled.'),
            'support_email' => $schema->string()->description('Support email for the funnel.'),
            'language_code' => $schema->string()->description('2-letter ISO language code (e.g., "en").'),
            'currency_code' => $schema->string()->description('3-letter ISO currency code (e.g., "USD").'),
            'main_product_id' => $schema->integer()->description('ID of the main product for this funnel.'),
            'bump_offer_ids' => $schema->array()->description('Array of up to 5 product IDs for bump offers.')->items($schema->integer()),
            'tax_enabled' => $schema->boolean()->description('Whether tax collection is enabled.'),
            'processor_id' => $schema->integer()->description('ID of the payment processor integration (e.g., Stripe).'),
            'template_id' => $schema->integer()->description('ID of the template to use for checkout page.'),
            'fulfillment' => $schema->string()->description('Fulfillment type: "invoice" or "redirect".'),
            'fulfillment_url' => $schema->string()->description('URL to redirect to after purchase (required if fulfillment is "redirect").'),
            'user_id' => $schema->integer()->description('User ID updating the funnel.'),
        ];
    }
}
