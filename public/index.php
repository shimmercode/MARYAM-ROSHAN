<?php
declare(strict_types=1);

/**
 * Maryam Roshan Beauty Salon Digital Portal — single front controller.
 * All public traffic is routed here by public/.htaccess.
 */

define('MR_START', microtime(true));

$root = dirname(__DIR__);

require_once $root . '/app/core/App.php';

use App\Core\App;

App::instance()
    ->boot($root)
    ->loadRoutes($root . '/routes')
    ->run();
