<?php

if (!defined('ABSPATH')) {
    exit;
}

function simple_shop_get_favorite_ids($user_id)
{
    $favorites = get_user_meta(
        $user_id,
        '_simple_shop_favorites',
        true
    );


    if (!is_array($favorites)) {
        return [];
    }


    return array_values(
        array_unique(
            array_map(
                'absint',
                $favorites
            )
        )
    );
}

function simple_shop_get_favorites(
    WP_REST_Request $request
) {
    $user_id = get_current_user_id();


    $favorite_ids =
        simple_shop_get_favorite_ids(
            $user_id
        );


    if (empty($favorite_ids)) {

        return rest_ensure_response([
            'data' => [],
            'count' => 0,
        ]);
    }


    $query = new WP_Query([

        'post_type' => 'product',

        'post_status' => 'publish',

        'post__in' => $favorite_ids,

        'posts_per_page' => -1,

        /*
         * Keep same order as favorite IDs
         */
        'orderby' => 'post__in',

    ]);


    $products = [];


    foreach ($query->posts as $post) {

        $products[] =
            simple_shop_format_product(
                $post
            );
    }


    return rest_ensure_response([

        'data' => $products,

        'count' => count($products),

    ]);
}

function simple_shop_add_favorite(
    WP_REST_Request $request
) {
    $user_id =
        get_current_user_id();


    $product_id =
        absint(
            $request->get_param(
                'product_id'
            )
        );


    /*
     * Validate Product
     */

    $product = get_post(
        $product_id
    );


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
     * Get existing favorites
     */

    $favorites =
        simple_shop_get_favorite_ids(
            $user_id
        );


    /*
     * Avoid duplicates
     */

    if (
        in_array(
            $product_id,
            $favorites,
            true
        )
    ) {

        return rest_ensure_response([

            'success' => true,

            'message' =>
                'Product is already in favorites.',

        ]);
    }


    /*
     * Add product
     */

    $favorites[] =
        $product_id;


    update_user_meta(
        $user_id,
        '_simple_shop_favorites',
        $favorites
    );


    return new WP_REST_Response(
        [
            'success' => true,

            'message' =>
                'Product added to favorites.',

            'product' =>
                simple_shop_format_product(
                    $product
                ),
        ],
        201
    );
}

function simple_shop_remove_favorite(
    WP_REST_Request $request
) {
    $user_id =
        get_current_user_id();


    $product_id =
        absint(
            $request->get_param(
                'product_id'
            )
        );


    $favorites =
        simple_shop_get_favorite_ids(
            $user_id
        );


    if (
        !in_array(
            $product_id,
            $favorites,
            true
        )
    ) {

        return new WP_Error(
            'favorite_not_found',
            'Product is not in favorites.',
            [
                'status' => 404,
            ]
        );
    }


    $favorites =
        array_values(
            array_filter(
                $favorites,

                function ($favorite_id)
                    use ($product_id) {

                    return $favorite_id
                        !== $product_id;
                }
            )
        );


    update_user_meta(
        $user_id,
        '_simple_shop_favorites',
        $favorites
    );


    return rest_ensure_response([

        'success' => true,

        'message' =>
            'Product removed from favorites.',

    ]);
}

add_action('rest_api_init', function () {

    /*
     * Get Favorites
     */
    register_rest_route(
        'shop/v1',
        '/favorites',
        [
            'methods' => WP_REST_Server::READABLE,

            'callback' =>
                'simple_shop_get_favorites',

            'permission_callback' =>
                'simple_shop_require_auth',
        ]
    );


    /*
     * Add Favorite
     */
    register_rest_route(
        'shop/v1',
        '/favorites/(?P<product_id>\d+)',
        [
            'methods' => WP_REST_Server::CREATABLE,

            'callback' =>
                'simple_shop_add_favorite',

            'permission_callback' =>
                'simple_shop_require_auth',

            'args' => [
                'product_id' => [
                    'required' => true,

                    'sanitize_callback' =>
                        'absint',
                ],
            ],
        ]
    );


    /*
     * Remove Favorite
     */
    register_rest_route(
        'shop/v1',
        '/favorites/(?P<product_id>\d+)',
        [
            'methods' => WP_REST_Server::DELETABLE,

            'callback' =>
                'simple_shop_remove_favorite',

            'permission_callback' =>
                'simple_shop_require_auth',

            'args' => [
                'product_id' => [
                    'required' => true,

                    'sanitize_callback' =>
                        'absint',
                ],
            ],
        ]
    );

});