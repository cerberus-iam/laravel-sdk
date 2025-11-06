<?php

// Load the fetch stub BEFORE the real fetch function gets loaded
require_once __DIR__.'/Support/FetchStub.php';

// Use the base TestCase for all tests in this directory
use CerberusIAM\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);
