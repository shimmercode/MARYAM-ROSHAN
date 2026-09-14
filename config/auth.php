<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'session' => [
        'name'         => 'MR_SESSION',
        'lifetime'     => 7200,
        'idle_timeout' => 7200,
        'samesite'     => 'Lax',
        'domain'       => Env::get('SESSION_DOMAIN', ''),
    ],

    'password' => [
        'algo'       => PASSWORD_BCRYPT,
        'options'    => ['cost' => 11],
        'min_length' => 8,
    ],

    'remember' => [
        'cookie'   => 'mr_remember',
        'lifetime' => 60 * 60 * 24 * 30,
    ],

    'throttle' => [
        'max_attempts'   => 5,
        'decay_minutes'  => 15,
        'lockout_minutes' => 15,
    ],

    'two_factor' => [
        'enabled'    => (bool)Env::get('TWO_FACTOR_ENABLED', false),
        'otp_length' => 6,
        'otp_ttl'    => 300,
    ],

    'roles' => [
        'SUPER_ADMIN'    => 'مدیر ارشد',
        'ADMIN'          => 'مدیر',
        'BRANCH_MANAGER' => 'مدیر شعبه',
        'RECEPTION'      => 'پذیرش',
        'SPECIALIST'     => 'متخصص',
        'CUSTOMER'       => 'مشتری',
    ],

    'home_route' => [
        'SUPER_ADMIN'    => '/admin',
        'ADMIN'          => '/admin',
        'BRANCH_MANAGER' => '/admin',
        'RECEPTION'      => '/admin',
        'SPECIALIST'     => '/staff',
        'CUSTOMER'       => '/customer',
    ],
];
