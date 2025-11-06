<?php

use CerberusIAM\Http\Clients\CerberusClient;

/**
 * Configuration Tests
 *
 * Tests that the package configuration is properly merged with defaults
 * and that the CerberusClient is configured correctly.
 */

// Test that default configuration values are properly merged
it('merges default configuration', function () {
    // Resolve the CerberusClient from the container
    /** @var CerberusClient $client */
    $client = $this->app->make(CerberusClient::class);

    // Verify the base URL is set to the default localhost
    expect($client->url('/health'))->toBe('http://localhost:4000/health');
    // Verify the session cookie name is set to the default
    expect($client->sessionCookieName())->toBe('cerb_sid');
});
