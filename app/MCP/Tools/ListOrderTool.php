<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\DTO\DashboardFilterDTO;
use App\Enums\BIDateRange;
use App\Facades\CurrentAccount;
use App\Repositories\BIRepository;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListOrderTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        List orders with transactions in the specified date range (up to 90 days).
        Returns orders that had transaction activity in the period, including:
        - Order metadata (customer, funnel, products, UTM tracking)
        - Lifetime order totals (all-time revenue for the order)
        - Period transactions (transactions that occurred in the date range)
        - Period totals (revenue from transactions in the date range)

        This correctly handles recurring subscriptions by showing only the transactions
        that occurred within the filtered period.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, BIRepository $biRepository): Response
    {
        $request->validate([
            'date_range' => 'nullable|string|in:today,yesterday,this_month,last_month,ytd,last_7_days,last_30_days,last_90_days',
            'product_id' => 'nullable|integer',
            'funnel_id' => 'nullable|integer',
            'utm_source' => 'nullable|string',
            'utm_medium' => 'nullable|string',
            'utm_campaign' => 'nullable|string',
            'utm_term' => 'nullable|string',
            'utm_content' => 'nullable|string',
            'customer_country' => 'nullable|string',
            'has_subscription' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $dateRange = $request->has('date_range')
            ? BIDateRange::from($request->get('date_range'))
            : BIDateRange::LAST_90_DAYS;

        if (in_array($dateRange, [BIDateRange::LAST_180_DAYS, BIDateRange::LAST_365_DAYS, BIDateRange::LAST_18_MONTHS], true)) {
            // Enforce max 90 days
            $dateRange = BIDateRange::LAST_90_DAYS;
        }

        $filters = new DashboardFilterDTO(
            date_range: $dateRange,
            product_id: $request->get('product_id') ? (string) $request->get('product_id') : null,
            funnel_id: $request->get('funnel_id') ? (string) $request->get('funnel_id') : null,
            utm_source: $request->get('utm_source'),
            utm_medium: $request->get('utm_medium'),
            utm_campaign: $request->get('utm_campaign'),
            utm_term: $request->get('utm_term'),
            utm_content: $request->get('utm_content'),
            customer_country: $request->get('customer_country'),
            has_subscription: $request->get('has_subscription'),
            account_id: CurrentAccount::get()->id,
        );

        $limit = $request->get('limit', 100);
        $result = $biRepository->getOrdersWithTransactions($filters, $limit);

        [$dateFrom, $dateTo] = $biRepository->calculateDateRange($dateRange);

        return Response::text(json_encode([
            'date_range' => $dateRange->value,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'summary' => $result['summary'],
            'orders' => $result['orders'],
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
            'account_id' => $schema->integer()->description('The account ID to list orders for.'),
            'date_range' => $schema->string()->description('Date range for orders: today, yesterday, this_month, last_month, ytd, last_7_days, last_30_days, last_90_days. Defaults to last_90_days. Maximum 90 days of data.'),
            'product_id' => $schema->integer()->description('Filter by product ID (main or bump offer).'),
            'funnel_id' => $schema->integer()->description('Filter by funnel ID.'),
            'utm_source' => $schema->string()->description('Filter by UTM source.'),
            'utm_medium' => $schema->string()->description('Filter by UTM medium.'),
            'utm_campaign' => $schema->string()->description('Filter by UTM campaign.'),
            'utm_term' => $schema->string()->description('Filter by UTM term.'),
            'utm_content' => $schema->string()->description('Filter by UTM content.'),
            'customer_country' => $schema->string()->description('Filter by customer country code.'),
            'has_subscription' => $schema->boolean()->description('Filter by whether order has subscription items.'),
            'limit' => $schema->integer()->description('Maximum number of orders to return (1-1000). Defaults to 100.'),
        ];
    }
}
