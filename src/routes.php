<?php
use \Slim\Http\Response;
use \Slim\Http\Request;

// Routes

$app->get('/', function (Request $request, Response $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/send', function(Request $request, Response $response, $args) use ($app) {
    $destination = $request->getParam('destination');
    if ( empty( $destination ) ) {
        return $response->withRedirect('/');
    }
    $sent = $this->tozny_realm->realmOTPChallenge( null, 'sms-otp-6', $destination, null, 'verify' );

    if ( 'ok' !== $sent['return'] ) {
        return $response->withRedirect('/');
    }

    $args['session'] = $sent['session_id'];

    return $this->renderer->render($response, 'sent.phtml', $args);
});

$app->post('/validate', function(Request $request, Response $response, $args) {
    $otp = $request->getParam('otp');
    $session = $request->getParam('session');
    if ( empty( $otp ) || empty( $session ) ) {
        return $response->withRedirect('/send');
    }

    $validated = $this->tozny_user->userOTPResult( $session, $otp );

    $args['validated'] = $validated;

    return $this->renderer->render($response, 'valid.phtml', $args);
});