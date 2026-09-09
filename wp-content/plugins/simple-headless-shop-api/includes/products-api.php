<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {

    // GET /wp-json/shop/v1/products
    register_rest_route('shop/v1', '/products', [
        'methods' => WP_REST_Server::READABLE,  // GET

        'callback' => 'simple_shop_get_products',

        'permission_callback' => '__return_true',  // Anyone may access this endpoint.
    ]);

     // Single Product
     //GET /shop/v1/products/{slug}
    register_rest_route('shop/v1', '/products/(?P<slug>[^/]+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'simple_shop_get_product',
        'permission_callback' => '__return_true',

        'args' => [
            'slug' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_title',
                // This means WordPress sanitizes the incoming slug before our callback receives it.
            ],
        ],
    ]);

});

function simple_shop_format_product($post)
{
    $terms = wp_get_post_terms(
        $post->ID,
        'product_category'
    );

    $categories = [];

    foreach ($terms as $term) {

        $categories[] = [
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        ];
    }


    $price = get_field('price', $post->ID);

    $sale_price = get_field('sale_price', $post->ID);

    $stock = get_field('stock', $post->ID);

    $sku = get_field('sku', $post->ID);


    $image = get_the_post_thumbnail_url(
        $post->ID,
        'large'
    );


    return [

        'id' => $post->ID,

        'name' => get_the_title($post->ID),

        'slug' => $post->post_name,

        'description' => apply_filters(
            'the_content',
            $post->post_content
        ),

        'short_description' => $post->post_excerpt,

        'price' => (float) $price,

        'sale_price' => $sale_price
            ? (float) $sale_price
            : null,

        'stock' => (int) $stock,

        'sku' => $sku,

        'image' => $image ?: null,

        'categories' => $categories,

    ];
}


function simple_shop_get_products(WP_REST_Request $request)
{
    $page = max(
        1,
        absint($request->get_param('page') ?: 1)
    );


    $per_page = absint(
        $request->get_param('per_page') ?: 12
    );

    $per_page = min(
        max($per_page, 1),
        100
    );


    $search = sanitize_text_field(
        $request->get_param('search') ?: ''
    );


    $category = sanitize_text_field(
        $request->get_param('category') ?: ''
    );


    $sort = sanitize_text_field(
        $request->get_param('sort') ?: 'newest'
    );


    /*
     * Category filter
     */

    $tax_query = [];

    if ($category) {

        $tax_query[] = [

            'taxonomy' => 'product_category',

            'field' => 'slug',

            'terms' => $category,

        ];
    }


    /*
     * Price filters
     */

    $meta_query = [];


    $min_price = $request->get_param('min_price');

    if ($min_price !== null && $min_price !== '') {

        $meta_query[] = [

            'key' => 'price',

            'value' => (float) $min_price,

            'compare' => '>=',

            'type' => 'NUMERIC',

        ];
    }


    $max_price = $request->get_param('max_price');

    if ($max_price !== null && $max_price !== '') {

        $meta_query[] = [

            'key' => 'price',

            'value' => (float) $max_price,

            'compare' => '<=',

            'type' => 'NUMERIC',

        ];
    }


    /*
     * Sorting
     */

    $orderby = 'date';

    $order = 'DESC';

    $meta_key = null;


    switch ($sort) {

        case 'oldest':

            $order = 'ASC';

            break;


        case 'name_asc':

            $orderby = 'title';

            $order = 'ASC';

            break;


        case 'name_desc':

            $orderby = 'title';

            $order = 'DESC';

            break;


        case 'price_asc':

            $orderby = 'meta_value_num';

            $order = 'ASC';

            $meta_key = 'price';

            break;


        case 'price_desc':

            $orderby = 'meta_value_num';

            $order = 'DESC';

            $meta_key = 'price';

            break;
    }


    /*
     * Query
     */

    $query_args = [

        'post_type' => 'product',

        'post_status' => 'publish',

        'posts_per_page' => $per_page,

        'paged' => $page,

        's' => $search,

        'orderby' => $orderby,

        'order' => $order,

        'tax_query' => $tax_query,

        'meta_query' => $meta_query,

    ];


    if ($meta_key) {

        $query_args['meta_key'] = $meta_key;

    }


    $query = new WP_Query($query_args);


    /*
     * Format response
     */

    $products = [];


    foreach ($query->posts as $post) {

        $products[] = simple_shop_format_product($post);

    }


    return rest_ensure_response([

        'data' => $products,

        'pagination' => [

            'page' => $page,

            'per_page' => $per_page,

            'total' => (int) $query->found_posts,

            'total_pages' => (int) $query->max_num_pages,

        ],

    ]);
}

function simple_shop_get_product(WP_REST_Request $request)
{
    $slug = $request->get_param('slug');

    $product = get_page_by_path($slug, OBJECT, ['product']);

    if (!$product || $product->post_status !== 'publish') {
        return new WP_Error(
            'product_not_found',
            'Product not found.',
            ['status' => 404]
        );
    }

    return rest_ensure_response([
        'data' => simple_shop_format_product($product),
    ]);
}
