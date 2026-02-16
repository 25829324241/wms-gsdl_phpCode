<?php

return [
    'autoload' => false,
    'hooks' => [
        'app_init' => [
            'barcode',
        ],
        'admin_login_init' => [
            'loginbg',
        ],
    ],
    'route' => [
        '/barcode$' => 'barcode/index/index',
        '/barcode/build$' => 'barcode/index/build',
    ],
    'priority' => [],
    'domain' => '',
];
