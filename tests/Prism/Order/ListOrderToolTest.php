<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Mcp\Prism\Tools\Order\ListOrderTool;
use App\Models\OrderFact;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

test('lists orders with transactions in date range', function () {
    // Create orders with transactions within the last 30 days
    for ($i = 0; $i < 3; $i++) {
        $orderFact = OrderFact::factory()->create([
            'account_id' => $this->account->id,
            'original_order_date' => Carbon::now()->subDays(20),
        ]);

        Transaction::factory()->create([
            'order_fact_id' => $orderFact->id,
            'account_id' => $this->account->id,
            'type' => TransactionType::PAYMENT,
            'amount_total' => 1000,
            'amount_net' => 950,
            'processed_at' => Carbon::now()->subDays(10),
        ]);
    }

    // Create an order with transaction outside the range
    $oldOrderFact = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'original_order_date' => Carbon::now()->subDays(100),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $oldOrderFact->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(100),
    ]);

    $tool = new ListOrderTool($this->account->id);
    $response = $tool(['date_range' => 'last_30_days']);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data)->toHaveKey('summary')
        ->and($data['summary']['total_orders'])->toBe(3)
        ->and($data['summary']['period_revenue'])->toBe('$30.00')
        ->and($data['summary']['period_net_revenue'])->toBe('$28.50')
        ->and($data)->toHaveKey('date_range')
        ->and($data['date_range'])->toBe('last_30_days')
        ->and($data)->toHaveKey('orders')
        ->and($data['orders'])->toHaveCount(3);
});

test('shows lifetime vs period totals correctly', function () {
    // Create order with multiple transactions across time
    $orderFact = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'total_revenue' => 3000, // Lifetime total
        'net_revenue' => 2850,
        'original_order_date' => Carbon::now()->subDays(60),
    ]);

    // Old transaction (outside last_7_days)
    Transaction::factory()->create([
        'order_fact_id' => $orderFact->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'amount_total' => 1000,
        'amount_net' => 950,
        'processed_at' => Carbon::now()->subDays(30),
    ]);

    // Recent transaction (within last_7_days)
    Transaction::factory()->create([
        'order_fact_id' => $orderFact->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::RECURRING_PAYMENT,
        'amount_total' => 1000,
        'amount_net' => 950,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $tool = new ListOrderTool($this->account->id);
    $response = $tool(['date_range' => 'last_7_days']);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['summary']['period_revenue'])->toBe('$10.00') // Only recent transaction
        ->and($data['orders'][0]['lifetime_totals']['total_revenue'])->toBe('$30.00') // Full lifetime
        ->and($data['orders'][0]['period_totals']['revenue'])->toBe('$10.00') // Period total
        ->and($data['orders'][0])->toHaveKey('period_transactions')
        ->and($data['orders'][0]['period_transactions'])->toHaveCount(1);
});

test('filters orders by product id', function () {
    $product1Id = 1;
    $product2Id = 2;

    // Orders with product 1
    for ($i = 0; $i < 2; $i++) {
        $orderFact = OrderFact::factory()->create([
            'account_id' => $this->account->id,
            'main_product_id' => $product1Id,
            'original_order_date' => Carbon::now()->subDays(10),
        ]);

        Transaction::factory()->create([
            'order_fact_id' => $orderFact->id,
            'account_id' => $this->account->id,
            'type' => TransactionType::PAYMENT,
            'processed_at' => Carbon::now()->subDays(5),
        ]);
    }

    // Order with product 2
    $orderFact2 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'main_product_id' => $product2Id,
        'original_order_date' => Carbon::now()->subDays(10),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact2->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    $tool = new ListOrderTool($this->account->id);
    $response = $tool([
        'product_id' => $product1Id,
        'date_range' => 'last_7_days'
    ]);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['summary']['total_orders'])->toBe(2);
});

test('filters orders by funnel id', function () {
    $funnel1Id = 1;
    $funnel2Id = 2;

    $orderFact1 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'funnel_id' => $funnel1Id,
        'original_order_date' => Carbon::now()->subDays(5),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact1->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $orderFact2 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'funnel_id' => $funnel2Id,
        'original_order_date' => Carbon::now()->subDays(5),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact2->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $tool = new ListOrderTool($this->account->id);
    $response = $tool([
        'funnel_id' => $funnel1Id,
        'date_range' => 'last_7_days'
    ]);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['summary']['total_orders'])->toBe(1)
        ->and($data['orders'][0]['funnel_id'])->toBe($funnel1Id);
});

