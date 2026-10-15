<?php
declare(strict_types=1);
define('PUBLIC_ROOT', __DIR__);
$private = dirname(__DIR__) . '/filolux-private';
define('ROOT', $private);
// Write PHP errors outside the public directory, including startup failures.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
$logDirectory = ROOT . '/storage';
if (is_dir($logDirectory) && is_writable($logDirectory)) {
    $logFile = $logDirectory . '/php-error.log';
    ini_set('error_log', $logFile);
    if (!is_file($logFile)) {
        error_log('FiloLux: registro privado de errores iniciado.');
        if (is_file($logFile)) chmod($logFile, 0600);
    }
}
require ROOT . '/app/bootstrap.php';
