<?php

declare(strict_types=1);

namespace CerberusIAM\Repositories;

use CerberusIAM\Contracts\IamClient;
use CerberusIAM\Contracts\UserRepository;
use CerberusIAM\Filters\UserDirectoryFilter;
use Illuminate\Http\Request;
use InvalidArgumentException;

use function Fetch\fetch;

/**
 * User Directory Repository
 *
 * This class implements the UserRepository interface, providing methods
 * to interact with the user directory in the Cerberus IAM system.
 */
class UserDirectoryRepository implements UserRepository
{
    /**
     * Create a new user directory repository instance.
     *
     * @param  IamClient  $client  The IAM client for API communication.
     */
    public function __construct(
        protected IamClient $client
    ) {}

    /**
     * List users for a given organisation.
     *
     * This method retrieves a paginated list of users from the user directory
     * for the specified organisation, applying filters from the request.
     *
     * @param  string  $organisationSlug  The slug of the organisation.
     * @param  Request  $request  The HTTP request containing filter parameters.
     * @param  array<string, mixed>  $options  Additional options like page and per_page.
     * @param  string|null  $accessToken  Optional access token for authentication.
     * @param  string|null  $sessionToken  Optional session token for authentication.
     * @return array<string, mixed> The list of users with metadata.
     *
     * @throws InvalidArgumentException If neither access token nor session token is provided.
     */
    public function list(
        string $organisationSlug,
        Request $request,
        array $options = [],
        ?string $accessToken = null,
        ?string $sessionToken = null
    ): array {
        // Ensure at least one authentication token is provided
        if (empty($accessToken) && empty($sessionToken)) {
            throw new InvalidArgumentException('Either an access token or session token must be provided.');
        }

        // Create a filter from the request parameters
        $filter = new UserDirectoryFilter($request);
        // Build the query parameters including pagination and filters
        $query = array_merge([
            'page' => $options['page'] ?? 1,
            'per_page' => $options['per_page'] ?? 15,
        ], $filter->toQueryParameters());

        // Prepare headers for the API request
        $headers = [
            'Accept' => 'application/json',
            'X-Org-Domain' => $organisationSlug,
        ];

        // Add authorization header if access token is provided
        if ($accessToken) {
            $headers['Authorization'] = 'Bearer '.$accessToken;
        }

        // Add session cookie if session token is provided
        if ($sessionToken) {
            $cookieName = $this->client->sessionCookieName();

            if (! $cookieName) {
                throw new InvalidArgumentException('Session cookie name is not configured for Cerberus.');
            }

            $headers['Cookie'] = sprintf('%s=%s', $cookieName, $sessionToken);
        }

        // Make the GET request to the user directory endpoint
        $response = fetch($this->client->url('/v1/admin/users'), [
            'method' => 'GET',
            'headers' => $headers,
            'query' => $query,
        ]);

        // Handle unsuccessful responses
        if (! $response->successful()) {
            $error = null;

            try {
                $error = $response->json();
            } catch (\Throwable $exception) {
                $error = ['message' => $response->text()];
            }

            // Return empty data with error metadata
            return [
                'data' => [],
                'meta' => [
                    'error' => $error,
                ],
            ];
        }

        // Return the successful JSON response
        return $response->json();
    }
}
