<?php

namespace Cerberus;

use App\Auth\Guards\TokenGuard;
use Fetch\Http\ClientHandler;
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
        $this->app->singleton(Cerberus::class, function (Application $app) {
            $http = new ClientHandler(null, [
                'base_uri' => rtrim(sprintf(
                    '%s/%s',
                    Cerberus::API_URI,
                    Cerberus::API_VERSION
                ), '/'),

                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    Cerberus::HEADER_CLIENT_ID => $app['config']->get('services.cerberus.key'),
                    Cerberus::HEADER_CLIENT_SECRET => $app['config']->get('services.cerberus.secret'),
                ],
            ]);

            return new Cerberus($http);
        });
    }

    /**
     * Bootstrap any authentication services.
     */
    public function boot(): void
    {
        Auth::provider('cerberus', function ($app, array $config) {
            return new CerberusUserProvider($app->make(Cerberus::class));
        });

        Auth::extend('cerberus', function ($app, $name, array $config) {
            return tap(
                new TokenGuard(
                    provider: Auth::createUserProvider($config['provider']),
                    request: $app['request']
                ),
                fn ($guard) => $app->refresh('request', $guard, 'setRequest')
            );
        });
    }
}
