<?php
use \Slim\App;
use \Slim\Http\Response;
use \Slim\Http\Request;

/** @global App $app */

// Routes
$app->get('/', function (Request $request, Response $response, $args) {
    $error = $request->getQueryParam( 'error' );
    if ( ! empty( $this->errors[ $error ] ) ) {
        $args[ 'error' ] = $this->errors[ $error ];
    }

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/register', function(Request $request, Response $response, $args) {
    $error = $request->getQueryParam( 'error' );
    if ( ! empty( $this->errors[ $error ] ) ) {
        $args[ 'error' ] = $this->errors[ $error ];
    }

    // Render registration view
    return $this->renderer->render($response, 'register.phtml', $args);
});

$app->post('/confirm', function(Request $request, Response $response, $args) use ($app) {
    $error = $request->getQueryParam( 'error' );
    if ( ! empty( $this->errors[ $error ] ) ) {
        $args[ 'error' ] = $this->errors[ $error ];
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

    return $this->renderer->render($response, 'confirm.phtml', $args);
});

$app->post('/loggedin', function(Request $request, Response $response, $args) {
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

    return $this->renderer->render($response, 'loggedin.phtml', $args);
});