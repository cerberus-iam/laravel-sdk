# Cerberus IAM | Laravel SDK

A framework-agnostic bridge that lets any Laravel application outsource authentication and user directory responsibilities to the [Cerberus IAM API](../README.md).  Instead of maintaining local passwords, session stores, and role logic, you attach this package, configure a guard, and delegate the heavy lifting to Cerberus.

---

## Requirements

| Component          | Version                           |
| ------------------ | --------------------------------- |
| PHP                | 8.2+                              |
| Laravel components | 10.x / 11.x                       |
| cerberus-iam/api   | Compatible REST instance          |
| jerome/filterable  | ^2.0 (optional request filtering) |

> The package is designed for multi-tenant environments. Ensure the API instance you point to has the desired organisations, roles, and OAuth clients configured.

---

## Installation

```bash
composer require cerberus-iam/laravel-sdk
php artisan vendor:publish --provider="CerberusIAM\\Providers\\CerberusIamServiceProvider" --tag=config
```

Publishing yields `config/cerberus-iam.php` – the single source of truth for connecting to the IAM platform.

### Environment Variables

```dotenv
CERBERUS_IAM_URL=https://api.cerberus-iam.com
CERBERUS_IAM_CLIENT_ID=
CERBERUS_IAM_CLIENT_SECRET=
CERBERUS_IAM_USERNAME="admin@cerberus-iam.com"
CERBERUS_IAM_PASSWORD="Password123!"
CERBERUS_IAM_REDIRECT_URI="${APP_URL}/cerberus/callback"
CERBERUS_IAM_SCOPES="openid profile email"
CERBERUS_IAM_SESSION_COOKIE=cerb_sid
CERBERUS_IAM_ORG_SLUG=cerberus-iam
CERBERUS_IAM_HTTP_TIMEOUT=10
CERBERUS_IAM_HTTP_RETRY=true
CERBERUS_IAM_HTTP_RETRY_ATTEMPTS=2
CERBERUS_IAM_HTTP_RETRY_DELAY=100
CERBERUS_IAM_PORTAL_URL=https://app.cerberus-iam.com
CERBERUS_IAM_PROFILE_URL=
CERBERUS_IAM_SECURITY_URL=
CERBERUS_IAM_REDIRECT_AFTER_LOGIN=/dashboard
CERBERUS_IAM_USER_MODEL=
```

### HTTP Client Customisation

All outbound calls now run through Laravel's HTTP client, so you get first-class support for `Http::fake()`, middleware, and macros. Tune timeouts or retry behaviour via `cerberus-iam.http` in the config (or the matching environment variables shown above). If you need deeper customisation you can register macros/global middleware on the `Http` facade the same way you would in any Laravel app—those hooks automatically apply to the SDK because it resolves the shared HTTP factory from the container.

---

## Database Requirements

Cerberus IAM uses UUIDs for user identifiers. You must configure your Laravel application's database to support this.

### Sessions Table

Update your sessions table migration to use string for `user_id`:

```php
$table->string('user_id')->nullable()->index();
```

If you already have a sessions table with a bigint `user_id` column, create a migration to convert it:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
};
```

### User Model (Optional but Recommended)

By default, the SDK retrieves user data from Cerberus IAM on every request (stateless mode). For better performance and integration with Laravel's ecosystem, you can configure the SDK to sync users to a local database table.

To enable database-backed authentication, set the `CERBERUS_IAM_USER_MODEL` environment variable:

```dotenv
CERBERUS_IAM_USER_MODEL=App\\Models\\User
```

Your `users` table migration should include:

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('cerberus_id')->unique(); // UUID from Cerberus IAM
    $table->string('name');
    $table->string('email')->unique();

    // Optional fields
    $table->string('first_name')->nullable();
    $table->string('last_name')->nullable();
    $table->string('organisation_id')->nullable();
    $table->string('organisation_slug')->nullable();

    $table->timestamps();
});
```

Your User model must:

1. Implement `Illuminate\Contracts\Auth\Authenticatable` (typically via `Illuminate\Foundation\Auth\User`)
2. Have `cerberus_id`, `name`, and `email` in the `$fillable` array
3. Optionally include `first_name`, `last_name`, `organisation_id`, `organisation_slug`