test('filters orders by utm parameters', function () {
    $orderFact1 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'summer',
        'original_order_date' => Carbon::now()->subDays(5),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact1->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $orderFact2 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'utm_source' => 'facebook',
        'utm_medium' => 'social',
        'utm_campaign' => 'winter',
        'original_order_date' => Carbon::now()->subDays(5),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact2->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $tool = new ListOrderTool($this->account->id);
    $response = $tool([
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'date_range' => 'last_7_days'
    ]);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['summary']['total_orders'])->toBe(1)
        ->and($data['orders'][0]['utm_data']['source'])->toBe('google')
        ->and($data['orders'][0]['utm_data']['medium'])->toBe('cpc');
});

test('filters orders by customer country', function () {
    $orderFact1 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'customer_country' => 'US',
        'original_order_date' => Carbon::now()->subDays(5),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact1->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $orderFact2 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'customer_country' => 'CA',
        'original_order_date' => Carbon::now()->subDays(5),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact2->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $tool = new ListOrderTool($this->account->id);
    $response = $tool([
        'customer_country' => 'US',
        'date_range' => 'last_7_days'
    ]);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['summary']['total_orders'])->toBe(1)
        ->and($data['orders'][0]['customer_country'])->toBe('US');
});

test('filters orders by subscription status', function () {
    $orderFact1 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'has_subscription' => true,
        'original_order_date' => Carbon::now()->subDays(5),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact1->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $orderFact2 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'has_subscription' => false,
        'original_order_date' => Carbon::now()->subDays(5),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact2->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $tool = new ListOrderTool($this->account->id);
    $response = $tool([
        'has_subscription' => true,
        'date_range' => 'last_7_days'
    ]);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['summary']['total_orders'])->toBe(1)
        ->and($data['orders'][0]['has_subscription'])->toBeTrue();
});

test('respects limit parameter', function () {
    // Create 5 orders
    for ($i = 0; $i < 5; $i++) {
        $orderFact = OrderFact::factory()->create([
            'account_id' => $this->account->id,
            'original_order_date' => Carbon::now()->subDays(5),
        ]);

        Transaction::factory()->create([
            'order_fact_id' => $orderFact->id,
            'account_id' => $this->account->id,
            'type' => TransactionType::PAYMENT,
            'processed_at' => Carbon::now()->subDays(3),
        ]);
    }

    $tool = new ListOrderTool($this->account->id);
    $response = $tool([
        'limit' => 3,
        'date_range' => 'last_7_days'
    ]);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['summary']['total_orders'])->toBe(3)
        ->and($data['orders'])->toHaveCount(3);
});

test('uses default date range when not specified', function () {
    $orderFact = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'original_order_date' => Carbon::now()->subDays(5),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $tool = new ListOrderTool($this->account->id);
    $response = $tool([]); // No parameters
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['date_range'])->toBe('last_90_days')
        ->and($data)->toHaveKey('date_from')
        ->and($data)->toHaveKey('date_to');
});

test('enforces 90 day maximum limit', function () {
    $tool = new ListOrderTool($this->account->id);
    $response = $tool(['date_range' => 'last_365_days']); // Over limit
    $data = json_decode($response, true);

    expect($data)->toHaveKey('success')
        ->and($data['success'])->toBeTrue()
        ->and($data['date_range'])->toBe('last_90_days'); // Should be downgraded
});

