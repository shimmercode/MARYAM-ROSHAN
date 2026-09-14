<?php
declare(strict_types=1);

use App\Core\Env;

$root = dirname(__DIR__);

return [
    'name'        => Env::get('APP_NAME', 'سالن زیبایی مریم روشن'),
    'env'         => Env::get('APP_ENV', 'production'),
    'debug'       => (bool)Env::get('APP_DEBUG', false),
    'url'         => rtrim((string)Env::get('APP_URL', ''), '/'),
    'base_path'   => Env::get('APP_BASE_PATH', ''),
    'key'         => Env::get('APP_KEY', ''),
    'version'     => '0.1.6',
    'timezone'    => Env::get('APP_TIMEZONE', 'Asia/Tehran'),
    'locale'      => 'fa',
    'direction'   => 'rtl',
    'currency'    => 'تومان',

    'root_path'    => $root,
    'app_path'     => $root . '/app',
    'views_path'   => $root . '/app/views',
    'public_path'  => $root . '/public',
    'storage_path' => $root . '/storage',
    'uploads_path' => $root . '/public/uploads',

    'installed_flag' => $root . '/storage/installed.lock',

    'pagination' => ['default' => 20, 'options' => [20, 50, 100]],

    'security_headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options'        => 'SAMEORIGIN',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'X-XSS-Protection'       => '1; mode=block',
        'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=()',
    ],

    'uploads' => [
        'max_size'      => 5 * 1024 * 1024,
        'allowed_ext'   => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'xlsx', 'csv', 'docx', 'pptx'],
        'allowed_mimes' => [
            'image/jpeg', 'image/png', 'image/webp', 'application/pdf',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
            // فایل‌های Office Open XML گاهی توسط fileinfo به‌عنوان بسته ZIP خام شناسایی می‌شوند.
            'application/zip',
        ],
    ],
];
