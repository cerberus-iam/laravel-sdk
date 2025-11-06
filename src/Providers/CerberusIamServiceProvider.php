<?php

declare(strict_types=1);

namespace CerberusIAM\Providers;

use CerberusIAM\Auth\CerberusGuard;
use CerberusIAM\Auth\CerberusUserProvider;
use CerberusIAM\Contracts\IamClient;
use CerberusIAM\Contracts\OAuthStateStore;
use CerberusIAM\Contracts\TokenStore;
use CerberusIAM\Contracts\UserRepository;
use CerberusIAM\Http\Clients\CerberusClient;
use CerberusIAM\Middleware\EnsureCerberusAuthenticated;
use CerberusIAM\Repositories\UserDirectoryRepository;
use CerberusIAM\Support\Stores\SessionOAuthStateStore;
use CerberusIAM\Support\Stores\SessionTokenStore;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * Cerberus IAM Service Provider
 *
 * This service provider is responsible for registering and bootstrapping the Cerberus IAM package
 * within a Laravel application. It handles the registration of core services, authentication drivers,
 * middleware, and configuration publishing.
 */
class CerberusIamServiceProvider extends ServiceProvider
{
    /**
     * Register the services provided by this package.
     *
     * This method binds the core interfaces and implementations into the Laravel service container,
     * including the IAM client, user repository, token store, and OAuth state store.
     */
    public function register(): void
    {
        // Merge the package configuration with the application's config
        $this->mergeConfigFrom(__DIR__.'/../../config/cerberus-iam.php', 'cerberus-iam');

        // Register the CerberusClient as a singleton in the service container
        $this->app->singleton(CerberusClient::class, function (Container $app) {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            // Create a new CerberusClient instance with configuration values
            return new CerberusClient(
                $config->get('cerberus-iam.base_url'),
                $config->get('cerberus-iam.session_cookie'),
                $config->get('cerberus-iam.organisation_slug'),
                $config->get('cerberus-iam.oauth'),
                $config->get('cerberus-iam.http', [])
            );
        });

        // Bind the IamClient interface to the CerberusClient implementation
        $this->app->bind(IamClient::class, fn ($app) => $app->make(CerberusClient::class));

        // Register the UserDirectoryRepository as a singleton
        $this->app->singleton(UserDirectoryRepository::class, function (Container $app) {
            return new UserDirectoryRepository($app->make(IamClient::class));
        });

        // Bind the UserRepository interface to the UserDirectoryRepository implementation
        $this->app->bind(UserRepository::class, fn ($app) => $app->make(UserDirectoryRepository::class));

        // Bind the TokenStore interface to a factory that creates SessionTokenStore instances
        $this->app->bind(TokenStore::class, function ($app, array $parameters = []) {
            $key = $parameters['key'] ?? 'cerberus.tokens';

            return new SessionTokenStore($app['session.store'], $key);
        });

        // Bind the OAuthStateStore interface to a factory that creates SessionOAuthStateStore instances
        $this->app->bind(OAuthStateStore::class, function ($app, array $parameters = []) {
            $stateKey = $parameters['state_key'] ?? 'cerberus.oauth.state';
            $codeKey = $parameters['code_key'] ?? 'cerberus.oauth.code_verifier';

            return new SessionOAuthStateStore($app['session.store'], $stateKey, $codeKey);
        });
    }

    /**
     * Bootstrap the package services.
     *
     * This method publishes the configuration file, registers the authentication driver,
     * sets up middleware aliases, and loads routes if not cached.
     *
     * @param  AuthFactory  $auth  The Laravel authentication factory.
     */
    public function boot(AuthFactory $auth): void
    {
        // Publish the package configuration file to the application's config directory
        $this->publishes([
            __DIR__.'/../../config/cerberus-iam.php' => config_path('cerberus-iam.php'),
        ], 'config');

        // Register the Cerberus authentication driver
        $this->registerAuthDriver($auth);

        // Register the middleware alias for Cerberus authentication
        $this->app['router']->aliasMiddleware('cerberus.auth', EnsureCerberusAuthenticated::class);

        // Load the package routes if they are not cached
        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../../routes/web.php';
        }
    }

    /**
     * Register the Cerberus authentication driver.
     *
     * This method extends Laravel's authentication system with a custom 'cerberus' driver
     * and provider, allowing applications to use Cerberus IAM for user authentication.
     *
     * @param  AuthFactory  $auth  The Laravel authentication factory.
     */
    protected function registerAuthDriver(AuthFactory $auth): void
    {
        // Register the 'cerberus' user provider
        $auth->provider('cerberus', function ($app, array $config) {
            return new CerberusUserProvider($app->make(IamClient::class));
        });

        // Extend the authentication system with the 'cerberus' guard
        Auth::extend('cerberus', function ($app, string $name, array $config) {
            /** @var IamClient $client */
            $client = $app->make(IamClient::class);

            // Create the user provider for this guard
            $provider = $app['auth']->createUserProvider($config['provider'] ?? null);

            // Generate unique keys for token and state stores based on the guard name
            $tokenStoreKey = sprintf('cerberus.tokens.%s', $name);
            $stateStoreKey = sprintf('cerberus.oauth.%s', $name);

            // Create instances of token and state stores
            $tokenStore = $app->make(TokenStore::class, ['key' => $tokenStoreKey]);
            $stateStore = $app->make(OAuthStateStore::class, [
                'state_key' => "{$stateStoreKey}.state",
                'code_key' => "{$stateStoreKey}.code_verifier",
            ]);

            // Return a new CerberusGuard instance
            return new CerberusGuard(
                $name,
                $client,
                $provider,
                $tokenStore,
                $stateStore,
                $app['request'],
                $config
            );
        });
    }
}
