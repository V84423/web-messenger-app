<?php

namespace RTippin\Messenger;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Http\Middleware\MessengerApi;
use RTippin\Messenger\Http\Middleware\SetMessengerProvider;

/**
 * @property-read Application $app
 */
trait RouteMap
{
    /**
     * Register our middleware.
     *
     * @return void
     *
     * @throws BindingResolutionException
     */
    private function registerRouterServices(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $router = $this->app->make(Router::class);

        $this->registerMiddleware(
            kernel: $kernel,
            router:  $router
        );

        $this->registerRoutes($router);

        $this->configureRateLimiting();
    }

    /**
     * Register our middleware.
     *
     * @param  Kernel  $kernel
     * @param  Router  $router
     * @return void
     */
    private function registerMiddleware(Kernel $kernel, Router $router): void
    {
        $kernel->prependToMiddlewarePriority(MessengerApi::class);

        $router->aliasMiddleware(
            name: 'messenger.provider',
            class: SetMessengerProvider::class
        );
    }

    /**
     * Register all routes used by messenger.
     *
     * @param  Router  $router
     * @return void
     */
    private function registerRoutes(Router $router): void
    {
        $router->group(
            attributes: $this->apiRouteConfiguration(),
            routes: fn () => $this->loadRoutesFrom(__DIR__.'/../routes/api.php')
        );

        $router->group(
            attributes: $this->apiRouteConfiguration(invite: true),
            routes: fn () => $this->loadRoutesFrom(__DIR__.'/../routes/invite_api.php')
        );

        $router->group(
            attributes: $this->assetsRouteConfiguration(),
            routes: fn () => $this->loadRoutesFrom(__DIR__.'/../routes/assets.php')
        );
    }

    /**
     * Configure the rate limiters for Messenger.
     *
     * @return void
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('messenger-api', function (Request $request) {
            return Messenger::getApiRateLimit() > 0
                ? Limit::perMinute(Messenger::getApiRateLimit())->by($request->user()?->getKey() ?: $request->ip())
                : Limit::none();
        });

        RateLimiter::for('messenger-message', function (Request $request) {
            return Messenger::getMessageRateLimit() > 0
                ? Limit::perMinute(Messenger::getMessageRateLimit())->by(
                    $request->route()->originalParameter('thread').'.'.$request->user()?->getKey() ?: $request->ip()
                )
                : Limit::none();
        });

        RateLimiter::for('messenger-attachment', function (Request $request) {
            return Messenger::getAttachmentRateLimit() > 0
                ? Limit::perMinute(Messenger::getAttachmentRateLimit())->by(
                    $request->route()->originalParameter('thread').'.'.$request->user()?->getKey() ?: $request->ip()
                )
                : Limit::none();
        });

        RateLimiter::for('messenger-search', function (Request $request) {
            return Messenger::getSearchRateLimit() > 0
                ? Limit::perMinute(Messenger::getSearchRateLimit())->by($request->user()?->getKey() ?: $request->ip())
                : Limit::none();
        });
    }

    /**
     * Get the Messenger API route group configuration array.
     *
     * @param  bool  $invite
     * @return array
     */
    private function apiRouteConfiguration(bool $invite = false): array
    {
        return [
            'domain' => config('messenger.routing.api.domain'),
            'prefix' => trim(config('messenger.routing.api.prefix'), '/'),
            'middleware' => $this->mergeApiMiddleware(
                $invite
                    ? config('messenger.routing.api.invite_api_middleware')
                    : config('messenger.routing.api.middleware')
            ),
        ];
    }

    /**
     * Get the Messenger API route group configuration array.
     *
     * @return array
     */
    private function assetsRouteConfiguration(): array
    {
        return [
            'domain' => config('messenger.routing.assets.domain'),
            'prefix' => trim(config('messenger.routing.assets.prefix'), '/'),
            'middleware' => config('messenger.routing.assets.middleware'),
        ];
    }
}
