<?php
if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

/**
 * Load in all autoloaded dependencies and also the Tozny API (which doesn't currently
 * leverage Composer autoloading but is still referenced by Composer).
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/tozny/sdk-php/ToznyRemoteRealmAPI.php';
require __DIR__ . '/../vendor/tozny/sdk-php/ToznyRemoteUserAPI.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// Run app
$app->run();
