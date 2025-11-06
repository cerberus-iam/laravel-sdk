<?php

declare(strict_types=1);

namespace CerberusIAM\Contracts;

/**
 * OAuth State Store Contract
 *
 * This interface defines the contract for storing and retrieving OAuth state
 * and code verifier parameters during the OAuth authorization flow.
 */
interface OAuthStateStore
{
    /**
     * Store the OAuth state and code verifier.
     *
     * This method stores the state parameter and optional code verifier
     * for use during the OAuth authorization flow.
     *
     * @param  string  $state  The OAuth state parameter for CSRF protection.
     * @param  string|null  $codeVerifier  The PKCE code verifier for security.
     */
    public function putState(string $state, ?string $codeVerifier = null): void;

    /**
     * Retrieve and remove the stored OAuth state and code verifier.
     *
     * This method retrieves the stored state and code verifier, then removes them
     * from storage to prevent reuse.
     *
     * @return array{state: string|null, code_verifier: string|null} The state and code verifier.
     */
    public function pullState(): array;
}
