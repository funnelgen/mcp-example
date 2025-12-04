<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Product;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Repositories\Tenant\ProductRepository;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Tool;

/**
 * Get Product Tool
 *
 * Retrieves detailed information about a specific product by its ID.
 * This tool handles tenant context switching and uses the ProductRepository
 * for data access.
 */
class GetProductTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('get_product')
            ->for('Get details about a specific product by ID')
            ->withObjectParameter(
                name: 'input',
                description: 'Product lookup parameters',
                properties: [
                    new NumberSchema('product_id', 'The ID of the product to fetch'),
                ],
                requiredFields: ['product_id']
            )
            ->using($this);
    }

    /**
     * Execute the tool to retrieve product information.
     *
     * @param  array{product_id: int|string}  $input
     * @return string JSON-encoded product data or error message
     */
    public function __invoke(array $input): string
    {
        SwitchTenantContextAction::run($this->accountId);

        $productId = (int) $input['product_id'];
        $productRepository = app(ProductRepository::class);
        $product = $productRepository->findById($productId);

        if (empty($product)) {
            return json_encode([
                'error' => 'Product not found',
                'product_id' => $productId,
            ]);
        }

        return json_encode($product->toArray());
    }
}
