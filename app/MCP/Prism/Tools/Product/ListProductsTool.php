<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Product;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Models\Tenant\Product;
use App\Repositories\Tenant\ProductRepository;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

/**
 * List Products Tool
 *
 * Retrieves a list of all available products with their details.
 * This tool handles tenant context switching and uses the ProductRepository
 * for data access.
 */
class ListProductsTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('list_products')
            ->for('List all available products with their details')
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
     * Execute the tool to retrieve all products.
     *
     * @param  array<string, mixed>  $input  Empty array since this tool takes no parameters
     * @return string JSON-encoded array of products
     */
    public function __invoke(mixed $input = []): string
    {
        SwitchTenantContextAction::run($this->accountId);

        $productRepository = app(ProductRepository::class);
        /** @var \Illuminate\Support\Collection<int, Product> $products */
        $products = $productRepository->getAll();

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
        ])->values();

        $result = json_encode([
            'total' => $products->count(),
            'products' => $productsData,
        ], JSON_PRETTY_PRINT);

        return $result ?: json_encode(['total' => 0, 'products' => []]);
    }
}
