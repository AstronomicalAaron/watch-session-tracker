<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}

if (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null) === 'test') {
    if (!is_dir(dirname(__DIR__) . '/var')) {
        mkdir(dirname(__DIR__) . '/var', 0777, true);
    }

    $testDb = dirname(__DIR__) . '/var/test.db';
    if (file_exists($testDb)) {
        unlink($testDb);
    }

    passthru('php bin/console app:db:init --env=test', $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "Failed to initialize test database.\n");
        exit($exitCode);
    }
}
