<?php

declare(strict_types=1);

namespace CerberusIAM\Tests;

use CerberusIAM\Providers\CerberusIamServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base Test Case
 *
 * This abstract class serves as the base test case for the Cerberus IAM package tests,
 * extending Orchestra Testbench for Laravel package testing.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Get the package providers for the test.
     *
     * @param  mixed  $app  The application instance.
     * @return array<int, string> The list of package providers.
     */
    protected function getPackageProviders($app): array
    {
        // Return the Cerberus IAM service provider for testing
        return [
            CerberusIamServiceProvider::class,
        ];
    }
}
