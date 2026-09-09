<?php
define('WP_USE_THEMES', false);
require_once('wp-load.php');

// Initialize the REST server
global $wp_rest_server;
if ( empty( $wp_rest_server ) ) {
    $wp_rest_server = new WP_REST_Server();
    do_action( 'rest_api_init', $wp_rest_server );
}

$routes = $wp_rest_server->get_routes();
$site_url = get_site_url();

$collection = [
    'info' => [
        'name' => get_bloginfo('name') . ' REST API',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
    ],
    'item' => []
];

$collection['variable'] = [
    [
        'key' => 'base_url',
        'value' => $site_url,
        'type' => 'string'
    ]
];

foreach ( $routes as $route_path => $route_handlers ) {
    if ( $route_path === '/' ) continue;

    $route_parts = array_values(array_filter(explode('/', $route_path)));
    $namespace = isset($route_parts[0]) ? $route_parts[0] : 'core';

    $folder_index = null;
    foreach ($collection['item'] as $index => $item) {
        if ($item['name'] === $namespace) {
            $folder_index = $index;
            break;
        }
    }

    if ($folder_index === null) {
        $collection['item'][] = [
            'name' => $namespace,
            'item' => []
        ];
        $folder_index = count($collection['item']) - 1;
    }

    foreach ( $route_handlers as $handler ) {
        $methods = array_keys($handler['methods']);
        foreach ($methods as $method) {
            // Clean up regex in paths for Postman
            $clean_path = preg_replace('/\(\?P<([^>]+)>[^)]+\)/', ':$1', $route_path);
            $clean_path = preg_replace('/\(.*?\)/', '', $clean_path);
            $clean_path = str_replace('//', '/', $clean_path);
            
            $args = isset($handler['args']) ? $handler['args'] : [];
            $queryParams = [];
            $bodyData = [];

            foreach ($args as $arg_name => $arg_details) {
                if (strpos($clean_path, ':' . $arg_name) !== false) continue;

                $type = isset($arg_details['type']) ? $arg_details['type'] : 'string';
                $default = isset($arg_details['default']) ? $arg_details['default'] : '';
                $description = isset($arg_details['description']) ? $arg_details['description'] : '';
                $required = isset($arg_details['required']) ? $arg_details['required'] : false;

                $val = $default;
                if ($val === '') {
                    if ($type === 'integer' || $type === 'number') {
                        $val = 1;
                    } elseif ($type === 'boolean') {
                        $val = true;
                    } elseif ($type === 'array') {
                        $val = ['example'];
                    } elseif ($type === 'object') {
                        $val = ['key' => 'value'];
                    } else {
                        $val = "example_" . $arg_name;
                    }
                }

                if (in_array($method, ['GET', 'DELETE'])) {
                    $queryParams[] = [
                        'key' => $arg_name,
                        'value' => (string) (is_array($val) || is_object($val) ? json_encode($val) : $val),
                        'description' => $description . ($required ? ' (Required)' : '')
                    ];
                } else {
                    $bodyData[$arg_name] = $val;
                }
            }

            $headers = [
                ['key' => 'Accept', 'value' => 'application/json'],
                ['key' => 'Authorization', 'value' => 'Bearer {{auth_token}}', 'type' => 'text', 'disabled' => true]
            ];

            if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($bodyData)) {
                $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
            }

            $url_array = [
                'raw' => '{{base_url}}/wp-json' . $clean_path,
                'host' => [ '{{base_url}}' ],
                'path' => array_merge(['wp-json'], array_values(array_filter(explode('/', $clean_path))))
            ];

            if (!empty($queryParams)) {
                $url_array['query'] = $queryParams;
                $queryString = implode('&', array_map(function($q) {
                    return urlencode($q['key']) . '=' . urlencode($q['value']);
                }, $queryParams));
                $url_array['raw'] .= '?' . $queryString;
            }

            $request_item = [
                'name' => $clean_path,
                'request' => [
                    'method' => $method,
                    'header' => $headers,
                    'url' => $url_array
                ]
            ];

            if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($bodyData)) {
                $request_item['request']['body'] = [
                    'mode' => 'raw',
                    'raw' => json_encode($bodyData, JSON_PRETTY_PRINT),
                    'options' => [ 'raw' => [ 'language' => 'json' ] ]
                ];
            }
            
            $collection['item'][$folder_index]['item'][] = $request_item;
        }
    }
}

file_put_contents('postman_collection.json', json_encode($collection, JSON_PRETTY_PRINT));
echo "Postman collection generated successfully as postman_collection.json\n";
