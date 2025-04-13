<?php

namespace Cerberus\Resources;

use InvalidArgumentException;

class Client extends Resource
{
    /**
     * Name of the resource.
     */
    public string $resource = 'clients';

    /**
     * Revoke a client by its ID or the current instance.
     *
     * @throws InvalidArgumentException If no valid client can be found to revoke.
     */
    public function revoke(?string $id = null): bool
    {
        $client = $id ? static::find($id) : $this;

        if (! $client || ! $client->exists) {
            throw new InvalidArgumentException(
                $id
                    ? "Client with ID {$id} not found or does not exist."
                    : 'Client ID is required to revoke a client.'
            );
        }

        return $client->update(['revoked' => true]);
    }
}
