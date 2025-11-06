<?php

use CerberusIAM\Filters\UserDirectoryFilter;
use Illuminate\Http\Request;

/**
 * User Directory Filter Tests
 *
 * Tests the UserDirectoryFilter class functionality, ensuring that HTTP request
 * parameters are correctly translated into IAM API query parameters for filtering
 * user directory results.
 */

// Test that request filters are properly translated to IAM query parameters
it('translates request filters into IAM query parameters', function () {
    // Create a mock HTTP request with filter parameters
    $request = Request::create('/users', 'GET', [
        'email' => 'jane@example.com',
        'search' => 'Jane',
        'mfa' => 'true',
        'status' => 'active',
    ]);

    // Instantiate the filter with the request
    $filter = new UserDirectoryFilter($request);

    // Convert the filter to query parameters
    $params = $filter->toQueryParameters();

    // Assert that the parameters match the expected IAM API format
    expect($params)->toMatchArray([
        'filter[email]' => 'jane@example.com',
        'filter[search]' => 'Jane',
        'filter[mfa_enabled]' => 'true',
        'filter[status]' => 'active',
    ]);
});
