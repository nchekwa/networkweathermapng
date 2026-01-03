<?php
/**
 * WeatherMap Library Bootstrap
 * 
 * This file loads all the WeatherMap library components in the correct order.
 */

// Prevent double inclusion
if (defined('WEATHERMAP_BOOTSTRAP_LOADED')) {
    return;
}
define('WEATHERMAP_BOOTSTRAP_LOADED', true);

// Define base path for WeatherMap library
define('WEATHERMAP_LIB_PATH', __DIR__);

$pluginDirs = [
    WEATHERMAP_LIB_PATH . '/lib/pre',
    WEATHERMAP_LIB_PATH . '/lib/post',
];

foreach ($pluginDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// WeatherMap constants are defined in WeatherMap.class.php
// We don't define them here to avoid redefinition warnings

// Global variables used by WeatherMap
$WEATHERMAP_VERSION = '1.0.0';
$weathermap_debugging = false;
$weathermap_map = '';
$weathermap_warncount = 0;
$weathermap_error_suppress = [];
$weathermap_debug_suppress = [];

// Load geometry and helper classes first (before compat layer)
require_once __DIR__ . '/geometry.php';
require_once __DIR__ . '/WMPoint.class.php';
require_once __DIR__ . '/WMVector.class.php';
require_once __DIR__ . '/WMLine.class.php';

// Load HTML ImageMap class
require_once __DIR__ . '/HTML_ImageMap.class.php';

// Load WeatherMap functions (before compat layer so originals take precedence)
require_once __DIR__ . '/WeatherMap.functions.php';

// Load compatibility layer AFTER original functions (fills in missing Cacti functions)
require_once __DIR__ . '/compat.php';

// Load main WeatherMap class FIRST (contains base classes WeatherMapBase, WeatherMapItem)
require_once __DIR__ . '/WeatherMap.class.php';

// Load node and link classes AFTER main class (they extend WeatherMapItem)
require_once __DIR__ . '/WeatherMapNode.class.php';
require_once __DIR__ . '/WeatherMapLink.class.php';

/**
 * Helper function to enable WeatherMap debugging
 */
function weathermap_enable_debug($enable = true) {
    global $weathermap_debugging;
    $weathermap_debugging = $enable;
}

/**
 * Helper function to set the current map name for logging
 */
function weathermap_set_map_name($name) {
    global $weathermap_map;
    $weathermap_map = $name;
}

/**
 * Get the WeatherMap warning count
 */
function weathermap_get_warning_count() {
    global $weathermap_warncount;
    return $weathermap_warncount;
}

/**
 * Reset the WeatherMap warning count
 */
function weathermap_reset_warning_count() {
    global $weathermap_warncount;
    $weathermap_warncount = 0;
}
