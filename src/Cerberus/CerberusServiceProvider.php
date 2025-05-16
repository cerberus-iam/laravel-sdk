<?php

namespace Cerberus;

use Cerberus\Contracts\TokenStorage;
use Cerberus\Guards\SessionGuard;
use Cerberus\Guards\TokenGuard;
use Cerberus\Storage\SessionTokenStorage;
use Fetch\Interfaces\ClientHandler as ClientHandlerInterface;
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
            return fetch_client([
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
        $this->app->singleton(TokenStorage::class, function (Application $app) {
            return new SessionTokenStorage(
                Cerberus::TOKEN_STORAGE_KEY,
                $app['session.store']
            );
        });
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
        Auth::extend('session', function (Application $app, string $name, array $config) {
            $guard = new SessionGuard(
                name: $name,
                provider: Auth::createUserProvider($config['provider']),
                session: $this->app['session.store'],
                rehashOnLogin: $this->app['config']->get('hashing.rehash_on_login', true),
            );

            $guard->setCookieJar($this->app['cookie']);
            $guard->setDispatcher($this->app['events']);
            $guard->setRequest($this->app->refresh('request', $guard, 'setRequest'));
            $guard->setRememberDuration($config['remember'] ?? 1440);

            return $guard;
        });

        Auth::extend('api', function (Application $app, string $name, array $config) {
            $guard = new TokenGuard(
                provider: Auth::createUserProvider($config['provider']),
                request: $app['request']
            );

            $this->app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }
}
