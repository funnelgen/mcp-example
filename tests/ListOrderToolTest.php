<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Mcp\Servers\FunnelGenServer;
use App\Mcp\Tools\ListOrderTool;
use App\Models\OrderFact;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

it('lists orders with transactions in date range', function () {
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

    $response = FunnelGenServer::tool(ListOrderTool::class, [
        'account_id' => $this->account->id,
        'date_range' => 'last_30_days',
    ]);

    $response->assertOk();
    $response->assertSee('"summary"');
    $response->assertSee('"total_orders": 3');
    $response->assertSee('"period_revenue": "$30.00"');
    $response->assertSee('"period_net_revenue": "$28.50"');
    $response->assertSee('"date_range"');
    $response->assertSee('last_30_days');
});

it('shows lifetime vs period totals correctly', function () {
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

    $response = FunnelGenServer::tool(ListOrderTool::class, [
        'account_id' => $this->account->id,
        'date_range' => 'last_7_days',
    ]);

    $response->assertOk();
    $response->assertSee('"period_revenue": "$10.00"'); // Only recent transaction
    $response->assertSee('"lifetime_totals"');
    $response->assertSee('"total_revenue": "$30.00"'); // Full lifetime
    $response->assertSee('"period_totals"');
    $response->assertSee('"period_transactions"');
});

it('filters orders by product id', function () {
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

    $response = FunnelGenServer::tool(ListOrderTool::class, [
        'account_id' => $this->account->id,
        'product_id' => $product1Id,
        'date_range' => 'last_7_days',
    ]);

    $response->assertOk();
    $response->assertSee('"total_orders": 2');
});

it('filters orders by funnel id', function () {
    $funnel1Id = 1;
    $funnel2Id = 2;

    $orderFact1 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'funnel_id' => $funnel1Id,
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact1->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    $orderFact2 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'funnel_id' => $funnel2Id,
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact2->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    $response = FunnelGenServer::tool(ListOrderTool::class, [
        'account_id' => $this->account->id,
        'funnel_id' => $funnel1Id,
        'date_range' => 'last_7_days',
    ]);

    $response->assertOk();
    $response->assertSee('"total_orders": 1');
    $response->assertSee('"funnel_id": '.$funnel1Id);
});

it('filters orders by subscription status', function () {
    // Subscription order
    $orderFact1 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'has_subscription' => true,
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact1->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    // Non-subscription order
    $orderFact2 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'has_subscription' => false,
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact2->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    $response = FunnelGenServer::tool(ListOrderTool::class, [
        'account_id' => $this->account->id,
        'has_subscription' => true,
        'date_range' => 'last_7_days',
    ]);

    $response->assertOk();
    $response->assertSee('"total_orders": 1');
    $response->assertSee('"has_subscription": true');
});

it('filters orders by utm parameters', function () {
    $orderFact = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'summer_sale',
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'utm_source' => 'facebook',
    ]);

    $response = FunnelGenServer::tool(ListOrderTool::class, [
        'account_id' => $this->account->id,
        'utm_source' => 'google',
        'date_range' => 'last_7_days',
    ]);

    $response->assertOk();
    $response->assertSee('"total_orders": 1');
    $response->assertSee('"source": "google"');
});

it('filters orders by customer country', function () {
    $orderFact1 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'customer_country' => 'US',
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact1->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    $orderFact2 = OrderFact::factory()->create([
        'account_id' => $this->account->id,
        'customer_country' => 'CA',
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact2->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    $response = FunnelGenServer::tool(ListOrderTool::class, [
        'account_id' => $this->account->id,
        'customer_country' => 'US',
        'date_range' => 'last_7_days',
    ]);

    $response->assertOk();
    $response->assertSee('"total_orders": 1');
    $response->assertSee('"customer_country": "US"');
});

it('respects limit parameter', function () {
    for ($i = 0; $i < 15; $i++) {
        $orderFact = OrderFact::factory()->create([
            'account_id' => $this->account->id,
        ]);

        Transaction::factory()->create([
            'order_fact_id' => $orderFact->id,
            'account_id' => $this->account->id,
            'type' => TransactionType::PAYMENT,
            'processed_at' => Carbon::now()->subDays(5),
        ]);
    }

    $response = FunnelGenServer::tool(ListOrderTool::class, [
        'account_id' => $this->account->id,
        'limit' => 10,
        'date_range' => 'last_7_days',
    ]);

    $response->assertOk();
    $response->assertSee('"total_orders": 10');
});

it('includes transaction details in period_transactions', function () {
    $orderFact = OrderFact::factory()->create([
        'account_id' => $this->account->id,
    ]);

    Transaction::factory()->create([
        'order_fact_id' => $orderFact->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'amount_total' => 1000,
        'amount_net' => 950,
        'amount_tax' => 50,
        'currency_code' => 'USD',
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    $response = FunnelGenServer::tool(ListOrderTool::class, [
        'account_id' => $this->account->id,
        'date_range' => 'last_7_days',
    ]);

    $response->assertOk();
    $response->assertSee('"period_transactions"');
    $response->assertSee('"type": "payment"');
    $response->assertSee('"amount_total": "$10.00"');
    $response->assertSee('"amount_net": "$9.50"');
    $response->assertSee('"currency_code": "USD"');
});

it('handles refunds in revenue calculations', function () {
    $orderFact = OrderFact::factory()->create([
        'account_id' => $this->account->id,
    ]);

    // Payment
    Transaction::factory()->create([
        'order_fact_id' => $orderFact->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::PAYMENT,
        'amount_total' => 1000,
        'amount_net' => 950,
        'processed_at' => Carbon::now()->subDays(5),
    ]);

    // Refund
    Transaction::factory()->create([
        'order_fact_id' => $orderFact->id,
        'account_id' => $this->account->id,
        'type' => TransactionType::REFUND,
        'amount_total' => -500,
        'amount_net' => -475,
        'processed_at' => Carbon::now()->subDays(3),
    ]);

    $response = FunnelGenServer::tool(ListOrderTool::class, [
        'account_id' => $this->account->id,
        'date_range' => 'last_7_days',
    ]);

    $response->assertOk();
    $response->assertSee('"period_revenue": "$5.00"'); // 1000 - 500
    $response->assertSee('"transaction_count": 2');
    $response->assertSee('"type": "refund"');
});
