<?php

namespace Cerberus\Concerns;

use Illuminate\Contracts\Auth\Authenticatable as User;

trait HandlesImpersonation
{
    /**
     * Impersonate a user for subsequent API requests.
     */
    public function impersonate(User|string|int $user): self
    {
        $userId = $user instanceof User ? $user->getAuthIdentifier() : $user;

        // Set impersonation header
        $this->http->withHeader('X-Cerberus-Impersonate', $userId);

        return $this;
    }

    /**
     * Stop impersonating a user.
     */
    public function stopImpersonating(): self
    {
        // Remove impersonation header
        $this->http->withoutHeader('X-Cerberus-Impersonate');

        return $this;
    }

    /**
     * Determine if the client is currently impersonating a user.
     */
    public function isImpersonating(): bool
    {
        return $this->http->hasHeader('X-Cerberus-Impersonate');
    }
}
