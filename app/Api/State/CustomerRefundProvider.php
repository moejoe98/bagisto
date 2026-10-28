<?php

namespace App\Api\State;

use ApiPlatform\Laravel\Eloquent\Paginator;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Models\CustomerRefund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Webkul\BagistoApi\Exception\AuthorizationException;
use Webkul\BagistoApi\Exception\ResourceNotFoundException;
use Webkul\Customer\Models\Customer;
use Webkul\Sales\Repositories\RefundRepository;

/**
 * Provides the refunds issued on the authenticated customer's orders.
 */
class CustomerRefundProvider implements ProviderInterface
{
    /**
     * The most refunds one page can hold.
     */
    public const MAX_PER_PAGE = 50;

    /**
     * Create a new provider.
     */
    public function __construct(protected RefundRepository $refundRepository) {}

    /**
     * Provide one refund or a page of refunds.
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $customer = Auth::guard('sanctum')->user();

        if (! $customer) {
            throw new AuthorizationException(__('bagistoapi::app.graphql.logout.unauthenticated'));
        }

        if (
            $operation instanceof GetCollection
            || $operation instanceof QueryCollection
        ) {
            return $this->provideCollection($customer->id, $context);
        }

        return $this->provideItem($customer->id, $uriVariables['id'] ?? $context['args']['id'] ?? null);
    }

    /**
     * One refund of the customer's, by id or IRI.
     */
    protected function provideItem(int $customerId, mixed $id): CustomerRefund
    {
        $refund = $id ? $this->scopedQuery($customerId)->find(basename((string) $id)) : null;

        if (! $refund) {
            throw new ResourceNotFoundException(__('Refund not found.'));
        }

        return $this->build($refund);
    }

    /**
     * A page of the customer's refunds, newest first.
     */
    protected function provideCollection(int $customerId, array $context): Paginator
    {
        $args = $context['args'] ?? [];

        $query = $this->scopedQuery($customerId);

        $orderId = $args['orderId'] ?? request()->query('order_id');

        if (
            $orderId !== null
            && $orderId !== ''
        ) {
            $query->where('order_id', (int) $orderId);
        }

        $perPage = max(1, min((int) ($args['first'] ?? request()->query('per_page', 10)), self::MAX_PER_PAGE));
        $offset = $this->offset($args['after'] ?? null, $perPage);

        $total = (clone $query)->count();

        $items = $query->orderByDesc('id')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($refund) => $this->build($refund));

        return new Paginator(new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $total > 0 ? intdiv($offset, $perPage) + 1 : 1,
            ['path' => request()->url()]
        ));
    }

    /**
     * Refunds on orders the customer placed while logged in.
     */
    protected function scopedQuery(int $customerId): Builder
    {
        return $this->refundRepository->getModel()->newQuery()
            ->with(['items', 'order'])
            ->whereHas('order', fn ($query) => $query
                ->where('customer_id', $customerId)
                ->where('customer_type', Customer::class));
    }

    /**
     * The offset a cursor points past, or the requested page's offset.
     */
    protected function offset(?string $after, int $perPage): int
    {
        if ($after) {
            $decoded = base64_decode($after, true);

            return ctype_digit((string) $decoded) ? (int) $decoded + 1 : 0;
        }

        return max(0, ((int) request()->query('page', 1) - 1) * $perPage);
    }

    /**
     * Build the API resource from a refund.
     */
    protected function build($refund): CustomerRefund
    {
        $currency = $refund->order_currency_code;

        $customerRefund = new CustomerRefund;

        $customerRefund->id = (int) $refund->id;
        $customerRefund->refund_number = (string) ($refund->increment_id ?: $refund->id);
        $customerRefund->order_id = (int) $refund->order_id;
        $customerRefund->order_increment_id = $refund->order?->increment_id;
        $customerRefund->state = $refund->state;
        $customerRefund->total_qty = (int) $refund->total_qty;
        $customerRefund->currency_code = $currency;
        $customerRefund->created_at = $refund->created_at?->toIso8601String();

        foreach (['sub_total', 'shipping_amount', 'tax_amount', 'discount_amount', 'adjustment_refund', 'adjustment_fee', 'grand_total'] as $field) {
            $customerRefund->{$field} = (float) $refund->{$field};
            $customerRefund->{'formatted_'.$field} = core()->formatPrice((float) $refund->{$field}, $currency);
        }

        $customerRefund->items = $refund->items->map(fn ($item) => [
            'id' => (int) $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'qty' => (int) $item->qty,
            'price' => (float) $item->price,
            'total' => (float) $item->total,
            'formatted_price' => core()->formatPrice((float) $item->price, $currency),
            'formatted_total' => core()->formatPrice((float) $item->total, $currency),
        ])->values()->all();

        return $customerRefund;
    }
}
