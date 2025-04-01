<?php

namespace Cerberus\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase, WithWorkbench;

    /**
     * Define the neccessary dependencies for the test.
     */
    protected function defineEnvironment($app)
    {
        $config = $app->make(Repository::class);

        $config->set([
            'auth.defaults.guard' => 'cerberus',
            'auth.defaults.provider' => 'cerberus',

            'auth.guards.cerberus' => [
                'driver' => 'cerberus',
                'provider' => 'cerberus',
            ],

            'auth.providers.cerberus' => [
                'driver' => 'cerberus',
                'model' => \Cerberus\Resources\User::class, // or your mock/fake class
            ],

            'database.default' => 'testing',
        ]);
    }

    /**
     * Set up the environment for the test.
     */
    protected function getEnvironmentSetUp($app)
    {
        $app->useEnvironmentPath(__DIR__.'/..');
        $app->bootstrapWith([LoadEnvironmentVariables::class]);

        parent::getEnvironmentSetUp($app);
    }

    /**
     * Define the package providers for the test.
     */
    protected function getPackageProviders($app)
    {
        return [
            \Cerberus\CerberusServiceProvider::class,
        ];
    }
}
