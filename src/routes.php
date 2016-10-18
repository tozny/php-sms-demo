<?php
use \Slim\App;
use \Slim\Http\Response;
use \Slim\Http\Request;

/** @global App $app */

/**
 * The routes for the homepage power both presenting the homepage itself (GET) and
 * logging in to the application (POST).
 *
 * If user authentication is successful, an authentication challenge will be created
 * with Tozny and the user will be redirected to the /confirm page where they must
 * input the OTP they have received. The session for the challenge will be passed
 * with the redirect in a query parameter such that it's available for completion.
 *
 * If authentication fails, the user is redirected back to the homepage and asked to
 * correct any errors.
 */
$app->get('/', function (Request $request, Response $response, $args) {
    $error = $request->getQueryParam( 'error' );
    if ( ! empty( $this->errors[ $error ] ) ) {
        $args[ 'error' ] = $this->errors[ $error ];
    }

    $message = $request->getQueryParam( 'message' );
    if ( ! empty( $this->messages[ $message ] ) ) {
        $args[ 'message' ] = $this->messages[ $message ];
    }

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});
$app->post('/', function (Request $request, Response $response, $args) {
    /** @var \Flintstone\Flintstone $db */
    $db = $this->users;

    $username = $request->getParam('username');
    $password = $request->getParam('password');

    /**
     * First we attempt to fetch the user from our database. If they exist, great! If not, move on and
     * set a generic "invalid login" error.
     */
    $user = $db->get($username);
    if ( $user ) {
        /**
         * Once we have a user, we need to compare the provided password (during login) with the stored
         * hash in the database. If they match, great! If not, move on and set a generic "invalid login"
         * error.
         */
        $user_data = json_decode( $user, true );
        if ($this->passwordhasher->checkPassword( $password, $user_data['password'] ) ) {
            $sent = $this->tozny_realm->realmOTPChallenge(
                null,                                       // Optional presence token (unnecessary)
                'sms-otp-6',                                // OTP format (could also be "sms-otp-8")
                $user_data['phone'],                        // Destination
                json_encode( [ 'username' => $username ] ), // Serialized data to be bound to the OTP session
                'verify'                                    // Context
            );

            if ('ok' === $sent['return'] ) {
                return $response->withRedirect('/confirm?session=' . $sent['session_id']);
            }
        }
    }

    return $response->withRedirect('/?error=invalidlogin');
});

/**
 * The routes for the registration endpoint power both resenting the registration form
 * (GET) and creating a new account (POST).
 *
 * When registering, a user will be sent an OTP over SMS to verify ownership of their
 * phone number. Unless they enter that OTP on the /verify screen, their account will
 * be "unverified" and there is no guarantee they can log in.
 */
$app->get('/register', function(Request $request, Response $response, $args) {
    $error = $request->getQueryParam( 'error' );
    if ( ! empty( $this->errors[ $error ] ) ) {
        $args[ 'error' ] = $this->errors[ $error ];
    }

    // Render registration view
    return $this->renderer->render($response, 'register.phtml', $args);
});
$app->post('/register', function(Request $request, Response $response, $args) {
    $username = $request->getParam('username');
    $phone = $request->getParam('phone');
    $password = $request->getParam('password');
    $cpassword = $request->getParam('password_confirm');

    if ( $this->users->get( $username ) ) {
        return $response->withRedirect('/register?error=useduser');
    }
    if ( empty( $phone ) ) {
        return $response->withRedirect('/register?error=emptyotp');
    }
    if (! hash_equals($password, $cpassword) ) {
        return $response->withRedirect('/register?error=nomatch');
    }

    $user = [
        'username' => $username,
        'phone'    => $phone,
        'password' => $this->passwordhasher->HashPassword( $password ),
        'verified' => false,
    ];

    $this->users->set( $username, json_encode( $user ) );

    $sent = $this->tozny_realm->realmOTPChallenge(
        null,                                       // Optional presence token (unnecessary)
        'sms-otp-6',                                // OTP format. Could also be "sms-otp-8"
        $phone,                                     // Destination
        json_encode( [ 'username' => $username ] ), // Serialized data to be bound to the OTP session
        'verify'                                    // Context
    );

    $this->logger->debug(json_encode($sent));

    if ('ok' === $sent['return'] ) {
        return $response->withRedirect('/verify?session=' . $sent['session_id']);
    }

    return $response->withRedirect('/register?error=generic');
});

/**
 * The verification routines are used to verify ownership of a phone number and complete
 * registration for the user. GETing the endpoint will present an entry form to confirm an
 * OTP. POSTing to the endpoint will submit the OTP and a session ID to verify the account.
 */
