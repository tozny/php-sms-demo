<?php
use \Slim\App;
use \Slim\Http\Response;
use \Slim\Http\Request;

/** @global App $app */

// Routes
$app->get('/', function (Request $request, Response $response, $args) {
    $error = $request->getQueryParam('error');
    if ( 'emptydest' === $error ) {
        $args['error'] = 'Please enter a valid phone number!';
    }

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/send', function(Request $request, Response $response, $args) use ($app) {
    $error = $request->getQueryParam('error');
    if ( 'emptyotp' === $error ) {
        $args['error'] = 'Please enter the OTP that you recieved on your device.';
    }
    if ( 'badsession' === $error ) {
        $args['error'] = 'There was an error completing your session. Please <a href="/">start over</a> and try again.';
    }

    $destination = $request->getParam('destination');
    if ( empty( $destination ) ) {
        return $response->withStatus(302)->withHeader('Location', '/?error=emptydest');
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
    if ( empty( $otp ) ) {
        return $response->withStatus(302)->withHeader('Location', '/send?error=emptyotp' );
    }
    $session = $request->getParam('session');
    if ( empty( $session ) ) {
        return $response->withStatus(302)->withHeader('Location', '/send?error=badsession' );
    }

    $validated = $this->tozny_user->userOTPResult( $session, $otp );

    $data = $validated['signed_data'];

    $args['signed_data'] = $data;
    $args['signature'] = $validated['signature'];

    $decoded = Tozny_Remote_Realm_API::base64UrlDecode($data);

    $args['data'] = json_decode( $decoded );

    return $this->renderer->render($response, 'valid.phtml', $args);
});