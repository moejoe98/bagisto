<?php

namespace App\Api\Models;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\OpenApi\Model\Operation;
use App\Api\Resolver\StoreConfigQueryResolver;
use App\Api\State\StoreConfigProvider;
use Webkul\BagistoApi\Models\StoreConfig as BaseStoreConfig;

/**
 * The package's public store settings, plus the cart, mini cart and login settings the mobile app reads.
 */
#[ApiResource(
    routePrefix: '/api/shop',
    shortName: 'StoreConfig',
    paginationEnabled: false,
    normalizationContext: ['skip_null_values' => false],
    operations: [
        new GetCollection(
            uriTemplate: '/store-config',
            provider: StoreConfigProvider::class,
            paginationEnabled: false,
            openapi: new Operation(
                tags: ['Store Config'],
                summary: 'Get the public store settings of the current channel',
                description: 'Returns the non-secret settings a storefront or app needs, including the cart, mini cart and login redirect settings. Send `X-Channel` and `X-Locale` to read another channel or locale. Public endpoint.',
            ),
        ),
    ],
    graphQlOperations: [
        new Query(
            resolver: StoreConfigQueryResolver::class,
            args: [],
        ),
    ],
)]
class StoreConfig extends BaseStoreConfig
{
    /**
     * How the mini cart counts its contents: display_item_quantity or display_number_of_items_in_cart.
     */
    public ?string $cart_summary = null;

    /**
     * Whether the shopper can open the cart page.
     */
    public ?bool $cart_page_enabled = null;

    /**
     * Where a customer lands after logging in: home or account.
     */
    public ?string $login_redirect_to = null;

    /**
     * Whether the mini cart is shown.
     */
    public ?bool $mini_cart_enabled = null;

    /**
     * The offer text shown in the mini cart.
     */
    public ?string $mini_cart_offer_info = null;
}
