<?php

use Webkul\Customer\Models\Customer;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\Refund;
use Webkul\Sales\Models\RefundItem;

const LIST_REFUNDS = <<<'GQL'
    query Refunds($orderId: Int) {
      customerRefunds(orderId: $orderId) {
        edges { node { _id refundNumber orderId orderIncrementId adjustmentRefund adjustmentFee grandTotal formattedAdjustmentFee items } }
      }
    }
GQL;

function createRefund(Customer $customer, array $amounts = []): Refund
{
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'status' => 'closed',
    ]);

    $refund = Refund::create($amounts + [
        'order_id' => $order->id,
        'state' => 'refunded',
        'total_qty' => 1,
        'order_currency_code' => 'USD',
        'sub_total' => 40,
        'adjustment_refund' => 5,
        'adjustment_fee' => 2,
        'grand_total' => 43,
    ]);

    RefundItem::create([
        'refund_id' => $refund->id,
        'name' => 'Clear Case',
        'sku' => 'CASE-1',
        'qty' => 1,
        'price' => 40,
        'total' => 40,
    ]);

    return $refund;
}

// ====================================================================
// Refund List
// ====================================================================

it('lists the customer\'s refunds with their adjustments and items', function () {
    $customer = $this->createCustomer();
    $refund = createRefund($customer);

    createRefund($this->createCustomer());

    $response = $this->graphQl(LIST_REFUNDS, [], $customer);

    $response->assertOk();

    expect($response->json('errors'))->toBeNull();

    $nodes = $response->json('data.customerRefunds.edges.*.node');

    expect($nodes)->toHaveCount(1);

    $node = $nodes[0];

    expect((int) $node['_id'])->toBe($refund->id)
        ->and($node['refundNumber'])->toBe((string) $refund->id)
        ->and($node['orderId'])->toBe($refund->order_id)
        ->and($node['adjustmentRefund'])->toEqual(5.0)
        ->and($node['adjustmentFee'])->toEqual(2.0)
        ->and($node['grandTotal'])->toEqual(43.0)
        ->and($node['formattedAdjustmentFee'])->toContain('2')
        ->and($node['items'][0]['sku'])->toBe('CASE-1');
});

it('filters the refunds by order', function () {
    $customer = $this->createCustomer();
    $first = createRefund($customer);

    createRefund($customer);

    $nodes = $this->graphQl(LIST_REFUNDS, ['orderId' => $first->order_id], $customer)->json('data.customerRefunds.edges.*.node');

    expect($nodes)->toHaveCount(1)
        ->and((int) $nodes[0]['_id'])->toBe($first->id);
});

// ====================================================================
// Single Refund
// ====================================================================

it('returns one of the customer\'s refunds by id', function () {
    $customer = $this->createCustomer();
    $refund = createRefund($customer);

    $response = $this->graphQl('query { customerRefund(id: "/api/shop/customer-refunds/'.$refund->id.'") { _id refundNumber adjustmentRefund } }', [], $customer);

    expect($response->json('errors'))->toBeNull()
        ->and((int) $response->json('data.customerRefund._id'))->toBe($refund->id);
});

it('hides another customer\'s refund', function () {
    $refund = createRefund($this->createCustomer());

    $response = $this->graphQl('query { customerRefund(id: "/api/shop/customer-refunds/'.$refund->id.'") { _id } }', [], $this->createCustomer());

    expect($response->json('data.customerRefund'))->toBeNull()
        ->and($response->json('errors'))->not->toBeNull();
});

// ====================================================================
// Authentication
// ====================================================================

it('requires a logged in customer', function () {
    $response = $this->graphQl(LIST_REFUNDS);

    expect($response->json('errors'))->not->toBeNull();
});
