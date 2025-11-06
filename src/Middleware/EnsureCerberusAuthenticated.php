<?php

declare(strict_types=1);

namespace CerberusIAM\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure Cerberus Authenticated Middleware
 *
 * This middleware ensures that the user is authenticated via Cerberus IAM,
 * redirecting to the OAuth flow if not authenticated.
 */
class EnsureCerberusAuthenticated
{
    /**
     * Create a new middleware instance.
     *
     * @param  AuthFactory  $auth  The Laravel authentication factory.
     */
    public function __construct(
        protected AuthFactory $auth
    ) {}

    /**
     * Handle an incoming request.
     *
     * This method checks if the user is authenticated with the specified guard.
     * If not, it redirects to the Cerberus OAuth authorization URL.
     *
     * @param  Request  $request  The HTTP request.
     * @param  Closure  $next  The next middleware closure.
     * @param  string|null  $guard  The guard name to check authentication for.
     * @param  string|null  $returnTo  The URL to return to after authentication.
     * @return Response The response, either redirect or the next response.
     */
    public function handle(Request $request, Closure $next, ?string $guard = null, ?string $returnTo = null): Response
    {
        // Determine the guard to use, defaulting to 'cerberus'
        $guard = $guard ?? 'cerberus';
        $authGuard = $this->auth->guard($guard);

        // If the user is not authenticated, redirect to Cerberus
        if ($authGuard->guest()) {
            return $authGuard->redirectToCerberus($returnTo);
        }

        // Proceed to the next middleware/request
        $response = $next($request);

        // Ensure the response is a Response instance
        if ($response instanceof Response) {
            return $response;
        }

        // Convert other response types to Response
        return response()->make($response);
    }
}
