<?php
// DIC configuration

$container = $app->getContainer();

// Set up environment
$dotenv = new \Dotenv\Dotenv(__DIR__ . '/..');
$dotenv->load();

// Flat-file database
$container['users'] = function ($c) {
    return new Flintstone\Flintstone('users', array('dir' => __DIR__ . '/../data'));
};

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// Tozny
$container['tozny_realm'] = function ($c) {
    return new Tozny_Remote_Realm_API(getenv('REALM_KEY_ID'), getenv('REALM_SECRET'), getenv('TOZNY_API'));
};
$container['tozny_user'] = function($c) {
    return new Tozny_Remote_User_API(getenv('REALM_KEY_ID'), getenv('TOZNY_API'));
};

// Password Hashing
$container['passwordhasher'] = function ($c) {
    return new \Hautelook\Phpass\PasswordHash(8,false);
};

// Errors
$container['errors'] = function ($c) {
    return [
        'generic'      => 'Something went wrong ... please contact support',
        'useduser'     => 'That username is already in use',
        'emptydest'    => 'Please enter a valid phone number',
        'emptyotp'     => 'Please enter the one-time password you received on your device',
        'badsession'   => 'There was an error completing your session. Please <a href="/">start over</a> and try again',
        'badpassword'  => 'Your current password is invalid',
        'nomatch'      => 'Please re-enter the same password to confirm',
        'invalidlogin' => 'Invalid login. Perhaps you need to <a href="/register">register</a> first.',
        'notloggedin'  => 'You don\'t seem to be logged in'
    ];
};

// Messages
$container['messages'] = function ($c) {
    return [
        'registered' => 'Your account is now registered!',
        'updated'    => 'Your password has been updated',
        'loggedout'  => 'You have been logged out'
    ];
};