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