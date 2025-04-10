<?php

namespace Cerberus;

use Cerberus\Resources\RefreshToken;
use Cerberus\Resources\Token;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use UnexpectedValueException;

class TokenParser
{
    /**
     * Parse an access token and return a Token resource.
     */
    public static function parseAccessToken(string $jwt): Token
    {
        $claims = self::parseJWT($jwt)->claims();

        return new Token([
            'access_token' => $jwt,
            'client_id' => self::convertSingleRecordAudToString($claims->get('aud')),
            'user_id' => $claims->has('sub') ? (int) $claims->get('sub') : null,
            'scopes' => $claims->get('scopes', []),
            'expires_in' => $claims->get('exp'),
            'token_id' => $claims->has('jti') ? $claims->get('jti') : null,
        ]);
    }

    /**
     * Wrap a refresh token string into a RefreshToken resource.
     */
    public static function parseRefreshToken(
        string $token,
        ?string $accessTokenId = null
    ): RefreshToken {
        return new RefreshToken([
            'refresh_token' => $token,
            'access_token_id' => $accessTokenId,
            'token_id' => hash('sha256', $token),
        ]);
    }

    /**
     * Internal method to parse a JWT into a Plain object.
     *
     * @throws UnexpectedValueException
     */
    protected static function parseJWT(string $jwt): Plain
    {
        $parser = new Parser(new JoseEncoder);
        $parsed = $parser->parse($jwt);

        if (! $parsed instanceof Plain) {
            throw new UnexpectedValueException('Invalid JWT token.');
        }

        return $parsed;
    }

    /**
     * Convert single record arrays into strings to ensure backwards compatibility between v4 and v3.x of lcobucci/jwt
     */
    protected static function convertSingleRecordAudToString(mixed $aud): array|string
    {
        if (is_null($aud)) {
            return '';
        }

        return \is_array($aud) && \count($aud) === 1 ? $aud[0] : $aud;
    }
}
