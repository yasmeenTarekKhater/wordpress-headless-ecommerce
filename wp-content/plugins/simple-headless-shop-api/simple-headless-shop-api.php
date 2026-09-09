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

require_once SIMPLE_SHOP_API_PATH . 'includes/products-api.php';