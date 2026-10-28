<?php

namespace App\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Models\StoreConfig;
use Webkul\BagistoApi\State\StoreConfigProvider as BaseStoreConfigProvider;

/**
 * Builds the package's store settings and adds the cart, mini cart and login settings from the admin configuration.
 */
class StoreConfigProvider implements ProviderInterface
{
    /**
     * Provide the store settings of the current channel as a one-item collection.
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return [self::build()];
    }

    /**
     * Build the store settings of the current channel.
     */
    public static function build(): StoreConfig
    {
        $config = new StoreConfig;

        foreach (get_object_vars(BaseStoreConfigProvider::build()) as $property => $value) {
            $config->{$property} = $value;
        }

        $config->cart_summary = self::text('sales.checkout.mini_cart.summary');
        $config->cart_page_enabled = self::flag('sales.checkout.shopping_cart.cart_page');
        $config->login_redirect_to = self::text('customer.settings.login_options.redirected_to_page');
        $config->mini_cart_enabled = self::flag('sales.checkout.mini_cart.display_mini_cart');
        $config->mini_cart_offer_info = self::text('sales.checkout.mini_cart.offer_info');

        return $config;
    }

    /**
     * Read a boolean setting.
     */
    private static function flag(string $code): bool
    {
        return filter_var(core()->getConfigData($code), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Read a text setting, treating an empty value as unset.
     */
    private static function text(string $code): ?string
    {
        $value = core()->getConfigData($code);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
