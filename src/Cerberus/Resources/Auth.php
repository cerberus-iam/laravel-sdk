<?php

namespace Cerberus\Resources;

class Auth extends Resource
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [
        'id',
        'email',
        'password',
    ];

    /**
     * The currently authenticated user instance.
     */
    protected ?User $user = null;

    /**
     * Set the currently authenticated user instance.
     */
    public function user(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Authenticate using credentials and return the token response.
     */
    public function authenticateViaCredentials(array $credentials): array
    {
        return $this->getConnection()
            ->post('/login', $credentials)
            ->json();
    }

    /**
     * Attempt to find the user for the current token.
     */
    public function findByToken(): ?User
    {
        $response = $this->getConnection()->get('/user');

        if (! $response->ok()) {
            return null;
        }

        $user = new User($response->json());

        return $this->user($user)->user;
    }

    /**
     * Check a plaintext password against the stored hash remotely.
     */
    public function checkPassword(array $credentials): bool
    {
        return $this->getConnection()
            ->post('/check-password', [
                'email' => $credentials['email'],
                $this->user?->getAuthPasswordName() ?? 'password' => $credentials['password'],
            ])
            ->ok();
    }

    /**
     * Rehash the user's password if required and supported.
     */
    public function rehashPasswordIfRequired(array $credentials, bool $force = false): void
    {
        $this->getConnection()
            ->withQueryParameters(['email' => $credentials['email']])
            ->post('/rehash-password', [
                'password' => $credentials['password'],
                'force' => $force,
            ]);
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(string $email, string $password): void
    {
        $this->getConnection()
            ->post('/reset-password', [
                'email' => $email,
                'password' => $password,
            ]);
    }
}