Example User model:

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'cerberus_id',
        'name',
        'email',
        'first_name',
        'last_name',
        'organisation_id',
        'organisation_slug',
    ];
}
```

When database-backed authentication is enabled:

- Users are automatically created/updated in your local database on login
- Subsequent requests retrieve the user from your database (fast)
- User data is refreshed from Cerberus on each login

---

## Guard Setup

Replace the default `web` guard (or add a dedicated guard) in `config/auth.php`:

```php
'guards' => [
    'web' => [
        'driver' => 'cerberus',
        'provider' => 'cerberus-users',
        'scopes' => ['openid', 'profile', 'email'],
    ],
],

'providers' => [
    'cerberus-users' => [
        'driver' => 'cerberus',
    ],
],
```

The package registers:

- `cerberus` guard (stateful)
- `cerberus` user provider (fetches profiles from IAM as-needed)
- Middleware alias `cerberus.auth`

---

## Authentication Flow

1. **Incoming request** hits a protected route.
2. `EnsureCerberusAuthenticated` checks the guard; guests are redirected to Cerberus using PKCE.
3. After login/consent, the IAM redirects to `/cerberus/callback` (included route) with an auth code.
4. `CerberusGuard::loginFromAuthorizationCode()` exchanges the code for tokens, stores them via the `TokenStore`, and pulls the user profile from `/oauth2/userinfo`.
5. Subsequent requests reuse access tokens, automatically refresh them with the IAM when expired, and fall back to Cerberus' session cookie if present.

### Protecting Routes

```php
Route::middleware('cerberus.auth')->group(function () {
    Route::view('/dashboard', 'dashboard');
});
```

You can override the default redirect target by passing it as middleware parameter:

```php
Route::middleware('cerberus.auth:/account')->get('/settings', SettingsController::class);
```

---

## Callback Route

The package registers `/cerberus/callback` under the `web` middleware group.  If you already have a route with that URI, disable the auto-registration by caching routes and defining your own controller that proxies to `CerberusGuard::loginFromAuthorizationCode()`.

```php
use CerberusIAM\Auth\CerberusGuard;
use Illuminate\Support\Facades\Auth;

Route::get('/cerberus/callback', function (Request $request) {
    /** @var CerberusGuard $guard */
    $guard = Auth::guard('cerberus');
    $guard->loginFromAuthorizationCode(
        $request->query('code'),
        $request->query('state')
    );

    return redirect()->intended(config('cerberus-iam.redirect_after_login'));
});
```

---

## Token & State Storage

By default tokens live in the session using `CerberusIAM\Support\Stores\SessionTokenStore`.  You can swap it for Redis or any persistent store by binding the interfaces:

```php
use CerberusIAM\Contracts\TokenStore;
use CerberusIAM\Contracts\OAuthStateStore;
use App\Auth\RedisTokenStore;
use App\Auth\RedisOAuthStateStore;

$this->app->bind(TokenStore::class, fn () => new RedisTokenStore());
$this->app->bind(OAuthStateStore::class, fn () => new RedisOAuthStateStore());
```

Both interfaces are minimal:

```php
interface TokenStore {
    public function store(array $payload): void;
    public function retrieve(): ?array;
    public function clear(): void;
}
```

The guard asks the store for `access_token`, `refresh_token`, and optionally `expires_at` (ISO8601).  Refresh happens automatically when `expires_at` is in the past.

---

## HTTP Client & Facade

`CerberusIAM\Http\Clients\CerberusClient` delegates to Laravel's HTTP client, so anything you register via `Http::macro()` or `Http::middleware()` flows through automatically. Use the facade when you need low-level access:

```php
use CerberusIAM\Facades\CerberusIam;

$jwks = CerberusIam::url('/oauth2/jwks.json');
$response = CerberusIam::getUserInfo($accessToken);
```

The client respects the timeout/retry options defined in `cerberus-iam.php`.

---

## User Directory Proxy

Cerberus exposes administrative endpoints (e.g. `/v1/admin/users`).  To keep your Laravel controllers tidy, use the bundled repository + filter pipeline:

```php
use CerberusIAM\Repositories\UserDirectoryRepository;
use Illuminate\Http\Request;

