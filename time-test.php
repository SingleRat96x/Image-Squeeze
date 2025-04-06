<?php
/**
 * WordPress Time Test Script
 * This script checks how WordPress timestamp functions are working in the current environment.
 */

// Bootstrap WordPress
require_once dirname(__FILE__, 4) . '/wp-load.php';

// Prevent direct access to this file.
if (!function_exists('add_action')) {
    echo "Cannot access this file directly.";
    exit;
}

echo "<h1>WordPress Time Debug</h1>";

// Test 1: Get WordPress timezone settings
$timezone_string = get_option('timezone_string');
$gmt_offset = get_option('gmt_offset');
echo "<h2>WordPress Timezone Settings</h2>";
echo "Timezone String: " . ($timezone_string ?: 'Not set') . "<br>";
echo "GMT Offset: " . ($gmt_offset ?: '0') . "<br>";

// Test 2: Current time according to different methods
echo "<h2>Current Time</h2>";
echo "PHP time(): " . date('Y-m-d H:i:s', time()) . "<br>";
echo "current_time('mysql'): " . current_time('mysql') . "<br>";
echo "current_time('mysql', true): " . current_time('mysql', true) . " (GMT)<br>";
echo "date_i18n(datetime): " . date_i18n('Y-m-d H:i:s') . "<br>";

// Test 3: Format time with date_i18n
$timestamp = time();
echo "<h2>Time Formatting</h2>";
echo "Original timestamp: " . $timestamp . "<br>";
echo "date(): " . date('Y-m-d H:i:s', $timestamp) . "<br>";
echo "date_i18n(): " . date_i18n('Y-m-d H:i:s', $timestamp) . "<br>";
echo "date_i18n() with get_option('time_format'): " . date_i18n(get_option('time_format'), $timestamp) . "<br>";

// Test 4: Simulate how our logs are created and displayed
echo "<h2>Log Timestamp Simulation</h2>";
// Create timestamp with current_time (like we do in the job manager)
$log_created_time = current_time('mysql');
echo "Log created with current_time('mysql'): " . $log_created_time . "<br>";

// Parse and display the time (like we do in logs-ui.php)
$parsed_timestamp = strtotime($log_created_time);
echo "strtotime() result: " . $parsed_timestamp . "<br>";
echo "Time extracted and formatted with date_i18n(): " . date_i18n(get_option('time_format'), $parsed_timestamp) . "<br>";

// Test 5: Check what happens with timestamps and LOCAL
echo "<h2>WordPress Server Time Info</h2>";
echo "PHP Default Timezone: " . date_default_timezone_get() . "<br>";
echo "Server Timezone from PHP: " . ini_get('date.timezone') . "<br>";
echo "WordPress WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled') . "<br>"; 