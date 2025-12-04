<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Order;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\DTO\DashboardFilterDTO;
use App\Enums\BIDateRange;
use App\Repositories\BIRepository;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

/**
 * List Order Tool
 *
 * Lists orders with transactions in the specified date range (up to 90 days).
 * Returns orders that had transaction activity in the period, including:
 * - Order metadata (customer, funnel, products, UTM tracking)
 * - Lifetime order totals (all-time revenue for the order)
 * - Period transactions (transactions that occurred in the date range)
 * - Period totals (revenue from transactions in the date range)
 *
 * This correctly handles recurring subscriptions by showing only the transactions
 * that occurred within the filtered period.
 */
class ListOrderTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('list_orders')
            ->for('List orders with transactions in the specified date range (up to 90 days)')
            ->withObjectParameter(
                name: 'input',
                description: 'Order listing parameters',
                properties: [
                    new StringSchema('date_range', 'Date range for orders: today, yesterday, this_month, last_month, ytd, last_7_days, last_30_days, last_90_days. Defaults to last_90_days. Maximum 90 days of data.', nullable: true),
                    new NumberSchema('product_id', 'Filter by product ID (main or bump offer)', nullable: true),
                    new NumberSchema('funnel_id', 'Filter by funnel ID', nullable: true),
                    new StringSchema('utm_source', 'Filter by UTM source', nullable: true),
                    new StringSchema('utm_medium', 'Filter by UTM medium', nullable: true),
                    new StringSchema('utm_campaign', 'Filter by UTM campaign', nullable: true),
                    new StringSchema('utm_term', 'Filter by UTM term', nullable: true),
                    new StringSchema('utm_content', 'Filter by UTM content', nullable: true),
                    new StringSchema('customer_country', 'Filter by customer country code', nullable: true),
                    new BooleanSchema('has_subscription', 'Filter by whether order has subscription items', nullable: true),
                    new NumberSchema('limit', 'Maximum number of orders to return (1-1000). Defaults to 100.', nullable: true),
                ],
                requiredFields: []
            )
            ->using($this);
    }

    /**
     * Execute the tool to list orders with transactions.
     *
     * @param  array{
     *     date_range?: string,
     *     product_id?: int,
     *     funnel_id?: int,
     *     utm_source?: string,
     *     utm_medium?: string,
     *     utm_campaign?: string,
     *     utm_term?: string,
     *     utm_content?: string,
     *     customer_country?: string,
     *     has_subscription?: bool,
     *     limit?: int
     * }  $input
     * @return string JSON-encoded orders data or error message
     */
    public function __invoke(array $input): string
    {
        SwitchTenantContextAction::run($this->accountId);

        try {
            // Validate and set date range
            $dateRange = isset($input['date_range'])
                ? BIDateRange::tryFrom($input['date_range'])
                : BIDateRange::LAST_90_DAYS;

            if ($dateRange === null) {
                return json_encode([
                    'error' => 'Invalid date_range value',
                    'message' => 'Date range must be one of: today, yesterday, this_month, last_month, ytd, last_7_days, last_30_days, last_90_days',
                ]);
            }

            // Enforce max 90 days limit
            if (in_array($dateRange, [BIDateRange::LAST_180_DAYS, BIDateRange::LAST_365_DAYS, BIDateRange::LAST_18_MONTHS], true)) {
                $dateRange = BIDateRange::LAST_90_DAYS;
            }

            // Validate limit
            $limit = $input['limit'] ?? 100;

            if ($limit < 1 || $limit > 1000) {
                return json_encode([
                    'error' => 'Invalid limit value',
                    'message' => 'Limit must be between 1 and 1000',
                ]);
            }

            // Create filter DTO
            $filters = new DashboardFilterDTO(
                date_range: $dateRange,
                product_id: isset($input['product_id']) ? (string) $input['product_id'] : null,
                funnel_id: isset($input['funnel_id']) ? (string) $input['funnel_id'] : null,
                utm_source: $input['utm_source'] ?? null,
                utm_medium: $input['utm_medium'] ?? null,
                utm_campaign: $input['utm_campaign'] ?? null,
                utm_term: $input['utm_term'] ?? null,
                utm_content: $input['utm_content'] ?? null,
                customer_country: $input['customer_country'] ?? null,
                has_subscription: $input['has_subscription'] ?? null,
                account_id: $this->accountId,
            );

            $biRepository = app(BIRepository::class);
            $result = $biRepository->getOrdersWithTransactions($filters, $limit);

            [$dateFrom, $dateTo] = $biRepository->calculateDateRange($dateRange);

            return json_encode([
                'success' => true,
                'date_range' => $dateRange->value,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'summary' => $result['summary'],
                'orders' => $result['orders'],
            ], JSON_PRETTY_PRINT);
        }
        catch (\Throwable $e) {
            return json_encode([
                'error' => 'Failed to list orders',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
