<?php

declare(strict_types=1);

namespace CerberusIAM\Support\Stores;

use CerberusIAM\Contracts\OAuthStateStore;
use Illuminate\Contracts\Session\Session;

/**
 * Session-based OAuth State Store
 *
 * This class implements the OAuthStateStore interface using Laravel's session storage
 * to temporarily store OAuth state and code verifier during the authorization flow.
 */
class SessionOAuthStateStore implements OAuthStateStore
{
    /**
     * Create a new session OAuth state store instance.
     *
     * @param  Session  $session  The Laravel session instance.
     * @param  string  $stateKey  The session key for storing the state.
     * @param  string  $codeVerifierKey  The session key for storing the code verifier.
     */
    public function __construct(
        protected Session $session,
        protected string $stateKey = 'cerberus.oauth.state',
        protected string $codeVerifierKey = 'cerberus.oauth.code_verifier'
    ) {}

    /**
     * Store the OAuth state and code verifier in the session.
     *
     * This method stores the state parameter and optionally the code verifier
     * in the session for use during the OAuth flow.
     *
     * @param  string  $state  The OAuth state parameter.
     * @param  string|null  $codeVerifier  The PKCE code verifier.
     */
    public function putState(string $state, ?string $codeVerifier = null): void
    {
        // Store the state in the session
        $this->session->put($this->stateKey, $state);

        // Store the code verifier if provided
        if ($codeVerifier !== null) {
            $this->session->put($this->codeVerifierKey, $codeVerifier);
        }
    }

    /**
     * Retrieve and remove the stored OAuth state and code verifier.
     *
     * This method pulls the state and code verifier from the session,
     * removing them to prevent reuse.
     *
     * @return array{state: string|null, code_verifier: string|null} The state and code verifier.
     */
    public function pullState(): array
    {
        // Pull the state from the session (removes it)
        $state = $this->session->pull($this->stateKey);
        // Pull the code verifier from the session (removes it)
        $codeVerifier = $this->session->pull($this->codeVerifierKey);

        // Return the values, ensuring they are strings or null
        return [
            'state' => is_string($state) ? $state : null,
            'code_verifier' => is_string($codeVerifier) ? $codeVerifier : null,
        ];
    }
}
