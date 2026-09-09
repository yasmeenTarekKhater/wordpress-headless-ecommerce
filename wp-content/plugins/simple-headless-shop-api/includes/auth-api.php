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

});

