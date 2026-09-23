<?php

// Prevent worker script from being accessed directly
if (!isset($_SERVER['FRANKENPHP_WORKER'])) {
    die('FrankenPHP worker mode required');
}

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
