<?php

if (!defined('ABSPATH')) {
    exit;
}

function simple_shop_generate_username($email)
{
    $email_parts = explode('@', $email);

    $base_username = sanitize_user(
        $email_parts[0],
        true
    );


    if (empty($base_username)) {
        $base_username = 'customer';
    }


    $username = $base_username;

    $counter = 1;


    while (username_exists($username)) {

        $username = $base_username . $counter;

        $counter++;
    }


    return $username;
}

function simple_shop_register_customer(WP_REST_Request $request)
{
    $name = $request->get_param('name');

    $email = $request->get_param('email');

    $password = $request->get_param('password');


    /*
     * Check duplicate email
     */

    if (email_exists($email)) {

        return new WP_Error(
            'email_already_exists',
            'An account with this email already exists.',
            [
                'status' => 409,
            ]
        );
    }


    /*
     * Generate WordPress username
     */

    $username = simple_shop_generate_username($email);


    /*
     * Create customer
     */

    $user_id = wp_insert_user([

        'user_login' => $username,

        'user_email' => $email,

        'user_pass' => $password,

        'display_name' => $name,

        'nickname' => $name,

        'role' => 'customer',

    ]);


    /*
     * Handle WordPress error
     */

    if (is_wp_error($user_id)) {

        return new WP_Error(
            'registration_failed',
            'Account could not be created.',
            [
                'status' => 500,
            ]
        );
    }


    /*
     * Get created user
     */

    $user = get_userdata($user_id);


    /*
     * Return 201 Created
     */

    return new WP_REST_Response(
        [
            'success' => true,

            'message' => 'Account created successfully.',

            'user' => [
                'id' => $user->ID,

                'name' => $user->display_name,

                'email' => $user->user_email,
            ],
        ],
        201
    );
}

function simple_shop_create_session($user_id)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'simple_shop_sessions';


    /*
     * Remove expired sessions
     */

    $wpdb->query(
        "DELETE FROM {$table_name}
         WHERE expires_at < UTC_TIMESTAMP()"
    );


    /*
     * Generate secure random token
     */

    try {

        $token = bin2hex(
            random_bytes(32)
        );

    } catch (Throwable $e) {

        return new WP_Error(
            'token_generation_failed',
            'Could not create authentication session.',
            [
                'status' => 500,
            ]
        );
    }


    /*
     * Never store the raw token
     */

    $token_hash = hash(
        'sha256',
        $token
    );


    /*
     * Session lifetime = 7 days
     */

    $expires_timestamp =
        time() + (7 * DAY_IN_SECONDS);


    $created_at = gmdate(
        'Y-m-d H:i:s'
    );


    $expires_at = gmdate(
        'Y-m-d H:i:s',
        $expires_timestamp
    );


    /*
     * Store session
     */

    $inserted = $wpdb->insert(

        $table_name,

        [
            'user_id' => $user_id,

            'token_hash' => $token_hash,

            'created_at' => $created_at,

            'expires_at' => $expires_at,
        ],

        [
            '%d',
            '%s',
            '%s',
            '%s',
        ]
    );


    if ($inserted === false) {

        return new WP_Error(
            'session_creation_failed',
            'Could not create authentication session.',
            [
                'status' => 500,
            ]
        );
    }


    return [
        'token' => $token,

        'expires_at' => $expires_at,
    ];
}

function simple_shop_login_customer(WP_REST_Request $request)
{
    $email = $request->get_param('email');

    $password = $request->get_param('password');


    /*
     * Authenticate through WordPress
     */

    $user = wp_authenticate(
        $email,
        $password
    );


    /*
     * Wrong credentials
     */

    if (is_wp_error($user)) {

        return new WP_Error(
            'invalid_credentials',
            'Invalid email or password.',
            [
                'status' => 401,
            ]
        );
    }


    /*
     * Only storefront customers
     */

    if (!in_array(
        'customer',
        (array) $user->roles,
        true
    )) {

        return new WP_Error(
            'invalid_credentials',
            'Invalid email or password.',
            [
                'status' => 401,
            ]
        );
    }


    /*
     * Create session
     */

    $session = simple_shop_create_session(
        $user->ID
    );


    if (is_wp_error($session)) {
        return $session;
    }


    /*
     * Return session
     */

    return rest_ensure_response([

        'success' => true,

        'message' => 'Login successful.',

        'user' => [

            'id' => $user->ID,

            'name' => $user->display_name,

            'email' => $user->user_email,

        ],

        'session' => [

            'token' => $session['token'],

            'expires_at' =>
                $session['expires_at'],

        ],

    ]);
}


