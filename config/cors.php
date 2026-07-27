<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/*', 'storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // Berubah menjadi bintang (semua port diizinkan)
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false, // Wajib diubah ke false jika allowed_origins menggunakan '*'
];
