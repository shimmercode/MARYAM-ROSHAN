<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'sms' => [
        'enabled'  => (bool)Env::get('SMS_ENABLED', false),
        'driver'   => Env::get('SMS_DRIVER', 'log'), // log | kavenegar | melipayamak
        'sender'   => Env::get('SMS_SENDER', ''),
        'api_key'  => Env::get('SMS_API_KEY', ''),
        'username' => Env::get('SMS_USERNAME', ''),
        'password' => Env::get('SMS_PASSWORD', ''),
        'timeout'  => 10,
    ],

    'mail' => [
        'enabled'   => (bool)Env::get('MAIL_ENABLED', false),
        'driver'    => Env::get('MAIL_DRIVER', 'log'), // log | mail | smtp
        'host'      => Env::get('SMTP_HOST', ''),
        'port'      => (int)Env::get('SMTP_PORT', 587),
        'username'  => Env::get('SMTP_USER', ''),
        'password'  => Env::get('SMTP_PASS', ''),
        'from'      => Env::get('MAIL_FROM', 'no-reply@maryamroshan.local'),
        'from_name' => Env::get('MAIL_FROM_NAME', 'سالن زیبایی مریم روشن'),
    ],

    'payment' => [
        'enabled'  => (bool)Env::get('PAYMENT_ENABLED', false),
        'driver'   => Env::get('PAYMENT_DRIVER', 'manual'),
        'api_key'  => Env::get('PAYMENT_API_KEY', ''),
        'callback' => Env::get('PAYMENT_CALLBACK', '/customer/payments/callback'),
    ],
];
