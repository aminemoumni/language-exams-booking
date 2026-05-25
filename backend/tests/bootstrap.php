<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Force test environment even when the container has APP_ENV=dev set
// by Docker Compose (system env vars take precedence over phpunit.xml <server> tags).
putenv('APP_ENV=test');
$_ENV['APP_ENV']    = 'test';
$_SERVER['APP_ENV'] = 'test';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}
