<?php

namespace Cerberus\Concerns;

use Fetch\Interfaces\ClientHandler;
use Illuminate\Contracts\Auth\Authenticatable as User;

trait HandlesImpersonation
{
    /**
     * The ID of the user the request to the API should be made on behalf of.
     */
    protected int|string|null $actor = null;

    /**
     * Impersonate a user for subsequent API requests.
     */
    public function actingAs(User|string|int $user): self
    {
        $userId = $user instanceof User ? $user->getAuthIdentifier() : $user;

        $this->actor = $userId;

        return $this;
    }

    /**
     * Stop impersonating a user.
     */
    protected function applyImpersonation(ClientHandler $http): self
    {
        if (! is_null($this->actor)) {
            $http->withHeader('X-Cerberus-User', $this->actor);
        }

        return $this;
    }
}
