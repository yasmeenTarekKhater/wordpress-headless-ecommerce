<?php

if (!defined('ABSPATH')) {
    exit;
}

function simple_shop_forgot_password(
    WP_REST_Request $request
) {
    global $wpdb;

    $email = $request->get_param('email');


    /*
     * Always use generic response.
     */

    $response = [
        'success' => true,

        'message' =>
            'If an account exists for this email, a verification code has been sent.',
    ];


    /*
     * Find user
     */

    $user = get_user_by(
        'email',
        $email
    );


    if (
        !$user
        ||
        !in_array(
            'customer',
            (array) $user->roles,
            true
        )
    ) {
        return rest_ensure_response(
            $response
        );
    }


    $table_name =
        $wpdb->prefix
        . 'simple_shop_password_resets';


    /*
     * Basic 60-second cooldown
     */

    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT created_at
             FROM {$table_name}
             WHERE user_id = %d
             LIMIT 1",

            $user->ID
        )
    );


    if ($existing) {

        $created_timestamp =
            strtotime($existing->created_at);

        if (
            $created_timestamp
            &&
            $created_timestamp
                > time() - 60
        ) {
            return rest_ensure_response(
                $response
            );
        }
    }


    /*
     * Generate 6-digit OTP
     */

    $otp = (string) wp_rand(
        100000,
        999999
    );


    // Add this line temporarily for local testing:
    error_log("TESTING OTP CODE: " . $otp);


    /*
     * Hash OTP
     */

    $otp_hash = hash_hmac(
        'sha256',
        $otp,
        wp_salt('auth')
    );


    $now = gmdate(
        'Y-m-d H:i:s'
    );


    $expires_at = gmdate(
        'Y-m-d H:i:s',
        time() + (10 * MINUTE_IN_SECONDS)
    );


    /*
     * Only one active reset per user
     */

    $wpdb->delete(
        $table_name,
        [
            'user_id' => $user->ID,
        ],
        [
            '%d',
        ]
    );


    $wpdb->insert(
        $table_name,
        [
            'user_id' =>
                $user->ID,

            'otp_hash' =>
                $otp_hash,

            'otp_expires_at' =>
                $expires_at,

            'attempts' =>
                0,

            'created_at' =>
                $now,
        ],
        [
            '%d',
            '%s',
            '%s',
            '%d',
            '%s',
        ]
    );


    /*
     * Send email
     */

    $subject =
        'Password Reset Code';

    $message =
        "Your password reset code is: {$otp}\n\n"
        . "This code expires in 10 minutes.";


    $sent = wp_mail(
        $user->user_email,
        $subject,
        $message
    );


    if (!$sent) {

        /*
         * Don't expose whether account exists.
         * Log it for development/debugging.
         */

        error_log(
            'Simple Shop: Failed to send password reset email for user '
            . $user->ID
        );
    }


    return rest_ensure_response(
        $response
    );
}

function simple_shop_verify_reset_otp(
    WP_REST_Request $request
) {
    global $wpdb;


    $email =
        $request->get_param('email');

    $otp =
        $request->get_param('otp');


    $user = get_user_by(
        'email',
        $email
    );


    if (!$user) {

        return new WP_Error(
            'invalid_otp',
            'The verification code is invalid or expired.',
            [
                'status' => 400,
            ]
        );
    }


    $table_name =
        $wpdb->prefix
        . 'simple_shop_password_resets';


    $now = gmdate(
        'Y-m-d H:i:s'
    );


    $reset = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM {$table_name}
             WHERE user_id = %d
             AND otp_expires_at > %s
             AND attempts < 5
             AND verified_at IS NULL
             LIMIT 1",

            $user->ID,
            $now
        )
    );


    if (!$reset) {

        return new WP_Error(
            'invalid_otp',
            'The verification code is invalid or expired.',
            [
                'status' => 400,
            ]
        );
    }


    /*
     * Compare OTP
     */

    $provided_hash = hash_hmac(
        'sha256',
        $otp,
        wp_salt('auth')
    );


    if (
        !hash_equals(
            $reset->otp_hash,
            $provided_hash
        )
    ) {

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name}
                 SET attempts = attempts + 1
                 WHERE id = %d",

                $reset->id
            )
        );


        return new WP_Error(
            'invalid_otp',
            'The verification code is invalid or expired.',
            [
                'status' => 400,
            ]
        );
    }


    /*
     * OTP correct.
     * Generate secure reset token.
     */

    try {

        $reset_token =
            bin2hex(
                random_bytes(32)
            );

    } catch (Throwable $e) {

        return new WP_Error(
            'reset_token_failed',
            'Could not create password reset session.',
            [
                'status' => 500,
            ]
        );
    }


    $reset_token_hash =
        hash(
            'sha256',
            $reset_token
        );


    $reset_expires_at =
        gmdate(
            'Y-m-d H:i:s',
            time()
                + (15 * MINUTE_IN_SECONDS)
        );


    $wpdb->update(
        $table_name,

        [
            'reset_token_hash' =>
                $reset_token_hash,

            'reset_expires_at' =>
                $reset_expires_at,

            'verified_at' =>
                $now,
        ],

        [
            'id' =>
                $reset->id,
        ],

        [
            '%s',
            '%s',
            '%s',
        ],

        [
            '%d',
        ]
    );


    return rest_ensure_response([

        'success' => true,

        'message' =>
            'Verification successful.',

        'reset_token' =>
            $reset_token,

        'expires_at' =>
            $reset_expires_at,

    ]);
}

