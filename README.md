# Cerberus IAM - Laravel SDK

A Laravel SDK for [Cerberus IAM](https://cerberus-iam.com), providing seamless integration with Cerberus' identity and access management platform.

## Requirements

- PHP >= 8.1
- Laravel >= 10.x

## Installation

```bash
composer require cerberus-iam/laravel-sdk
```

## Configuration

1. Register an account at [cerberus-iam.com/register](https://cerberus-iam.com/register).

2. Once registered, you'll receive your client credentials via email. These include a client ID and client secret for either `password` or `client_credentials` grant types.

3. Set the following values in your `.env` file:

```env
CERBERUS_API_KEY="your-client-id"
CERBERUS_API_SECRET="your-client-secret"
CERBERUS_API_URL="https://api.cerberus-iam.com"
```

4. Add the Cerberus configuration to `config/services.php`:

```php
'cerberus' => [
    'url' => env('CERBERUS_API_URL'),
    'key' => env('CERBERUS_API_KEY'),
    'secret' => env('CERBERUS_API_SECRET'),
],
```

5. Update `config/auth.php` to register Cerberus as a guard and provider:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'cerberus',
    ],

    'api' => [
        'driver' => 'cerberus',
        'provider' => 'cerberus',
    ],
],

'providers' => [
    'cerberus' => [
        'driver' => 'cerberus',
        'model' => Cerberus\Resources\User::class,
    ],
],
```

## Authentication

Cerberus supports both session-based (web) and token-based (API) authentication strategies.

The SDK includes:

- A custom `SessionGuard` for Laravel's session authentication
- A `User` model compatible with Laravel's authentication contracts

## User Model

Cerberus ships with its own `User` model that implements:

- `Illuminate\Contracts\Auth\Authenticatable`
- `Illuminate\Contracts\Auth\CanResetPassword`
- `Illuminate\Contracts\Auth\Access\Authorizable`

```php
Cerberus\Resources\User
```

This model supports authorization gates, role assignment, and permission checks.

## Usage

### Quickstart Example

```php
if (Auth::attempt(['email' => $email, 'password' => $password])) {
    $user = Auth::user();
    return redirect()->intended('dashboard');
}
```

### Access the Cerberus Client

```php
$cerberus = app(Cerberus\Cerberus::class);
```

### Retrieve a User

```php
$user = $cerberus->users()->find('user-id');
```

### Act As a User

```php
$cerberus->actingAs($user);
```

### Assign a Role to a User

```php
$role = $cerberus->roles()->find('admin');
$user->assignRole($role);
```

### Check User Permissions

```php
if ($user->can('edit-posts')) {
    // Authorized
}
```

## Available Resources

Cerberus exposes all IAM resources dynamically:

```php
$cerberus->users();
$cerberus->roles();
$cerberus->teams();
$cerberus->permissions();
$cerberus->clients();
```

To override or bind your own implementations:

```php
Cerberus::useResource('users', CustomUser::class);
```

## Advanced Features

### Token Lifecycle Management

- Automatic handling of access token acquisition and refresh
- Token storage is handled internally by the SDK
- Session-based storage for `SessionGuard`, and cache-based for client credential flows

### Impersonation

```php
$cerberus->impersonate('user-id');
```

### Multi-Tenancy

Use alternate credentials dynamically:

```php
$cerberus->withClient('other-client-id', 'other-secret');
```

## Laravel Integration

- Works with `Auth::user()` and `Auth::attempt()`
- Compatible with `@auth`, `@guest`, and route middleware like `auth`

```php
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'));
});
```

## Customization

- Extend resource classes by using `Cerberus::useResource()`
- Override the default user model with `Cerberus::useUserModel(CustomUser::class)`

## Security Considerations

- All communication must occur over HTTPS
- Tokens are never exposed in plaintext beyond initial response
- Passwords are never stored or handled manually

## Testing

Run unit tests with PHPUnit:

```bash
phpunit
```

Use mocking to simulate Cerberus behavior in integration tests.

## Contributing

Contributions are welcome. Please ensure all pull requests include relevant tests and documentation updates.

## License

This SDK is open-sourced software licensed under the [MIT license](LICENSE).
