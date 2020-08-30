<?php

return [
    // default limit
    'limit' => 25,

    // default order
    'order' => [
        [
            'column' => 'id',
            'direction' => 'desc'
        ],
    ],

    // excluded parameters to filters
    'excluded_parameters' => ['token', 'password'],

    /*
     * By default the package will use the `limit`, `filter` and `order`
     * query parameters as described in the readme.
     *
     * You can customize these query string parameters here.
     */
    'parameters' => [
        'limit' => 'limit',

        'filter' => 'filter',

        'order' => 'order',
    ],






    /*
     * Related model counts are included using the relationship name suffixed with this string.
     * For example: GET /users?include=postsCount
     */
    'count_suffix' => 'Count',
];
