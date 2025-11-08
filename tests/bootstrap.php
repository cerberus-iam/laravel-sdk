<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);

if (! class_exists(Dotenv::class)) {
    return;
}

if (file_exists($projectRoot.'/.env.testing')) {
    Dotenv::createImmutable($projectRoot, '.env.testing')->safeLoad();
}

if (file_exists($projectRoot.'/.env')) {
    Dotenv::createImmutable($projectRoot)->safeLoad();
}
