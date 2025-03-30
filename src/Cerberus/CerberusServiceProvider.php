<?php

namespace Cerberus;

use App\Auth\Guards\TokenGuard;
use Fetch\Http\ClientHandler;
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
            return new ClientHandler(null, [
                'base_uri' => $this->getBaseUri(),
                'headers' => $this->getHttpHeaders($app),
            ]);
        });
    }

    /**
     * Register the Cerberus core singleton.
     */
    protected function registerCerberusSingleton(): void
    {
        $this->app->singleton(Cerberus::class, fn (Application $app) => $this->createCerberusInstance($app));
    }

    /**
     * Create a Cerberus instance.
     */
    protected function createCerberusInstance(Application $app): Cerberus
    {
        return new Cerberus($app->make(ClientHandlerInterface::class));
    }

    /**
     * Get the base URI for Cerberus API.
     */
    protected function getBaseUri(): string
    {
        return rtrim(sprintf('%s/%s', Cerberus::API_URI, Cerberus::API_VERSION), '/');
    }

    /**
     * Get default HTTP headers for Cerberus client.
     */
    protected function getHttpHeaders(Application $app): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            Cerberus::API_KEY_NAME => $app['config']->get('services.cerberus.key'),
            Cerberus::API_SECRET_NAME => $app['config']->get('services.cerberus.secret'),
        ];
    }

    /**
     * Register the Cerberus user provider for Auth.
     */
    protected function registerAuthProvider(): void
    {
        Auth::provider('cerberus', fn (Application $app, array $config) => $this->createUserProvider($app));
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
        Auth::extend('cerberus', fn (Application $app, string $name, array $config) => $this->createAuthGuard($app, $config));
    }

    /**
     * Create the TokenGuard instance.
     */
    protected function createAuthGuard(Application $app, array $config): TokenGuard
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
