<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Tenant\ProductBehaviorRule;
use App\Repositories\Tenant\ProductRepository;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetProductTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get detailed information about a specific product by ID.
        Returns complete product details including pricing, behavior rules, and computed attributes.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, ProductRepository $productRepository): Response
    {
        $request->validate([
            'id' => 'required|integer',
            'with_behavior_rules' => 'nullable|boolean',
        ]);

        $product = $productRepository->findById($request->get('id'));

        if (!$product) {
            return Response::error("Product with ID {$request->get('id')} not found.");
        }

        // Load behavior rules if requested
        if ($request->get('with_behavior_rules', false)) {
            $product->load('behaviorRules.integration');
        }

        $productData = [
            'id' => $product->id,
            'name' => $product->name,
            'label' => $product->label,
            'pricing_type' => $product->pricing_type->value,
            'price' => $product->price,
            'setup_fee' => $product->setup_fee,
            'recurring_price' => $product->recurring_price,
            'billing_interval' => $product->billing_interval?->value,
            'trial_days' => $product->trial_days,
            'display_price' => $product->display_price,
            'display_recurring_price' => $product->display_recurring_price,
            'display_setup_fee' => $product->display_setup_fee,
            'todays_price' => $product->todays_price,
            'base_price' => $product->base_price,
            'is_subscription' => $product->is_subscription,
            'is_one_time' => $product->is_one_time,
            'has_setup_fee' => $product->has_setup_fee,
            'has_trial' => $product->has_trial,
            'created_at' => $product->created_at->toISOString(),
            'updated_at' => $product->updated_at->toISOString(),
        ];

        // Include behavior rules if loaded
        if ($product->relationLoaded('behaviorRules')) {
            $productData['behavior_rules'] = $product->behaviorRules->map(fn (ProductBehaviorRule $rule): array => [
                'id' => $rule->id,
                'integration_id' => $rule->integration_id,
                'event_type' => $rule->event_type->value,
                'action_type' => $rule->action_type->value,
                'action_config' => $rule->action_config,
                'is_active' => $rule->is_active,
                'integration' => $rule->relationLoaded('integration') ? [
                    'id' => $rule->integration->id,
                    'name' => $rule->integration->name->value,
                    'enabled' => $rule->integration->enabled,
                ] : null,
            ]);
        }

        return Response::text(json_encode($productData, JSON_PRETTY_PRINT));
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
            'account_id' => $schema->integer()->description('The account ID associated with the product.'),
            'id' => $schema->integer()->description('The unique identifier of the product.')->required(),
            'with_behavior_rules' => $schema->boolean()->description('Whether to include behavior rules with the product.'),
        ];
    }
}
