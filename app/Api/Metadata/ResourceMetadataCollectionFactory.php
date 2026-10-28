<?php

namespace App\Api\Metadata;

use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use App\Api\State\CustomerReturnMessageProcessor;
use App\Api\State\CustomerReturnProcessor;
use Webkul\BagistoApi\Models\CustomerReturn;
use Webkul\BagistoApi\Models\CustomerReturnMessage;

/**
 * Adds file uploads to the package's create mutations for returns and return messages.
 */
class ResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    /**
     * The upload argument and processor each resource's create mutation gains.
     */
    public const UPLOADS = [
        CustomerReturn::class => [
            'args' => ['images' => ['type' => '[Upload]', 'description' => 'Images or videos showing the returned item, sent as GraphQL multipart uploads']],
            'processor' => CustomerReturnProcessor::class,
        ],
        CustomerReturnMessage::class => [
            'args' => ['file' => ['type' => 'Upload', 'description' => 'A file attached to the message, sent as a GraphQL multipart upload']],
            'processor' => CustomerReturnMessageProcessor::class,
        ],
    ];

    /**
     * Create a new factory around the one API Platform builds from the resource attributes.
     */
    public function __construct(protected ResourceMetadataCollectionFactoryInterface $decorated) {}

    /**
     * Create the resource metadata, with the uploads added to the create mutations.
     */
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        if (! isset(self::UPLOADS[$resourceClass])) {
            return $collection;
        }

        $upload = self::UPLOADS[$resourceClass];

        foreach ($collection as $index => $resource) {
            $operations = $resource->getGraphQlOperations() ?? [];

            foreach ($operations as $key => $operation) {
                if (
                    $operation instanceof Mutation
                    && $operation->getName() === 'create'
                ) {
                    $operations[$key] = $operation
                        ->withExtraArgs(($operation->getExtraArgs() ?? []) + $upload['args'])
                        ->withProcessor($upload['processor']);
                }
            }

            $collection[$index] = $resource->withGraphQlOperations($operations);
        }

        return $collection;
    }
}
