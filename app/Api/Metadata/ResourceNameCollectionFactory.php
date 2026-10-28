<?php

namespace App\Api\Metadata;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use App\Api\Models\CustomerRefund;
use App\Api\Models\StoreConfig;
use Webkul\BagistoApi\Models\StoreConfig as BaseStoreConfig;

/**
 * Lists the API resources with the package's classes swapped for the store's own, and the store's own added.
 */
class ResourceNameCollectionFactory implements ResourceNameCollectionFactoryInterface
{
    /**
     * Package resource classes and the store classes that replace them.
     */
    public const REPLACEMENTS = [
        BaseStoreConfig::class => StoreConfig::class,
    ];

    /**
     * Store resource classes the package has no counterpart for.
     */
    public const ADDITIONS = [
        CustomerRefund::class,
    ];

    /**
     * Create a new factory around the one API Platform builds from the configured resource paths.
     */
    public function __construct(protected ResourceNameCollectionFactoryInterface $decorated) {}

    /**
     * Create the resource name collection with the replacements and additions applied.
     */
    public function create(): ResourceNameCollection
    {
        $classes = self::ADDITIONS;

        foreach ($this->decorated->create() as $resourceClass) {
            $classes[] = self::REPLACEMENTS[$resourceClass] ?? $resourceClass;
        }

        return new ResourceNameCollection(array_values(array_unique($classes)));
    }
}
