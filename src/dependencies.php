<?php
// DIC configuration

$container = $app->getContainer();

// Set up environment
$dotenv = new \Dotenv\Dotenv(__DIR__ . '/../data');
$dotenv->load();

// Flat-file database
$container['users'] = function ($c) {
    return new Flintstone\Flintstone('users', array('dir' => __DIR__ . '/../'));
}

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