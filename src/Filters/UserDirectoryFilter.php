<?php

declare(strict_types=1);

namespace CerberusIAM\Filters;

use Filterable\Filter;
use Illuminate\Http\Request;

/**
 * User Directory Filter
 *
 * This class converts incoming HTTP filters to Cerberus API query parameters,
 * providing validation and transformation of filter values.
 */
class UserDirectoryFilter extends Filter
{
    /**
     * The list of allowed filters.
     *
     * @var array<int, string>
     */
    protected array $filters = [
        'email',
        'search',
        'role',
        'team',
        'organisation',
        'mfa',
        'status',
    ];

    /**
     * The payload array for query parameters.
     *
     * @var array<string, mixed>
     */
    protected array $payload = [];

    /**
     * Create a new user directory filter instance.
     *
     * @param  Request  $request  The HTTP request containing filter parameters.
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);

        // Enable validation and value transformation features
        $this->enableFeatures(['validation', 'valueTransformation']);
        // Set validation rules for each filter
        $this->setValidationRules([
            'email' => ['nullable', 'email'],
            'search' => ['nullable', 'string'],
            'organisation' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'team' => ['nullable', 'string'],
            'mfa' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,suspended,invited,blocked'],
        ]);

        // Register transformer for MFA filter to convert to boolean
        $this->registerTransformer('mfa', fn ($value) => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE));
    }

    /**
     * Convert the applied filters to query parameters.
     *
     * @return array<string, mixed> The query parameters for the API request.
     */
    public function toQueryParameters(): array
    {
        // Reset payload and apply filters
        $this->payload = [];
        $this->applyFilterables();

        // Filter out null and empty values
        return array_filter(
            $this->payload,
            fn ($value) => $value !== null && $value !== ''
        );
    }

    /**
     * Apply the email filter.
     *
     * @param  string  $value  The email value.
     */
    protected function email(string $value): void
    {
        $this->payload['filter[email]'] = $value;
    }

    /**
     * Apply the search filter.
     *
     * @param  string  $value  The search value.
     */
    protected function search(string $value): void
    {
        $this->payload['filter[search]'] = $value;
    }

    /**
     * Apply the organisation filter.
     *
     * @param  string  $value  The organisation value.
     */
    protected function organisation(string $value): void
    {
        $this->payload['filter[organisation]'] = $value;
    }

    /**
     * Apply the role filter.
     *
     * @param  string  $value  The role value.
     */
    protected function role(string $value): void
    {
        $this->payload['filter[role]'] = $value;
    }

    /**
     * Apply the team filter.
     *
     * @param  string  $value  The team value.
     */
    protected function team(string $value): void
    {
        $this->payload['filter[team]'] = $value;
    }

    /**
     * Apply the MFA filter.
     *
     * @param  bool|null  $value  The MFA value.
     */
    protected function mfa(?bool $value): void
    {
        if ($value === null) {
            return;
        }

        $this->payload['filter[mfa_enabled]'] = $value ? 'true' : 'false';
    }

    /**
     * Apply the status filter.
     *
     * @param  string  $value  The status value.
     */
    protected function status(string $value): void
    {
        $this->payload['filter[status]'] = $value;
    }
}
