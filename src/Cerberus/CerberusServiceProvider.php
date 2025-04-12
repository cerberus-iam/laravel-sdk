<?php

namespace Cerberus;

use Cerberus\Contracts\TokenStorage;
use Cerberus\Guards\TokenGuard;
use Fetch\Interfaces\ClientHandler as ClientHandlerInterface;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class CerberusServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->registerHttpClient();
        $this->registerTokenStorage();
        $this->registerCerberusSingleton();
    }

    /**
     * Bootstrap any application authentication services.
     */
    public function boot(): void
    {
        $this->registerAuthProvider();
        $this->registerAuthGuard();
    }

    /**
     * Register the Cerberus HTTP client.
     */
    protected function registerHttpClient(): void
    {
        $this->app->singleton(ClientHandlerInterface::class, function (Application $app) {
            return fetch(url: null, options: [
                'base_uri' => Cerberus::getBaseUri(),
                'headers' => Cerberus::getHttpHeaders(),
            ]);
        });
    }

    /**
     * Register the Cerberus core singleton.
     */
    protected function registerCerberusSingleton(): void
    {
        $this->app->singleton(Cerberus::class, fn (
            Application $app
        ) => $this->createCerberusInstance($app));
    }

    /**
     * Register the token storage implementation.
     *
     * @throws \RuntimeException
     */
    protected function registerTokenStorage(): void
    {
        $this->app->singleton(
            TokenStorage::class,
            fn (Application $app) => new CacheTokenStorage
        );
    }

    /**
     * Create a Cerberus instance.
     */
    protected function createCerberusInstance(Application $app): Cerberus
    {
        return new Cerberus($app->make(ClientHandlerInterface::class));
    }

    /**
     * Register the Cerberus user provider for Auth.
     */
    protected function registerAuthProvider(): void
    {
        Auth::provider('cerberus', function (Application $app, array $config) {
            return $this->createUserProvider($app);
        });
    }

    /**
     * Create the CerberusUserProvider instance.
     */
    protected function createUserProvider(Application $app): CerberusUserProvider
    {
        return new CerberusUserProvider($app->make(Cerberus::class));
    }

    /**
     * Register the Cerberus token-based authentication guard.
     */
    protected function registerAuthGuard(): void
    {
        Auth::extend('cerberus', fn (
            Application $app,
            string $name,
            array $config
        ) => $this->createAuthGuard($app, $config));
    }

    /**
     * Create the TokenGuard instance.
     */
    protected function createAuthGuard(Application $app, array $config): Guard
    {
        return tap(
            new TokenGuard(
                provider: Auth::createUserProvider($config['provider']),
                request: $app['request']
            ),
            fn (TokenGuard $guard) => $app->refresh('request', $guard, 'setRequest')
        );
    }
}
