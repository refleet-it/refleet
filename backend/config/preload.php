<?php

$appId = $_SERVER['APP_ID'] ?? $_ENV['APP_ID'] ?? 'monolith';
$preload = \dirname(__DIR__).'/var/cache/'.$appId.'/prod/App_KernelProdContainer.preload.php';

if (\file_exists($preload)) {
    require $preload;
}
