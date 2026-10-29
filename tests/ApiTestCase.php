<?php

namespace Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Webkul\BagistoApi\Models\StorefrontKey;
use Webkul\Core\Models\Channel;
use Webkul\Customer\Models\Customer;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderItem;

abstract class ApiTestCase extends TestCase
{
    /**
     * The storefront key every request is sent with.
     */
    protected string $storefrontKey = '';

    /**
     * Create the storefront key and turn off the repository cache.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->storefrontKey = 'pk_storefront_'.Str::random(32);

        StorefrontKey::create([
            'name' => 'Test '.$this->storefrontKey,
            'key_type' => 'shop',
            'key' => $this->storefrontKey,
            'is_active' => true,
            'rate_limit' => 100000,
        ]);

        config([
            'repository.cache.enabled' => false,
            'repository.cache.repositories' => [],
        ]);
    }

    /**
     * Send a GraphQL request as JSON, as the given customer when one is passed.
     */
    protected function graphQl(string $query, array $variables = [], ?Customer $customer = null): TestResponse
    {
        return $this->postJson('/api/graphql', array_filter([
            'query' => $query,
            'variables' => $variables,
        ]), $this->apiHeaders($customer));
    }

    /**
     * Send a GraphQL multipart request carrying files, following the GraphQL multipart request specification.
     *
     * The files are passed on as plain Symfony uploads, which is what the GraphQL entrypoint hands a resolver on a real
     * server, rather than the Laravel uploads the test client would otherwise create.
     *
     * @param  array<string, UploadedFile>  $files  Files keyed by their variable path, e.g. "input.images.0".
     */
    protected function graphQlUpload(string $query, array $variables, array $files, Customer $customer): TestResponse
    {
        $map = [];
        $parts = [];

        foreach (array_values($files) as $index => $file) {
            $map[(string) $index] = ['variables.'.array_keys($files)[$index]];
            $parts[(string) $index] = new SymfonyUploadedFile($file->getPathname(), $file->getClientOriginalName(), $file->getClientMimeType(), null, true);
        }

        return $this->call('POST', '/api/graphql', [
            'operations' => json_encode(['query' => $query, 'variables' => $variables]),
            'map' => json_encode($map),
        ], [], $parts, $this->transformHeadersToServerVars($this->apiHeaders($customer) + [
            'Content-Type' => 'multipart/form-data; boundary=test',
            'Accept' => 'application/json',
        ]));
    }

    /**
     * The storefront key header, and a bearer token when a customer is passed.
     *
     * The guards are forgotten first, so a request never reuses the customer the previous one authenticated.
     */
    protected function apiHeaders(?Customer $customer = null): array
    {
        $this->app['auth']->forgetGuards();

        $headers = ['X-STOREFRONT-KEY' => $this->storefrontKey];

        if ($customer) {
            $headers['Authorization'] = 'Bearer '.$customer->createToken('test')->plainTextToken;
        }

        return $headers;
    }

    /**
     * Create a customer.
     */
    protected function createCustomer(): Customer
    {
        return Customer::factory()->create();
    }

    /**
     * Create a completed, invoiced order for the customer with one item that can still be returned.
     *
     * @return array{order: Order, orderItem: OrderItem}
     */
    protected function createReturnableOrder(Customer $customer): array
    {
        $channel = Channel::first();
        $product = Product::factory()->create();

        DB::table('product_flat')->insert([
            'product_id' => $product->id,
            'sku' => 'RETURNABLE-'.$product->id,
            'name' => 'Returnable Product',
            'url_key' => 'returnable-'.$product->id,
            'status' => 1,
            'visible_individually' => 1,
            'locale' => app()->getLocale(),
            'channel' => $channel->code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'channel_id' => $channel->id,
            'status' => 'completed',
        ]);

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => 'RETURNABLE-'.$product->id,
            'type' => 'simple',
            'name' => 'Returnable Product',
            'qty_ordered' => 2,
            'qty_invoiced' => 2,
            'qty_refunded' => 0,
            'qty_canceled' => 0,
            'rma_return_period' => 30,
        ]);

        return ['order' => $order, 'orderItem' => $orderItem];
    }
}
