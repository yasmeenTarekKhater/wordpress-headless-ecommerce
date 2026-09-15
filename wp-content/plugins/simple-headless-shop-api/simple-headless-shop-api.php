<?php

/**
 * Plugin Name: Simple Headless Shop API
 * Description: Custom REST API for the Nuxt headless ecommerce application.
 * Version: 1.0.0
 * Author: Yasmeen tarek
 */

if (!defined('ABSPATH')) {
    exit;
}

define(
    'SIMPLE_SHOP_API_PATH',
    plugin_dir_path(__FILE__)
);


/*
|--------------------------------------------------------------------------
| Activation
|--------------------------------------------------------------------------
*/

function simple_shop_activate()
{

    /*
     * Customer role
     */
    add_role(
        'customer',
        'Customer',
        [
            'read' => true,
        ]
    );

    /*
     * Sessions table
     */
    simple_shop_create_sessions_table();


    /*
     * Password resets table
     */

    simple_shop_create_password_resets_table();

}

function simple_shop_create_sessions_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'simple_shop_sessions';

    $charset_collate = $wpdb->get_charset_collate();


    $sql = "CREATE TABLE {$table_name} (

        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        user_id BIGINT(20) UNSIGNED NOT NULL,

        token_hash CHAR(64) NOT NULL,

        expires_at DATETIME NOT NULL,

        created_at DATETIME NOT NULL,

        PRIMARY KEY  (id),

        UNIQUE KEY token_hash (token_hash),

        KEY user_id (user_id),

        KEY expires_at (expires_at)

    ) {$charset_collate};";


    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta($sql);
}

function simple_shop_create_password_resets_table()
{
    global $wpdb;

    $table_name =
        $wpdb->prefix . 'simple_shop_password_resets';

    $charset_collate =
        $wpdb->get_charset_collate();


    $sql = "CREATE TABLE {$table_name} (

        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        user_id BIGINT(20) UNSIGNED NOT NULL,

        otp_hash CHAR(64) NOT NULL,

        otp_expires_at DATETIME NOT NULL,

        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,

        reset_token_hash CHAR(64) DEFAULT NULL,

        reset_expires_at DATETIME DEFAULT NULL,

        verified_at DATETIME DEFAULT NULL,

        created_at DATETIME NOT NULL,

        PRIMARY KEY  (id),

        UNIQUE KEY user_id (user_id),

        UNIQUE KEY reset_token_hash (reset_token_hash),

        KEY otp_expires_at (otp_expires_at)

    ) {$charset_collate};";


    require_once ABSPATH
        . 'wp-admin/includes/upgrade.php';

    dbDelta($sql);
}

/*
|--------------------------------------------------------------------------
| Activation hook
|--------------------------------------------------------------------------
*/

register_activation_hook(
    __FILE__,
    'simple_shop_activate'
);

/*
|--------------------------------------------------------------------------
| Includes
|--------------------------------------------------------------------------
*/

require_once SIMPLE_SHOP_API_PATH . 'includes/products-api.php';
require_once SIMPLE_SHOP_API_PATH . 'includes/auth-api.php';
require_once SIMPLE_SHOP_API_PATH . 'includes/password-reset-api.php';