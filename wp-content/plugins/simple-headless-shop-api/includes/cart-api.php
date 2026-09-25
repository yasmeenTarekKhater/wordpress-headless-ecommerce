<?php

if (!defined('ABSPATH')) {
    exit;
}

function simple_shop_get_product_effective_price($product_id)
{
    $price = (float) get_field(
        'price',
        $product_id
    );


    $sale_price = get_field(
        'sale_price',
        $product_id
    );


    if (
        $sale_price !== null
        &&
        $sale_price !== ''
        &&
        (float) $sale_price > 0
        &&
        (float) $sale_price < $price
    ) {

        return (float) $sale_price;
    }


    return $price;
}

function simple_shop_build_cart_response($user_id)
{
    global $wpdb;


    $table_name =
        $wpdb->prefix . 'simple_shop_cart_items';


    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT product_id, quantity
             FROM {$table_name}
             WHERE user_id = %d
             ORDER BY id ASC",

            $user_id
        )
    );


    $items = [];

    $subtotal = 0;

    $total_quantity = 0;


    foreach ($rows as $row) {

        $product = get_post(
            (int) $row->product_id
        );


        /*
         * Ignore deleted/draft products
         */

        if (
            !$product
            ||
            $product->post_type !== 'product'
            ||
            $product->post_status !== 'publish'
        ) {
            continue;
        }


        $quantity =
            (int) $row->quantity;


        $unit_price =
            simple_shop_get_product_effective_price(
                $product->ID
            );


        $line_total =
            $unit_price * $quantity;


        $subtotal += $line_total;

        $total_quantity += $quantity;


        $items[] = [

            'product' =>
                simple_shop_format_product(
                    $product
                ),

            'quantity' =>
                $quantity,

            'unit_price' =>
                $unit_price,

            'line_total' =>
                $line_total,

        ];
    }


    return [

        'items' => $items,

        'summary' => [

            'items_count' =>
                count($items),

            'total_quantity' =>
                $total_quantity,

            'subtotal' =>
                $subtotal,

        ],

    ];
}

function simple_shop_get_cart(
    WP_REST_Request $request
) {
    $user_id =
        get_current_user_id();


    return rest_ensure_response([

        'data' =>
            simple_shop_build_cart_response(
                $user_id
            ),

    ]);
}

function simple_shop_add_cart_item(
    WP_REST_Request $request
) {
    global $wpdb;


    $user_id =
        get_current_user_id();


    $product_id =
        absint(
            $request->get_param(
                'product_id'
            )
        );


    $quantity =
        absint(
            $request->get_param(
                'quantity'
            ) ?: 1
        );


    /*
     * Validate Product
     */

    $product =
        get_post($product_id);


    if (
        !$product
        ||
        $product->post_type !== 'product'
        ||
        $product->post_status !== 'publish'
    ) {

        return new WP_Error(
            'product_not_found',
            'Product not found.',
            [
                'status' => 404,
            ]
        );
    }


    /*
     * Check Stock
     */

    $stock = (int) get_field(
        'stock',
        $product_id
    );


    if ($stock <= 0) {

        return new WP_Error(
            'out_of_stock',
            'Product is out of stock.',
            [
                'status' => 409,
            ]
        );
    }


    $table_name =
        $wpdb->prefix
        . 'simple_shop_cart_items';


    /*
     * Check if already in cart
     */

    $existing =
        $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, quantity
                 FROM {$table_name}
                 WHERE user_id = %d
                 AND product_id = %d
                 LIMIT 1",

                $user_id,
                $product_id
            )
        );


    if ($existing) {

        $new_quantity =
            (int) $existing->quantity
            + $quantity;

    } else {

        $new_quantity =
            $quantity;
    }


    /*
     * Stock validation
     */

    if ($new_quantity > $stock) {

        return new WP_Error(
            'insufficient_stock',
            'Requested quantity exceeds available stock.',
            [
                'status' => 409,

                'available_stock' =>
                    $stock,
            ]
        );
    }


    $now =
        gmdate('Y-m-d H:i:s');


    /*
     * Update existing
     */

    if ($existing) {

        $wpdb->update(

            $table_name,

            [
                'quantity' =>
                    $new_quantity,

                'updated_at' =>
                    $now,
            ],

            [
                'id' =>
                    $existing->id,
            ],

            [
                '%d',
                '%s',
            ],

            [
                '%d',
            ]
        );


        return rest_ensure_response([

            'success' => true,

            'message' =>
                'Cart updated successfully.',

            'data' =>
                simple_shop_build_cart_response(
                    $user_id
                ),

        ]);
    }


    /*
     * Create new cart item
     */

    $inserted =
        $wpdb->insert(

            $table_name,

            [
                'user_id' =>
                    $user_id,

                'product_id' =>
                    $product_id,

                'quantity' =>
                    $quantity,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ],

            [
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
            ]
        );


    if ($inserted === false) {

        return new WP_Error(
            'cart_update_failed',
            'Could not add product to cart.',
            [
                'status' => 500,
            ]
        );
    }


    return new WP_REST_Response(
        [

            'success' => true,

            'message' =>
                'Product added to cart.',

            'data' =>
                simple_shop_build_cart_response(
                    $user_id
                ),

        ],
        201
    );
}


add_action('rest_api_init', function () {

    /*
     * Get Cart
     * Clear Cart
     */

    register_rest_route(
        'shop/v1',
        '/cart',
        [

            [
                'methods' =>
                    WP_REST_Server::READABLE,

                'callback' =>
                    'simple_shop_get_cart',

                'permission_callback' =>
                    'simple_shop_require_auth',
            ],

            [
                'methods' =>
                    WP_REST_Server::DELETABLE,

                'callback' =>
                    'simple_shop_clear_cart',

                'permission_callback' =>
                    'simple_shop_require_auth',
            ],

        ]
    );


    /*
     * Add Item
     */

    register_rest_route(
        'shop/v1',
        '/cart/items',
        [

            'methods' =>
                WP_REST_Server::CREATABLE,

            'callback' =>
                'simple_shop_add_cart_item',

            'permission_callback' =>
                'simple_shop_require_auth',

            'args' => [

                'product_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],

                'quantity' => [

                    'default' => 1,

                    'sanitize_callback' => 'absint',

                    'validate_callback' =>
                        function ($value) {

                            return $value >= 1
                                && $value <= 99;
                        },
                ],

            ],

        ]
    );


    /*
     * Update / Remove Item
     */

    register_rest_route(
        'shop/v1',
        '/cart/items/(?P<product_id>\d+)',
        [

            [
                'methods' => 'PATCH',

                'callback' =>
                    'simple_shop_update_cart_item',

                'permission_callback' =>
                    'simple_shop_require_auth',

                'args' => [

                    'product_id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],

                    'quantity' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',

                        'validate_callback' =>
                            function ($value) {

                                return $value >= 1
                                    && $value <= 99;
                            },
                    ],

                ],
            ],

            [
                'methods' =>
                    WP_REST_Server::DELETABLE,

                'callback' =>
                    'simple_shop_remove_cart_item',

                'permission_callback' =>
                    'simple_shop_require_auth',
            ],

        ]
    );

});