function simple_shop_reset_password(
    WP_REST_Request $request
) {
    global $wpdb;


    $reset_token =
        $request->get_param(
            'reset_token'
        );


    $new_password =
        $request->get_param(
            'password'
        );


    /*
     * Validate token format
     */

    if (
        !is_string($reset_token)
        ||
        !preg_match(
            '/^[a-f0-9]{64}$/i',
            $reset_token
        )
    ) {

        return new WP_Error(
            'invalid_reset_token',
            'Password reset session is invalid or expired.',
            [
                'status' => 401,
            ]
        );
    }


    $token_hash = hash(
        'sha256',
        $reset_token
    );


    $table_name =
        $wpdb->prefix
        . 'simple_shop_password_resets';


    $now =
        gmdate('Y-m-d H:i:s');


    $reset = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM {$table_name}
             WHERE reset_token_hash = %s
             AND reset_expires_at > %s
             AND verified_at IS NOT NULL
             LIMIT 1",

            $token_hash,
            $now
        )
    );


    if (!$reset) {

        return new WP_Error(
            'invalid_reset_token',
            'Password reset session is invalid or expired.',
            [
                'status' => 401,
            ]
        );
    }


    /*
     * Load customer
     */

    $user = get_userdata(
        (int) $reset->user_id
    );


    if (!$user) {

        return new WP_Error(
            'user_not_found',
            'User account no longer exists.',
            [
                'status' => 404,
            ]
        );
    }


    /*
     * Reset WordPress password
     */

    reset_password(
        $user,
        $new_password
    );


    /*
     * Remove ALL storefront sessions.
     */

    $sessions_table =
        $wpdb->prefix
        . 'simple_shop_sessions';


    $wpdb->delete(
        $sessions_table,
        [
            'user_id' =>
                $user->ID,
        ],
        [
            '%d',
        ]
    );


    /*
     * Reset token is one-time-use.
     */

    $wpdb->delete(
        $table_name,
        [
            'id' =>
                $reset->id,
        ],
        [
            '%d',
        ]
    );


    return rest_ensure_response([

        'success' => true,

        'message' =>
            'Password reset successfully. Please login with your new password.',

    ]);
}

add_action('rest_api_init', function () {

    /*
     * Forgot Password
     */

    register_rest_route(
        'shop/v1',
        '/auth/forgot-password',
        [
            'methods' =>
                WP_REST_Server::CREATABLE,

            'callback' =>
                'simple_shop_forgot_password',

            'permission_callback' =>
                '__return_true',

            'args' => [

                'email' => [
                    'required' => true,

                    'sanitize_callback' =>
                        'sanitize_email',

                    'validate_callback' =>
                        function ($value) {
                            return is_email($value);
                        },
                ],
            ],
        ]
    );


    /*
     * Verify OTP
     */

    register_rest_route(
        'shop/v1',
        '/auth/verify-otp',
        [
            'methods' =>
                WP_REST_Server::CREATABLE,

            'callback' =>
                'simple_shop_verify_reset_otp',

            'permission_callback' =>
                '__return_true',

            'args' => [

                'email' => [
                    'required' => true,

                    'sanitize_callback' =>
                        'sanitize_email',

                    'validate_callback' =>
                        function ($value) {
                            return is_email($value);
                        },
                ],

                'otp' => [
                    'required' => true,

                    'sanitize_callback' =>
                        'sanitize_text_field',

                    'validate_callback' =>
                        function ($value) {
                            return preg_match(
                                '/^\d{6}$/',
                                $value
                            );
                        },
                ],
            ],
        ]
    );


    /*
     * Reset Password
     */

    register_rest_route(
        'shop/v1',
        '/auth/reset-password',
        [
            'methods' =>
                WP_REST_Server::CREATABLE,

            'callback' =>
                'simple_shop_reset_password',

            'permission_callback' =>
                '__return_true',

            'args' => [

                'reset_token' => [
                    'required' => true,
                ],

                'password' => [
                    'required' => true,

                    'validate_callback' =>
                        function ($value) {

                            return is_string($value)
                                && strlen($value) >= 8;
                        },
                ],
            ],
        ]
    );

});