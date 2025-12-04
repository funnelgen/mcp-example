<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Schemas;

use App\Models\Tenant\Product;
use Prism\Prism\Schema\ObjectSchema;

class ProductSchema extends ObjectSchema
{
    public function __construct()
    {
        $properties = [
            new \Prism\Prism\Schema\StringSchema(
                name: 'id',
                description: 'The unique identifier for the product',
            ),
            new \Prism\Prism\Schema\StringSchema(
                name: 'name',
                description: 'The name of the product',
            ),
            new \Prism\Prism\Schema\StringSchema(
                name: 'description',
                description: 'A detailed description of the product',
            ),
            new \Prism\Prism\Schema\NumberSchema(
                name: 'price',
                description: 'The price of the product in USD',
            ),
            new \Prism\Prism\Schema\StringSchema(
                name: 'currency',
                description: 'The currency code (e.g., USD)',
            ),
            new \Prism\Prism\Schema\BooleanSchema(
                name: 'in_stock',
                description: 'Indicates if the product is in stock',
            ),
        ];

        parent::__construct(
            name: 'Product',
            description: 'A schema representing a product in the catalog',
            properties: $properties,
            requiredFields: ['id', 'name', 'price', 'currency', 'in_stock'],
            allowAdditionalProperties: false,
        );
    }

    /**
     * Generate a Product schema from a Product model instance.
     */
    public static function fromModel(Product $product): self
    {
        return new self();
    }
}
