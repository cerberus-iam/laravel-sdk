# Cerberus IAM | Laravel Bridge

A framework-agnostic bridge that lets any Laravel application outsource authentication and user directory responsibilities to the [Cerberus IAM API](../README.md).  Instead of maintaining local passwords, session stores, and role logic, you attach this package, configure a guard, and delegate the heavy lifting to Cerberus.

---

## Requirements

| Component          | Version                           |
| ------------------ | --------------------------------- |
| PHP                | 8.2+                              |
| Laravel components | 10.x / 11.x                       |
| cerberus-iam/api   | Compatible REST instance          |
| jerome/fetch-php   | ^3.2 (HTTP client)                |
| jerome/filterable  | ^2.0 (optional request filtering) |

> The package is designed for multi-tenant environments. Ensure the API instance you point to has the desired organisations, roles, and OAuth clients configured.

---

## Installation

```bash
composer require cerberus-iam/laravel-sdk
php artisan vendor:publish --provider="CerberusIAM\\CerberusIamServiceProvider" --tag=config
```

Publishing yields `config/cerberus-iam.php` – the single source of truth for connecting to the IAM platform.

### Environment Variables

```dotenv
CERBERUS_IAM_URL=https://api.cerberus-iam.test
CERBERUS_IAM_CLIENT_ID=client_...
CERBERUS_IAM_CLIENT_SECRET=secret_...
CERBERUS_IAM_REDIRECT_URI=https://app.test/cerberus/callback
CERBERUS_IAM_SCOPES="openid profile email"
CERBERUS_IAM_SESSION_COOKIE=cerb_sid
CERBERUS_IAM_ORG_SLUG=cerberus-iam
CERBERUS_IAM_HTTP_TIMEOUT=10
CERBERUS_IAM_HTTP_RETRY=true
CERBERUS_IAM_HTTP_RETRY_ATTEMPTS=2
CERBERUS_IAM_REDIRECT_AFTER_LOGIN=/dashboard
```

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

`CerberusIAM\Http\Clients\CerberusClient` is a thin wrapper over [`jerome/fetch-php`](https://fetch-php.thavarshan.com).  Use the facade when you need low-level access:

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

---

## Troubleshooting

| Symptom                                  | Likely Cause                       | Fix                                                             |
| ---------------------------------------- | ---------------------------------- | --------------------------------------------------------------- |
| Redirect loop back to Cerberus           | callback URL mismatch              | Confirm `CERBERUS_IAM_REDIRECT_URI` matches the client settings |
| `invalid_state` exception                | Session not persisting             | Check session driver, domain, and ensure HTTPS in production    |
| `Route [/cerberus/callback] not defined` | Routes cached without package boot | Clear route cache or define the route manually                  |
| 401 when listing users                   | Missing organisation slug header   | Pass `X-Org-Domain` or adjust repository call                   |

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
