<?php

namespace App\Api\Models;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Api\State\CustomerRefundProvider;

/**
 * A refund (credit memo) issued on one of the authenticated customer's orders.
 */
#[ApiResource(
    routePrefix: '/api/shop',
    shortName: 'CustomerRefund',
    normalizationContext: ['skip_null_values' => false],
    operations: [
        new GetCollection(
            uriTemplate: '/customer-refunds',
            provider: CustomerRefundProvider::class,
            openapi: new Operation(
                tags: ['Customer Refund'],
                summary: 'List the authenticated customer\'s refunds',
                description: 'Returns the refunds issued on the customer\'s orders, newest first. Filter with `order_id`, page with `page` and `per_page`. Requires a customer bearer token.',
            ),
        ),
        new Get(
            uriTemplate: '/customer-refunds/{id}',
            provider: CustomerRefundProvider::class,
            openapi: new Operation(
                tags: ['Customer Refund'],
                summary: 'Get one of the authenticated customer\'s refunds',
                description: 'Returns a refund issued on one of the customer\'s orders, with its items. Requires a customer bearer token.',
            ),
        ),
    ],
    graphQlOperations: [
        new Query(provider: CustomerRefundProvider::class),
        new QueryCollection(
            provider: CustomerRefundProvider::class,
            paginationType: 'cursor',
            args: [
                'orderId' => [
                    'type' => 'Int',
                    'description' => 'Only refunds of this order',
                ],
                'first' => ['type' => 'Int'],
                'after' => ['type' => 'String'],
            ],
        ),
    ],
)]
class CustomerRefund
{
    /**
     * The refund id.
     */
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    /**
     * The number shown to the customer: the increment id, or the refund id when none is set.
     */
    public ?string $refund_number = null;

    /**
     * The refunded order's id.
     */
    public ?int $order_id = null;

    /**
     * The refunded order's number.
     */
    public ?string $order_increment_id = null;

    /**
     * The refund state.
     */
    public ?string $state = null;

    /**
     * The number of items refunded.
     */
    public ?int $total_qty = null;

    /**
     * The currency the amounts are in.
     */
    public ?string $currency_code = null;

    /**
     * The refunded items' subtotal.
     */
    public ?float $sub_total = null;

    /**
     * The refunded shipping amount.
     */
    public ?float $shipping_amount = null;

    /**
     * The refunded tax amount.
     */
    public ?float $tax_amount = null;

    /**
     * The discount taken off the refund.
     */
    public ?float $discount_amount = null;

    /**
     * The extra amount refunded on top of the items.
     */
    public ?float $adjustment_refund = null;

    /**
     * The fee kept back from the refund.
     */
    public ?float $adjustment_fee = null;

    /**
     * The total refunded.
     */
    public ?float $grand_total = null;

    /**
     * The subtotal, formatted in the refund's currency.
     */
    public ?string $formatted_sub_total = null;

    /**
     * The shipping amount, formatted in the refund's currency.
     */
    public ?string $formatted_shipping_amount = null;

    /**
     * The tax amount, formatted in the refund's currency.
     */
    public ?string $formatted_tax_amount = null;

    /**
     * The discount, formatted in the refund's currency.
     */
    public ?string $formatted_discount_amount = null;

    /**
     * The adjustment refund, formatted in the refund's currency.
     */
    public ?string $formatted_adjustment_refund = null;

    /**
     * The adjustment fee, formatted in the refund's currency.
     */
    public ?string $formatted_adjustment_fee = null;

    /**
     * The total refunded, formatted in the refund's currency.
     */
    public ?string $formatted_grand_total = null;

    /**
     * The refunded items: id, name, sku, qty, price, total and their formatted amounts.
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $items = null;

    /**
     * When the refund was issued, as ISO 8601.
     */
    public ?string $created_at = null;
}
