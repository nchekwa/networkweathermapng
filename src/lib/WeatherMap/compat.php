<?php
/**
 * Cacti Compatibility Layer
 * 
 * This file provides stub functions to replace Cacti-specific functions
 * used by the original WeatherMap plugin, allowing it to run standalone.
 */

// Prevent double inclusion
if (defined('CACTI_COMPAT_LOADED')) {
    return;
}
define('CACTI_COMPAT_LOADED', true);

/**
 * Replacement for cacti_log - logs to PHP error log or custom log file
 */
if (!function_exists('cacti_log')) {
    function cacti_log($message, $output = false, $environ = 'WEATHERMAP') {
        $logMessage = date('Y-m-d H:i:s') . " [$environ] $message";
        error_log($logMessage);
        if ($output) {
            echo $logMessage . "\n";
        }
    }
}

/**
 * Replacement for cacti_sizeof - counts array elements safely
 */
if (!function_exists('cacti_sizeof')) {
    function cacti_sizeof($array) {
        if (is_array($array)) {
            return count($array);
        }
        return 0;
    }
}

/**
 * Replacement for read_config_option - reads from our config
 */
if (!function_exists('read_config_option')) {
    function read_config_option($option, $default = '') {
        static $config = null;
        
        if ($config === null) {
            $config = [
                'weathermap_width' => 400,
                'weathermap_height' => 300,
                'weathermap_output_format' => 'png',
                'weathermap_thumbsize' => 250,
                'weathermap_cycle_refresh' => 300,
                'weathermap_all_tab' => 'on',
                'weathermap_map_selector' => 'on',
                'weathermap_quiet_logging' => 'off',
                'weathermap_render_period' => 0,
                'weathermap_pagestyle' => 0,
                'weathermap_poller_output' => 'output',
                'base_path' => defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2),
            ];
        }
        
        return $config[$option] ?? $default;
    }
}

/**
 * Replacement for db_fetch_assoc_prepared - returns empty array
 */
if (!function_exists('db_fetch_assoc_prepared')) {
    function db_fetch_assoc_prepared($sql, $params = []) {
        // This would need to be connected to our database
        return [];
    }
}

/**
 * Replacement for db_fetch_cell_prepared - returns null
 */
if (!function_exists('db_fetch_cell_prepared')) {
    function db_fetch_cell_prepared($sql, $params = []) {
        return null;
    }
}

/**
 * Replacement for db_fetch_assoc - returns empty array
 */
if (!function_exists('db_fetch_assoc')) {
    function db_fetch_assoc($sql) {
        return [];
    }
}

/**
 * Replacement for db_fetch_row - returns empty array
 */
if (!function_exists('db_fetch_row')) {
    function db_fetch_row($sql) {
        return [];
    }
}

/**
 * Replacement for db_execute_prepared
 */
if (!function_exists('db_execute_prepared')) {
    function db_execute_prepared($sql, $params = []) {
        return true;
    }
}

/**
 * Replacement for debug_log_insert
 */
if (!function_exists('debug_log_insert')) {
    function debug_log_insert($type, $message) {
        error_log("[$type] $message");
    }
}

/**
 * HTML escape function
 */
if (!function_exists('html_escape')) {
    function html_escape($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Check if value is 'none'
 */
if (!function_exists('is_none')) {
    function is_none($value) {
        if (is_array($value)) {
            return isset($value[0]) && $value[0] === -1;
        }
        return $value === 'none' || $value === -1;
    }
}

/**
 * Image creation wrapper functions
 */
if (!function_exists('wimagecreatetruecolor')) {
    function wimagecreatetruecolor($width, $height) {
        return imagecreatetruecolor(intval($width), intval($height));
    }
}

if (!function_exists('wimagefilledrectangle')) {
    function wimagefilledrectangle($image, $x1, $y1, $x2, $y2, $color) {
        return imagefilledrectangle($image, intval($x1), intval($y1), intval($x2), intval($y2), $color);
    }
}

if (!function_exists('wimagerectangle')) {
    function wimagerectangle($image, $x1, $y1, $x2, $y2, $color) {
        return imagerectangle($image, intval($x1), intval($y1), intval($x2), intval($y2), $color);
    }
}

if (!function_exists('wimagepolygon')) {
    function wimagepolygon($image, $points, $num_points, $color) {
        return imagepolygon($image, $points, $num_points, $color);
    }
}

if (!function_exists('wimagefilledpolygon')) {
    function wimagefilledpolygon($image, $points, $num_points, $color) {
        return imagefilledpolygon($image, $points, $num_points, $color);
    }
}

if (!function_exists('myimagecolorallocate')) {
    function myimagecolorallocate($image, $red, $green, $blue) {
        return imagecolorallocate($image, intval($red), intval($green), intval($blue));
    }
}

if (!function_exists('imagecreatefromfile')) {
    function imagecreatefromfile($filename) {
        $info = getimagesize($filename);
        if ($info === false) {
            return false;
        }
        
        switch ($info[2]) {
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filename);
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filename);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filename);
            default:
                return false;
        }
    }
}

