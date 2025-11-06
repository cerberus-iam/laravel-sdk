<?php

declare(strict_types=1);

namespace CerberusIAM\Http\Controllers;

use CerberusIAM\Auth\CerberusGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Cerberus Callback Controller
 *
 * This controller handles the OAuth callback from the Cerberus IAM service,
 * processing the authorization code and completing the authentication flow.
 */
class CerberusCallbackController extends Controller
{
    /**
     * Handle the OAuth callback request.
     *
     * This method processes the callback from the Cerberus IAM OAuth flow,
     * validates the response parameters, and logs the user in using the authorization code.
     *
     * @param  Request  $request  The HTTP request containing OAuth parameters.
     * @return RedirectResponse A redirect response to the intended destination.
     *
     * @throws BadRequestHttpException If the OAuth response is invalid or contains an error.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        // Check if the OAuth response contains an error
        if ($request->filled('error')) {
            throw new BadRequestHttpException($request->input('error_description', 'Cerberus authentication failed.'));
        }

        // Extract the authorization code and state from the request
        $code = $request->query('code');
        $state = $request->query('state');

        // Validate that both code and state are present
        if (! $code || ! $state) {
            throw new BadRequestHttpException('Missing Cerberus OAuth response parameters.');
        }

        // Peek at the stored state to get the guard name without removing it
        // (The guard's loginFromAuthorizationCode will pull and validate the full state)
        $session = $request->session();
        $guardName = $session->get('cerberus.oauth.guard');

        // If no guard name is stored, try the configured default guard
        // This provides backward compatibility for existing OAuth flows
        if (! $guardName) {
            $guardName = config('cerberus-iam.default_guard');

            // If still no guard name, throw an error
            if (! $guardName) {
                throw new BadRequestHttpException(
                    'Unable to determine which guard initiated the OAuth flow. '.
                    'Please ensure cerberus-iam.default_guard is configured, or upgrade your OAuth flow.'
                );
            }
        }

        // Get the Cerberus guard instance
        /** @var CerberusGuard $guard */
        $guard = Auth::guard($guardName);

        // Attempt to log in the user using the authorization code and state
        $guard->loginFromAuthorizationCode($code, $state);

        // Redirect to the intended URL or the configured default
        return redirect()->intended(config('cerberus-iam.redirect_after_login', '/'));
    }
}
