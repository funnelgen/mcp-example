<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Tenant\Product;
use App\Repositories\Tenant\ProductRepository;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListProductTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        List all products for a given account.
        Returns product details including pricing information and behavior rules if loaded.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, ProductRepository $productRepository): Response
    {
        $request->validate([
            'with_behavior_rules' => 'nullable|boolean',
        ]);

        $with = [];

        if ($request->get('with_behavior_rules', false)) {
            $with[] = 'behaviorRules.integration';
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Product> $products */
        $products = $productRepository->getAll(with: $with);

        $productsData = $products->map(fn (Product $product): array => [
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
            'is_subscription' => $product->is_subscription,
            'is_one_time' => $product->is_one_time,
            'has_setup_fee' => $product->has_setup_fee,
            'has_trial' => $product->has_trial,
            'created_at' => $product->created_at->toISOString(),
            'updated_at' => $product->updated_at->toISOString(),
            'behavior_rules_count' => $product->relationLoaded('behaviorRules') ? $product->behaviorRules->count() : null,
        ]);

        return Response::text(json_encode([
            'total' => $productsData->count(),
            'products' => $productsData,
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
            'account_id' => $schema->integer()->description('The account ID to list products for.'),
            'with_behavior_rules' => $schema->boolean()->description('Whether to include behavior rules with products.'),
        ];
    }
}
