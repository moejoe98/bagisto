<?php

namespace App\Api\Metadata;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use App\Api\Models\StoreConfig;
use Webkul\BagistoApi\Models\StoreConfig as BaseStoreConfig;

/**
 * Lists the API resources with the package's classes swapped for the store's own subclasses.
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
     * Create a new factory around the one API Platform builds from the configured resource paths.
     */
    public function __construct(protected ResourceNameCollectionFactoryInterface $decorated) {}

    /**
     * Create the resource name collection with the replacements applied.
     */
    public function create(): ResourceNameCollection
    {
        $classes = [];

        foreach ($this->decorated->create() as $resourceClass) {
            $classes[] = self::REPLACEMENTS[$resourceClass] ?? $resourceClass;
        }

        return new ResourceNameCollection(array_values(array_unique($classes)));
    }
}
