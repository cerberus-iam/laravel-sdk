<?php

declare(strict_types=1);

namespace CerberusIAM\Contracts;

use Illuminate\Http\Request;

/**
 * User Repository Contract
 *
 * This interface defines the contract for user repository implementations,
 * providing methods to interact with user data in the Cerberus IAM system.
 */
interface UserRepository
{
    /**
     * List users for a given organisation.
     *
     * This method retrieves a list of users from the user directory for the specified organisation,
     * applying any filters or options provided in the request and options array.
     *
     * @param  string  $organisationSlug  The slug of the organisation to list users for.
     * @param  Request  $request  The HTTP request object containing query parameters.
     * @param  array<string, mixed>  $options  Additional options for filtering or pagination.
     * @param  string|null  $accessToken  Optional access token for authentication.
     * @param  string|null  $sessionToken  Optional session token for authentication.
     * @return array<string, mixed> The list of users with metadata.
     */
    public function list(string $organisationSlug, Request $request, array $options = [], ?string $accessToken = null, ?string $sessionToken = null): array;
}
