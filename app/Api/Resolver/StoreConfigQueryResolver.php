<?php

namespace App\Api\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use App\Api\Models\StoreConfig;
use App\Api\State\StoreConfigProvider;

class StoreConfigQueryResolver implements QueryItemResolverInterface
{
    /**
     * Resolve the storeConfig query to the store settings of the current channel.
     */
    public function __invoke(?object $item, array $context): StoreConfig
    {
        return StoreConfigProvider::build();
    }
}