$app->get('/verify', function(Request $request, Response $response, $args) {
    $session = $request->getQueryParam('session');
    $args['session_id'] = $session;

    $error = $request->getQueryParam( 'error' );
    if ( ! empty( $this->errors[ $error ] ) ) {
        $args[ 'error' ] = $this->errors[ $error ];
    }

    // Render the view
    return $this->renderer->render($response, 'verify.phtml', $args);
});
$app->post( '/verify', function ( Request $request, Response $response, $args ) {
    $session = $request->getParam( 'session' );
    $otp = $request->getParam( 'otp' );

    if ( empty( $session ) ) {
        return $response->withRedirect( '/verify?error=badsession' );
    }

    if ( empty( $otp ) ) {
        return $response->withRedirect( '/verify?error=emptyotp&session=' . $session );
    }

    $validated = $this->tozny_user->userOTPResult( $session, $otp );

    /**
     * Once the OTP is successfully validated, we have a signed_data blob from which we can
     * extract the original username (this was populated in the `data` key when the OTP was
     * originally created). This can be used to query the user DB to update the "verified"
     * status of our user.
     */
    if ( isset( $validated['signed_data'] ) ) {
        $data = $validated['signed_data'];

        $decoded = Tozny_Remote_Realm_API::base64UrlDecode($data);
        $deserialized = json_decode( $decoded, true );

        if ( isset( $deserialized['data'] ) ) {
            $realm_data = json_decode( $deserialized[ 'data' ], true );

            if ( isset( $realm_data['username'] ) ) {
                $username = $realm_data['username'];

                $user = $this->users->get( $username );

                if ( $user ) {
                    $user_data = json_decode( $user, true );
                    $user_data['verified'] = true;
                    $this->users->set( $username, json_encode( $user_data ) );

                    $_SESSION['username'] = $username;

                    return $response->withRedirect('/loggedin');
                }
            }
        }
    }

    return $response->withRedirect('/register?error=generic');
} );

/**
 * The routes for confirming an OTP are similar to /verify but will be verifying an OTP
 * issued with a context of authenticate (meaning the OTP will be used to actually log the
 * user in!
 */
$app->get('/confirm', function(Request $request, Response $response, $args) {
    $session = $request->getQueryParam('session');
    $args['session_id'] = $session;

    $error = $request->getQueryParam( 'error' );
    if ( ! empty( $this->errors[ $error ] ) ) {
        $args[ 'error' ] = $this->errors[ $error ];
    }

    return $this->renderer->render($response, 'confirm.phtml', $args);
});
$app->post('/confirm', function(Request $request, Response $response, $args) {
    $session = $request->getParam( 'session' );
    $otp = $request->getParam( 'otp' );

    if ( empty( $session ) ) {
        return $response->withRedirect( '/confirm?error=badsession' );
    }

    if ( empty( $otp ) ) {
        return $response->withRedirect( '/confirm?error=emptyotp&session=' . $session );
    }

    $validated = $this->tozny_user->userOTPResult( $session, $otp );

    /**
     * Once the OTP is successfully validated, we have a signed_data blob from which we can
     * extract the original username (this was populated in the `data` key when the OTP was
     * originally created). This can be used to query the user DB to update the "verified"
     * status of our user.
     */
    if ( isset( $validated['signed_data'] ) ) {
        $data = $validated['signed_data'];

        $decoded = Tozny_Remote_Realm_API::base64UrlDecode($data);
        $deserialized = json_decode( $decoded, true );

        if ( isset( $deserialized['data'] ) ) {
            $realm_data = json_decode( $deserialized[ 'data' ], true );

            if ( isset( $realm_data['username'] ) ) {
                $username = $realm_data['username'];

                $user = $this->users->get( $username );

                if ( $user ) {
                    $user_data = json_decode( $user, true );
                    $user_data['verified'] = true;
                    $this->users->set( $username, json_encode( $user_data ) );

                    $_SESSION['username'] = $username;

                    return $response->withRedirect('/loggedin');
                }
            }
        }
    }

    return $response->withRedirect('/login?error=generic');
});

/**
 * Once the user has been logged in, they will have access to the central, authenticated
 * page that presents their user information and the ability to update their password.
 */
$app->get('/loggedin', function(Request $request, Response $response, $args) {
    if ( ! isset( $_SESSION['username'] ) || ! $this->users->get( $_SESSION['username'] ) ) {
        return $response->withRedirect('/?error=notloggedin');
    }

    $error = $request->getQueryParam( 'error' );
    if ( ! empty( $this->errors[ $error ] ) ) {
        $args[ 'error' ] = $this->errors[ $error ];
    }

    $message = $request->getQueryParam( 'message' );
    if ( ! empty( $this->messages[ $message ] ) ) {
        $args[ 'message' ] = $this->messages[ $message ];
    }

    $user_data = $this->users->get( $_SESSION['username'] );
    $user = json_decode( $user_data, true );

    $args['username'] = $user['username'];
    $args['phone'] = $user['phone'];

    return $this->renderer->render($response, 'loggedin.phtml', $args);
});
$app->post('/loggedin', function(Request $request, Response $response, $args) {
    if ( ! isset( $_SESSION['username'] ) || ! $this->users->get( $_SESSION['username'] ) ) {
        return $response->withRedirect('/?error=notloggedin');
    }

    $old_password = $request->getParam('password');
    $new_password = $request->getParam('new_password');
    $new_cpassword = $request->getParam('new_cpassword');

    $user_data = $this->users->get( $_SESSION['username'] );
    $user = json_decode( $user_data, true );

    if (! hash_equals($new_password, $new_cpassword) ) {
        return $response->withRedirect('/loggedin?error=nomatch');
    }

    if (! $this->passwordhasher->checkPassword( $old_password, $user['password'] ) ) {
        return $response->withRedirect('/loggedin?error=badpassword');
    }

    $user['password'] = $this->passwordhasher->HashPassword( $new_password );
    $this->users->set( $_SESSION['username'], json_encode( $user ) );

    return $response->withRedirect('/loggedin?message=updated');
});

/**
 * Logging out is a matter of clearing the PHP session and redirecting to the homepage.
 */
$app->get('/logout', function(Request $request, Response $response, $args) {
    session_destroy();

    return $response->withRedirect('/?loggedout');
} );