class AdminUserController
{
    public function __invoke(Request $request, UserDirectoryRepository $repository)
    {
        $directory = $repository->list(
            organisationSlug: $request->header('X-Org-Domain'),
            request: $request,
            options: ['per_page' => 25],
            accessToken: auth()->guard('cerberus')->getTokenStore()->retrieve()['access_token'] ?? null,
            sessionToken: $request->cookie(config('cerberus-iam.session_cookie'))
        );

        return view('admin.users.index', ['users' => $directory['data'] ?? []]);
    }
}
```

`UserDirectoryFilter` leverages [`jerome/filterable`](https://github.com/Thavarshan/filterable) so your existing HTTP filters map to IAM query parameters (`filter[email]`, `filter[mfa_enabled]`, etc.).

---

## Customising Behaviour

| Concern        | Contract                                | Default                                | Notes                                 |
| -------------- | --------------------------------------- | -------------------------------------- | ------------------------------------- |
| IAM client     | `CerberusIAM\Contracts\IamClient`       | `Http\Clients\CerberusClient`          | Replace to mock or extend API calls   |
| Token store    | `CerberusIAM\Contracts\TokenStore`      | Session                                | Ideal place for Redis-backed storage  |
| OAuth state    | `CerberusIAM\Contracts\OAuthStateStore` | Session                                | Persist `state` + PKCE verifier       |
| User directory | `CerberusIAM\Contracts\UserRepository`  | `Repositories\UserDirectoryRepository` | Swap if you prefer GraphQL or caching |

Bind your implementation in any service provider to override defaults.

---

## Testing & Local Development

The package ships with a Pest suite backed by Orchestra Testbench.

```bash
composer test
```

To test against a live Cerberus instance locally:

1. Run the IAM API (`npm run dev` inside the main repo) and ensure an OAuth client exists.
2. Point `CERBERUS_IAM_URL` to that instance.
3. Configure the guard in a sample Laravel app, apply the middleware, and log in.

### Live Integration Tests Against <https://api.cerberus-iam.com>

Pest now includes an optional `integration` group that exercises the SDK against the deployed API (`https://api.cerberus-iam.com`). Because these tests create real sessions and call privileged admin endpoints, they are skipped unless the following environment variables are defined:

| Variable                      | Required        | Description                                                                  |
| ----------------------------- | --------------- | ---------------------------------------------------------------------------- |
| `CERBERUS_IAM_BASE_URL`       | Yes             | Base URL for the live API (`https://api.cerberus-iam.com`)                   |
| `CERBERUS_IAM_USERNAME`       | Yes             | Email for a Cerberus account that can log in via `/v1/auth/login`            |
| `CERBERUS_IAM_PASSWORD`       | Yes             | Password for the live test account                                           |
| `CERBERUS_IAM_CLIENT_ID`      | For admin flows | OAuth client ID with permission to call admin endpoints                      |
| `CERBERUS_IAM_CLIENT_SECRET`  | For admin flows | Client secret for the above OAuth client (required for client credentials)   |
| `CERBERUS_IAM_ORG_SLUG`       | Optional        | Organisation slug to scope admin requests (falls back to the login response) |
| `CERBERUS_IAM_SESSION_COOKIE` | Optional        | Session cookie name (defaults to `cerb_sid`)                                 |
| `CERBERUS_IAM_SCOPES`         | Optional        | Space-delimited scopes for the OAuth client (default `openid profile email`) |
| `CERBERUS_IAM_ADMIN_SCOPES`   | Optional        | Additional scopes for client-credential admin calls (default `users:read`)   |
| `CERBERUS_IAM_REDIRECT_URI`   | Optional        | Redirect URI associated with the OAuth client                                |
| `CERBERUS_IAM_TIMEOUT`        | Optional        | HTTP timeout (seconds) for the live calls, default `15`                      |

Once configured, run only the live tests with:

```bash
composer test -- --group=integration
```

The tests log out sessions automatically, but they still mutate real data—use service accounts and non-production organisations wherever possible.

---

## Troubleshooting

| Symptom                                                             | Likely Cause                       | Fix                                                                          |
| ------------------------------------------------------------------- | ---------------------------------- | ---------------------------------------------------------------------------- |
| Redirect loop back to Cerberus                                      | callback URL mismatch              | Confirm `CERBERUS_IAM_REDIRECT_URI` matches the client settings              |
| `invalid_state` exception                                           | Session not persisting             | Check session driver, domain, and ensure HTTPS in production                 |
| `Route [/cerberus/callback] not defined`                            | Routes cached without package boot | Clear route cache or define the route manually                               |
| 401 when listing users                                              | Missing organisation slug header   | Pass `X-Org-Domain` or adjust repository call                                |
| `Invalid text representation: invalid input syntax for type bigint` | Sessions table `user_id` is bigint | Change sessions table `user_id` column to string (see Database Requirements) |

---

## Contributing

1. `composer install`
2. Make changes (follow PSR-12, small focused commits)
3. `composer test`
4. Submit PR referencing the IAM change if relevant

Bug reports and feature requests are welcome via issues in the main Cerberus IAM repository.

---

## License

This project is licensed under the MIT License – see [LICENSE](LICENSE) for details.