/**
 * Screenshot mode helper
 */
if (!function_exists('screenshotify')) {
    function screenshotify($string) {
        // Replace potentially identifying information
        return preg_replace('/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/', 'x.x.x.x', $string);
    }
}

/**
 * Metadata dump - no-op in standalone mode
 */
if (!function_exists('metadump')) {
    function metadump($message, $reset = false) {
        // No-op in standalone mode
    }
}

/**
 * Nice scalar formatting
 */
if (!function_exists('nice_scalar')) {
    function nice_scalar($value, $kilo = 1000, $decimals = 2) {
        $units = ['', 'K', 'M', 'G', 'T', 'P'];
        $unit_index = 0;
        
        while (abs($value) >= $kilo && $unit_index < count($units) - 1) {
            $value /= $kilo;
            $unit_index++;
        }
        
        return round($value, $decimals) . $units[$unit_index];
    }
}

/**
 * Rotate points about a center point
 */
if (!function_exists('rotateAboutPoint')) {
    function rotateAboutPoint(&$points, $cx, $cy, $angle) {
        $cos_angle = cos($angle);
        $sin_angle = sin($angle);
        
        for ($i = 0; $i < count($points); $i += 2) {
            $x = $points[$i] - $cx;
            $y = $points[$i + 1] - $cy;
            
            $points[$i] = $x * $cos_angle - $y * $sin_angle + $cx;
            $points[$i + 1] = $x * $sin_angle + $y * $cos_angle + $cy;
        }
    }
}

/**
 * Plugin version function - returns version string
 */
if (!function_exists('plugin_weathermap_numeric_version')) {
    function plugin_weathermap_numeric_version() {
        return '1.0.0';
    }
}

/**
 * Unformat number - convert K/M/G suffixed numbers to raw values
 */
if (!function_exists('unformat_number')) {
    function unformat_number($string, $kilo = 1000) {
        $string = trim($string);
        if (preg_match('/^([0-9.]+)([KMGTP]?)$/i', $string, $matches)) {
            $value = floatval($matches[1]);
            $suffix = strtoupper($matches[2] ?? '');
            
            switch ($suffix) {
                case 'K': return $value * $kilo;
                case 'M': return $value * $kilo * $kilo;
                case 'G': return $value * $kilo * $kilo * $kilo;
                case 'T': return $value * $kilo * $kilo * $kilo * $kilo;
                case 'P': return $value * $kilo * $kilo * $kilo * $kilo * $kilo;
                default: return $value;
            }
        }
        return floatval($string);
    }
}

/**
 * DB quote function
 */
if (!function_exists('db_qstr')) {
    function db_qstr($string) {
        return "'" . addslashes($string) . "'";
    }
}

/**
 * Get request variable functions
 */
if (!function_exists('get_nfilter_request_var')) {
    function get_nfilter_request_var($name, $default = '') {
        return $_REQUEST[$name] ?? $default;
    }
}

if (!function_exists('get_filter_request_var')) {
    function get_filter_request_var($name, $default = 0) {
        $val = $_REQUEST[$name] ?? $default;
        return is_numeric($val) ? intval($val) : $default;
    }
}

if (!function_exists('get_request_var')) {
    function get_request_var($name, $default = '') {
        return $_REQUEST[$name] ?? $default;
    }
}

if (!function_exists('isset_request_var')) {
    function isset_request_var($name) {
        return isset($_REQUEST[$name]);
    }
}

if (!function_exists('set_request_var')) {
    function set_request_var($name, $value) {
        $_REQUEST[$name] = $value;
    }
}
