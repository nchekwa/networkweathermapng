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
require_once APP_ROOT . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(APP_ROOT . '/..');
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
