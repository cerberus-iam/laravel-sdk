<?php

namespace CerberusIAM\Tests\Support;

class FetchStub
{
    /** @var array<int, array<string, mixed>> */
    public static array $requests = [];

    /** @var array<int, object> */
    public static array $queue = [];
}

namespace Fetch;

use CerberusIAM\Tests\Support\FetchStub;

if (! function_exists(__NAMESPACE__.'\\fetch')) {
    function fetch(string $url, array $options = [])
    {
        FetchStub::$requests[] = ['url' => $url, 'options' => $options];

        return array_shift(FetchStub::$queue);
    }
}
