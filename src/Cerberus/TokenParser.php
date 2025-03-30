<?php

namespace Cerberus;

use Cerberus\Resources\Token;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use UnexpectedValueException;

class TokenParser
{
    /**
     * Parse a raw JWT string and return a Token object.
     *
     *
     * @throws \UnexpectedValueException
     */
    public static function parse(string $token): Token
    {
        $parser = new Parser(new JoseEncoder);
        $parsed = $parser->parse($token);

        if (! $parsed instanceof Plain) {
            throw new UnexpectedValueException('Invalid JWT token.');
        }

        $claims = $parsed->claims();

        return Container::getInstance()->make(Token::class)->forceFill([
            'access_token' => $token,
            'client_id' => $claims->get('aud'),
            'user_id' => $claims->has('sub') ? (int) $claims->get('sub') : null,
            'scopes' => $claims->get('scopes', []),
            'expires_at' => $claims->has('exp')
                ? Carbon::createFromTimestamp($claims->get('exp')->getTimestamp())
                : null,
        ]);
    }
}
