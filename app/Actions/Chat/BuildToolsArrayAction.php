<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ChatType;
use App\Mcp\Prism\Tools\Funnel\CreateFunnelTool;
use App\Mcp\Prism\Tools\Funnel\GetFunnelTool;
use App\Mcp\Prism\Tools\Funnel\ListFunnelsTool;
use App\Mcp\Prism\Tools\Funnel\UpdateFunnelTool;
use App\Mcp\Prism\Tools\Order\ListOrderTool;
use App\Mcp\Prism\Tools\Product\CreateProductTool;
use App\Mcp\Prism\Tools\Product\GetProductTool;
use App\Mcp\Prism\Tools\Product\ListProductsTool;
use App\Mcp\Prism\Tools\Product\UpdateProductTool;
use App\Mcp\Prism\Tools\Template\CreateTemplateTool;
use App\Mcp\Prism\Tools\Template\GetTemplateTool;
use App\Mcp\Prism\Tools\Template\ListTemplatesTool;
use App\Mcp\Prism\Tools\Template\UpdateTemplateTool;
use App\Traits\AsActionTrait;

/**
 * @method static array run(ChatType $type, int $accountId):
 */
class BuildToolsArrayAction
{
    use AsActionTrait;

    public function __invoke(ChatType $type, int $accountId): array
    {
        $tools = match ($type) {
            ChatType::CHAT => [
                new GetFunnelTool($accountId),
                new ListFunnelsTool($accountId),
                new CreateFunnelTool($accountId),
                new UpdateFunnelTool($accountId),
                new GetProductTool($accountId),
                new ListProductsTool($accountId),
                new CreateProductTool($accountId),
                new UpdateProductTool($accountId),
                new GetTemplateTool($accountId),
                new ListTemplatesTool($accountId),
                new UpdateTemplateTool($accountId),
                new ListOrderTool($accountId),
            ],
            ChatType::DESIGN => [
                new GetFunnelTool($accountId),
                new ListFunnelsTool($accountId),
                new CreateTemplateTool($accountId),
                new GetTemplateTool($accountId),
                new ListTemplatesTool($accountId),
                new UpdateTemplateTool($accountId),
            ],
            ChatType::SEARCH => [],
        };

        return $tools;
    }
}