function simple_shop_get_bearer_token(WP_REST_Request $request)
{
    $authorization = $request->get_header('authorization');


    if (!$authorization) {

        return new WP_Error(
            'missing_token',
            'Authentication token is required.',
            [
                'status' => 401,
            ]
        );
    }


    if (!preg_match(
        '/^Bearer\s+(.+)$/i',
        trim($authorization),
        $matches
    )) {

        return new WP_Error(
            'invalid_authorization_header',
            'Invalid authorization header.',
            [
                'status' => 401,
            ]
        );
    }


    $token = trim($matches[1]);


    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {

        return new WP_Error(
            'invalid_token',
            'Invalid authentication token.',
            [
                'status' => 401,
            ]
        );
    }


    return $token;
}

function simple_shop_get_authenticated_user(
    WP_REST_Request $request
) {
    global $wpdb;


    /*
     * Get Bearer token
     */

    $token = simple_shop_get_bearer_token($request);


    if (is_wp_error($token)) {
        return $token;
    }


    /*
     * Hash provided token
     */

    $token_hash = hash(
        'sha256',
        $token
    );


    /*
     * Find active session
     */

    $table_name =
        $wpdb->prefix . 'simple_shop_sessions';


    $now = gmdate('Y-m-d H:i:s');


    $session = $wpdb->get_row(

        $wpdb->prepare(

            "SELECT user_id
             FROM {$table_name}
             WHERE token_hash = %s
             AND expires_at > %s
             LIMIT 1",

            $token_hash,
            $now
        )

    );


    /*
     * Session doesn't exist / expired
     */

    if (!$session) {

        return new WP_Error(
            'invalid_or_expired_token',
            'Authentication session is invalid or expired.',
            [
                'status' => 401,
            ]
        );
    }


    /*
     * Load WordPress user
     */

    $user = get_userdata(
        (int) $session->user_id
    );


    if (!$user) {

        return new WP_Error(
            'user_not_found',
            'Authenticated user no longer exists.',
            [
                'status' => 401,
            ]
        );
    }


    /*
     * Storefront customers only
     */

    if (!in_array(
        'customer',
        (array) $user->roles,
        true
    )) {

        return new WP_Error(
            'unauthorized_user',
            'This account cannot access the storefront.',
            [
                'status' => 403,
            ]
        );
    }


    return $user;
}

function simple_shop_require_auth(
    WP_REST_Request $request
) {
    $user = simple_shop_get_authenticated_user(
        $request
    );


    if (is_wp_error($user)) {
        return $user;
    }


    /*
     * Tell WordPress who the current user is
     */

    wp_set_current_user(
        $user->ID
    );


    return true;
}

function simple_shop_get_current_customer(
    WP_REST_Request $request
) {
    $user = wp_get_current_user();


    return rest_ensure_response([

        'success' => true,

        'user' => [

            'id' => $user->ID,

            'name' => $user->display_name,

            'email' => $user->user_email,

        ],

    ]);
}


add_action('rest_api_init', function () {

    register_rest_route('shop/v1', '/auth/register', [

        'methods' => WP_REST_Server::CREATABLE,  //POST

        'callback' => 'simple_shop_register_customer',

        'permission_callback' => '__return_true',  // Anyone may access this endpoint.

        'args' => [

            'name' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],

            'email' => [
                'required' => true,

                'sanitize_callback' => 'sanitize_email',

                'validate_callback' => function ($value) {
                    return is_email($value);
                },
            ],

            'password' => [
                'required' => true,

                'validate_callback' => function ($value) {
                    return is_string($value)
                        && strlen($value) >= 8;
                },
            ],

        ],

    ]);

    register_rest_route('shop/v1', '/auth/login', [

        'methods' => WP_REST_Server::CREATABLE,

        'callback' => 'simple_shop_login_customer',

        'permission_callback' => '__return_true',

        'args' => [

            'email' => [
                'required' => true,

                'sanitize_callback' => 'sanitize_email',

                'validate_callback' => function ($value) {
                    return is_email($value);
                },
            ],

            'password' => [
                'required' => true,

                'validate_callback' => function ($value) {
                    return is_string($value)
                        && $value !== '';
                },
            ],

        ],

    ]);

    register_rest_route('shop/v1', '/auth/me', [

        'methods' => WP_REST_Server::READABLE,

        'callback' => 'simple_shop_get_current_customer',

        'permission_callback' => 'simple_shop_require_auth',

    ]);

});

