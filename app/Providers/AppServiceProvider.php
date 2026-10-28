<?php

namespace App\Providers;

use ApiPlatform\GraphQl\Error\ErrorHandlerInterface;
use ApiPlatform\GraphQl\ExecutorInterface;
use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use ApiPlatform\GraphQl\Type\Definition\UploadType;
use ApiPlatform\GraphQl\Type\FieldsBuilderEnumInterface;
use ApiPlatform\GraphQl\Type\TypesContainerInterface;
use ApiPlatform\GraphQl\Type\TypesFactoryInterface;
use ApiPlatform\Laravel\GraphQl\Controller\EntrypointController;
use ApiPlatform\Laravel\GraphQl\Controller\GraphiQlController;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use App\Api\Metadata\ResourceMetadataCollectionFactory;
use App\Api\Metadata\ResourceNameCollectionFactory;
use App\Api\Resolver\StoreConfigQueryResolver;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Negotiation\Negotiator;
use Symfony\Component\Serializer\SerializerInterface;
use Webkul\BagistoApi\GraphQl\QueryScopedSchemaBuilder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerApiExtensions();

        $allowedIPs = array_map('trim', explode(',', config('app.debug_allowed_ips', '')));

        $allowedIPs = array_filter($allowedIPs);

        if (empty($allowedIPs)) {
            return;
        }

        if (in_array(Request::ip(), $allowedIPs)) {
            Debugbar::enable();
        } else {
            Debugbar::disable();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ParallelTesting::setUpTestDatabase(function (string $database, int $token) {
            Artisan::call('db:seed');
        });
    }

    /**
     * Register the store's additions to the Bagisto API: its own resources, the upload mutations and multipart GraphQL.
     */
    protected function registerApiExtensions(): void
    {
        $this->app->extend(
            ResourceNameCollectionFactoryInterface::class,
            fn (ResourceNameCollectionFactoryInterface $factory) => new ResourceNameCollectionFactory($factory)
        );

        $this->app->extend(
            ResourceMetadataCollectionFactoryInterface::class,
            fn (ResourceMetadataCollectionFactoryInterface $factory) => new ResourceMetadataCollectionFactory($factory)
        );

        $this->app->tag(StoreConfigQueryResolver::class, QueryItemResolverInterface::class);

        $this->app->tag(UploadType::class, 'api_platform.graphql.type');

        $this->app->extend(
            EntrypointController::class,
            fn (EntrypointController $controller, Application $app) => $this->storefrontGraphQlEntrypoint($app)
        );
    }

    /**
     * The storefront GraphQL entrypoint the package binds, also accepting multipart requests that carry uploads.
     */
    protected function storefrontGraphQlEntrypoint(Application $app): EntrypointController
    {
        return new EntrypointController(
            new QueryScopedSchemaBuilder(
                $app->make(ResourceNameCollectionFactoryInterface::class),
                $app->make(ResourceMetadataCollectionFactoryInterface::class),
                $app->make(TypesFactoryInterface::class),
                $app->make(TypesContainerInterface::class),
                $app->make(FieldsBuilderEnumInterface::class),
            ),
            $app->make(ExecutorInterface::class),
            $app->make(GraphiQlController::class),
            $app->make(SerializerInterface::class),
            $app->make(ErrorHandlerInterface::class),
            debug: (bool) config('app.debug'),
            negotiator: $app->make(Negotiator::class),
            formats: config('api-platform.formats') + ['multipart' => ['multipart/form-data']],
        );
    }
}
