<?php

namespace Cerberus\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Auth\User;
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
            'auth.defaults.provider' => 'cerberus',
            'auth.providers.users.model' => User::class,
            'auth.guards.api' => ['driver' => 'cerberus', 'provider' => 'cerberus'],
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
}
