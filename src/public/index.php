<?php
/**
 * Zabbix Weathermap - Main Entry Point
 * 
 * Network visualization tool for Zabbix
 */

declare(strict_types=1);

// Define base paths
define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', __DIR__);

// Autoloader
$autoload = APP_ROOT . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    $autoload = dirname(APP_ROOT) . '/vendor/autoload.php';
}
require_once $autoload;

// Load environment variables
$envRoot = APP_ROOT;
if (!file_exists($envRoot . '/.env') && file_exists(dirname(APP_ROOT) . '/.env')) {
    $envRoot = dirname(APP_ROOT);
}
$dotenv = Dotenv\Dotenv::createImmutable($envRoot);
$dotenv->safeLoad();

// Bootstrap application
use App\Core\Application;

try {
    $app = new Application();
    $app->run();
} catch (Throwable $e) {
    // Handle fatal errors
    if (getenv('APP_DEBUG') === 'true') {
        echo '<h1>Application Error</h1>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        http_response_code(500);
        echo '<h1>Internal Server Error</h1>';
        echo '<p>An unexpected error occurred. Please try again later.</p>';
    }
    
    // Log the error
    error_log('Fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}
