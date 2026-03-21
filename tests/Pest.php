<?php

use Orchestra\Testbench\TestCase;

// Use Orchestra Testbench as the base test case for package tests
// Ensure an APP_KEY is available during tests to avoid encryption errors
if (empty(getenv('APP_KEY')) && empty($_ENV['APP_KEY']) && empty($_SERVER['APP_KEY'])) {
	// set a runtime key for tests via environment only (avoid container binding)
	$key = 'base64:'.base64_encode(random_bytes(32));
	putenv('APP_KEY='.$key);
	$_ENV['APP_KEY'] = $key;
	$_SERVER['APP_KEY'] = $key;
}

uses(TestCase::class)->in(__DIR__);
