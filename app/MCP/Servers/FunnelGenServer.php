<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Prompts\CreateFunnelWorkflowPrompt;
use App\Mcp\Prompts\CreateProductWorkflowPrompt;
use App\Mcp\Prompts\CreateTemplateWorkflowPrompt;
use App\Mcp\Resources\TemplateSchemaResource;
use App\Mcp\Tools\CreateFunnelTool;
use App\Mcp\Tools\CreateProductTool;
use App\Mcp\Tools\CreateTemplateTool;
use App\Mcp\Tools\GetFunnelTool;
use App\Mcp\Tools\GetProductTool;
use App\Mcp\Tools\GetTemplateTool;
use App\Mcp\Tools\ListFunnelTool;
use App\Mcp\Tools\ListOrderTool;
use App\Mcp\Tools\ListProductTool;
use App\Mcp\Tools\ListTemplateTool;
use App\Mcp\Tools\UpdateFunnelTool;
use App\Mcp\Tools\UpdateProductTool;
use App\Mcp\Tools\UpdateTemplateTool;
use Illuminate\Http\Request;
use Laravel\Mcp\Server;

class FunnelGenServer extends Server
{
    public string $serverName = 'FunnelGen Server';

    public string $serverVersion = '1.0.0';

    public string $instructions = <<<'MARKDOWN'
        This server manages the complete FunnelGen e-commerce and checkout system.

        Available Resources:
        - **Products**: One-time and subscription products with pricing configuration
        - **Templates**: HTML/CSS/JS templates for checkout pages
        - **Funnels**: Sales funnels connecting products and templates
        - **Orders**: Order information and analytics (coming soon)

        All operations require an account_id parameter for multi-tenancy.

        ## Products
        Products can be configured as one-time purchases or recurring subscriptions.
        - One-time products: Require a price
        - Subscription products: Require recurring_price and billing_interval
        - Subscriptions support setup fees, trial periods, and multiple billing intervals

        ## Templates
        Templates are HTML/CSS/JS checkout page designs.
        - HTML content MUST include {{CHECKOUT_COMPONENT}} placeholder
        - Follow the template workflow for creating new templates
        - Templates can have custom variables and configurations

        ## Funnels
        Funnels are sales funnels that connect products with templates and payment processors.
        - Each funnel has one main product and up to 5 bump offers
        - Funnels can have different statuses: Disabled (0), Test (1), Draft (2), Live (3)
        - Configure payment processors (like Stripe), templates, tax settings, and fulfillment

        Use the provided tools to create, list, retrieve, and update resources.
    MARKDOWN;

    public array $tools = [
        // Product Tools
        CreateProductTool::class,
        ListProductTool::class,
        GetProductTool::class,
        UpdateProductTool::class,

        // Template Tools
        CreateTemplateTool::class,
        ListTemplateTool::class,
        GetTemplateTool::class,
        UpdateTemplateTool::class,

        // Funnel Tools
        CreateFunnelTool::class,
        ListFunnelTool::class,
        GetFunnelTool::class,
        UpdateFunnelTool::class,

        // Order tools
        ListOrderTool::class,
    ];

    public array $resources = [
        TemplateSchemaResource::class,
    ];

    public array $prompts = [
        CreateFunnelWorkflowPrompt::class,
        CreateProductWorkflowPrompt::class,
        CreateTemplateWorkflowPrompt::class,
    ];

    /**
     * Determine if the server requires authentication.
     */
    public function requiresAuthentication(Request $request): bool
    {
        return true;
    }

    /**
     * Get the authentication guard to use.
     */
    public function authenticationGuard(Request $request): ?string
    {
        return 'api';
    }
}