test('returns error for invalid date range', function () {
    $tool = new ListOrderTool($this->account->id);
    $response = $tool(['date_range' => 'invalid_range']);
    $data = json_decode($response, true);

    expect($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid date_range value')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toContain('Date range must be one of:');
});

test('returns error for invalid limit', function () {
    $tool = new ListOrderTool($this->account->id);
    $response = $tool(['limit' => 0]); // Below minimum
    $data = json_decode($response, true);

    expect($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid limit value')
        ->and($data)->toHaveKey('message')
        ->and($data['message'])->toBe('Limit must be between 1 and 1000');

    $response = $tool(['limit' => 1001]); // Above maximum
    $data = json_decode($response, true);

    expect($data)->toHaveKey('error')
        ->and($data['error'])->toBe('Invalid limit value');
});

test('includes all order data fields', function () {
    $orderFact = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'order_id' => 'order_123',
        'funnel_id' => 456,
        'customer_email' => 'test@example.com',
        'customer_country' => 'US',
        'customer_state' => 'CA',
        'has_subscription' => true,
        'main_product_id' => 789,
        'bump_1_product_id' => 101,
        'bump_2_product_id' => 102,
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'test',
        'utm_term' => 'keyword',
        'utm_content' => 'ad1',
        'original_order_date' => Carbon::now()->subDays(5),
        'total_revenue' => 2000,
        'net_revenue' => 1900,
        'mrr_contribution' => 100,
        'arr_contribution' => 1200,
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'amount_total' => 1000,
        'amount_net' => 950,
        'amount_subtotal' => 900,
        'amount_tax' => 100,
        'amount_discount' => 50,
        'currency_code' => 'USD',
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $tool = new ListOrderTool($this->account->id);
    $response = $tool(['date_range' => 'last_7_days']);
    $data = json_decode($response, true);

    $order = $data['orders'][0];

    expect($order)->toHaveKey('id')
        ->and($order)->toHaveKey('order_id')
        ->and($order['order_id'])->toBe('order_123')
        ->and($order)->toHaveKey('funnel_id')
        ->and($order['funnel_id'])->toBe(456)
        ->and($order)->toHaveKey('customer_email')
        ->and($order['customer_email'])->toBe('test@example.com')
        ->and($order)->toHaveKey('customer_country')
        ->and($order['customer_country'])->toBe('US')
        ->and($order)->toHaveKey('has_subscription')
        ->and($order['has_subscription'])->toBeTrue()
        ->and($order)->toHaveKey('products')
        ->and($order['products']['main_product_id'])->toBe(789)
        ->and($order['products']['bump_offers'])->toContain(101)
        ->and($order['products']['bump_offers'])->toContain(102)
        ->and($order)->toHaveKey('utm_data')
        ->and($order['utm_data']['source'])->toBe('google')
        ->and($order['utm_data']['medium'])->toBe('cpc')
        ->and($order['utm_data']['campaign'])->toBe('test')
        ->and($order['utm_data']['term'])->toBe('keyword')
        ->and($order['utm_data']['content'])->toBe('ad1')
        ->and($order)->toHaveKey('lifetime_totals')
        ->and($order['lifetime_totals']['total_revenue'])->toBe('$20.00')
        ->and($order['lifetime_totals']['net_revenue'])->toBe('$19.00')
        ->and($order['lifetime_totals']['mrr_contribution'])->toBe('$1.00')
        ->and($order['lifetime_totals']['arr_contribution'])->toBe('$12.00')
        ->and($order)->toHaveKey('period_transactions')
        ->and($order['period_transactions'])->toHaveCount(1)
        ->and($order)->toHaveKey('period_totals')
        ->and($order['period_totals']['revenue'])->toBe('$10.00')
        ->and($order['period_totals']['net_revenue'])->toBe('$9.50')
        ->and($order['period_totals']['transaction_count'])->toBe(1);

    $transaction = $order['period_transactions'][0];
    expect($transaction)->toHaveKey('id')
        ->and($transaction)->toHaveKey('type')
        ->and($transaction['type'])->toBe('payment')
        ->and($transaction)->toHaveKey('type_label')
        ->and($transaction)->toHaveKey('amount_total')
        ->and($transaction['amount_total'])->toBe('$10.00')
        ->and($transaction)->toHaveKey('amount_net')
        ->and($transaction['amount_net'])->toBe('$9.50')
        ->and($transaction)->toHaveKey('amount_subtotal')
        ->and($transaction['amount_subtotal'])->toBe('$9.00')
        ->and($transaction)->toHaveKey('amount_tax')
        ->and($transaction['amount_tax'])->toBe('$1.00')
        ->and($transaction)->toHaveKey('amount_discount')
        ->and($transaction['amount_discount'])->toBe('$0.50')
        ->and($transaction)->toHaveKey('currency_code')
        ->and($transaction['currency_code'])->toBe('USD')
        ->and($transaction)->toHaveKey('processed_at');
});

test('handles multiple date ranges correctly', function () {
    $orderFact = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'original_order_date' => Carbon::now()->subDays(10),
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    $dateRanges = ['today', 'yesterday', 'this_month', 'last_month', 'ytd', 'last_7_days', 'last_30_days', 'last_90_days'];

    foreach ($dateRanges as $dateRange) {
        $tool = new ListOrderTool($this->account->id);
        $response = $tool(['date_range' => $dateRange]);
        $data = json_decode($response, true);

        expect($data)->toHaveKey('success')
            ->and($data['success'])->toBeTrue()
            ->and($data['date_range'])->toBe($dateRange)
            ->and($data)->toHaveKey('date_from')
            ->and($data)->toHaveKey('date_to')
            ->and($data)->toHaveKey('summary')
            ->and($data)->toHaveKey('orders');
    }
});
