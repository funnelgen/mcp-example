<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\FunnelStatus;
use App\Facades\CurrentAccount;
use App\Managers\Tenant\FunnelManager;
use App\ValueObjects\FunnelProduct;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateFunnelTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Create a new funnel (sales funnel) with a main product and optional bump offers.
        Funnels connect products with templates and payment processors to create checkout pages.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, FunnelManager $funnelManager): Response
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'status' => ['nullable', 'string', 'in:disabled,enabled'],
            'owner' => 'nullable|integer',
            'support_email' => 'nullable|email|max:255',
            'language_code' => 'nullable|string|size:2',
            'currency_code' => 'nullable|string|size:3',
            'main_product_id' => 'required|integer',
            'bump_offer_ids' => 'nullable|array|max:5',
            'bump_offer_ids.*' => 'integer',
            'tax_enabled' => 'nullable|boolean',
            'processor_id' => 'nullable|integer',
            'template_id' => 'nullable|integer',
            'fulfillment' => 'nullable|string|in:invoice,redirect',
            'fulfillment_url' => 'nullable|string|url',
            'user_id' => 'nullable|integer',
        ]);

        $status = FunnelStatus::ENABLED;

        match ($request->get('status')) {
            'disabled' => $status = FunnelStatus::DISABLED,
            'enabled' => $status = FunnelStatus::ENABLED,
            default => $status = FunnelStatus::ENABLED,
        };

        $funnel = $funnelManager->createFunnel(
            accountId: CurrentAccount::get()->id,
            name: $request->get('name'),
            slug: $request->get('slug'),
            status: $status,
            owner: $request->get('owner', $request->get('user_id', 1)),
            supportEmail: $request->get('support_email', 'support@example.com'),
            languageCode: $request->get('language_code', 'en'),
            currencyCode: $request->get('currency_code', 'USD'),
            mainProductId: $request->get('main_product_id'),
            bumpOfferIds: $request->get('bump_offer_ids', []),
            taxEnabled: $request->get('tax_enabled', false),
            processorId: $request->get('processor_id'),
            templateId: $request->get('template_id'),
            fulfillment: $request->get('fulfillment'),
            fulfillmentUrl: $request->get('fulfillment_url'),
            userId: $request->get('user_id'),
        );

        // Reload with funnel data
        $funnel->load(['funnelData', 'template']);

        return Response::text(json_encode([
            'id' => $funnel->id,
            'name' => $funnel->name,
            'slug' => $funnel->slug,
            'status' => $funnel->status->value,
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
            'created_at' => $funnel->created_at->toISOString(),
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
            'account_id' => $schema->integer()->description('The account ID that will own the funnel.'),
            'name' => $schema->string()->description('The funnel name.')->required(),
            'slug' => $schema->string()->description('URL-friendly slug for the funnel.')->required(),
            'status' => $schema->string()->description('Funnel status: disabled or enabled. Defaults to enabled.'),
            'owner' => $schema->integer()->description('User ID of the funnel owner. Defaults to user_id or 1.'),
            'support_email' => $schema->string()->description('Support email for the funnel. Defaults to support@example.com.'),
            'language_code' => $schema->string()->description('2-letter ISO language code (e.g., "en"). Defaults to "en".'),
            'currency_code' => $schema->string()->description('3-letter ISO currency code (e.g., "USD"). Defaults to "USD".'),
            'main_product_id' => $schema->integer()->description('ID of the main product for this funnel.')->required(),
            'bump_offer_ids' => $schema->array()->description('Array of up to 5 product IDs for bump offers.')->items($schema->integer()),
            'tax_enabled' => $schema->boolean()->description('Whether tax collection is enabled. Defaults to false.'),
            'processor_id' => $schema->integer()->description('ID of the payment processor integration (e.g., Stripe).'),
            'template_id' => $schema->integer()->description('ID of the template to use for checkout page.'),
            'fulfillment' => $schema->string()->description('Fulfillment type: "invoice" or "redirect".'),
            'fulfillment_url' => $schema->string()->description('URL to redirect to after purchase (required if fulfillment is "redirect").'),
            'user_id' => $schema->integer()->description('User ID creating the funnel.'),
        ];
    }
}
