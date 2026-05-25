<?php
// Raw disk log — runs before Laravel boots, proves if PHP is executing this file at all
$logFile = __DIR__ . '/debug.log';
$entry = date('Y-m-d H:i:s') . "\n" .
         "METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n" .
         "URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n" .
         "BODY: " . file_get_contents('php://input') . "\n" .
         str_repeat('-', 60) . "\n";
file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

// Bootstrap Laravel normally
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());
$response->send();
$kernel->terminate($request, $response);
