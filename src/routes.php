<?php
// Routes

$app->get('/', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/send', function($request, $response, $args) {
    return $this->renderer->render($response, 'sent.phtml', $args);
});

$app->post('/validate', function($request, $response, $args) {
    return $this->renderer->render($response, 'valid.phtml', $args);
});