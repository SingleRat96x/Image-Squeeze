<?php
/**
 * Uninstall Image Squeeze
 *
 * @package ImageSqueeze
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// If current user cannot activate plugins, then exit.
if (!current_user_can('activate_plugins')) {
    exit;
}

// Delete plugin options
$options_to_delete = [
    'imagesqueeze_current_job',
    'imagesqueeze_job_queue',
    'imagesqueeze_optimization_log',
    // Add any other global plugin options here.
];

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Delete post meta data
global $wpdb;
$post_meta_to_delete = [
    '_imagesqueeze_optimized',
    '_imagesqueeze_webp_sizes',
    '_imagesqueeze_error_message',
    '_imagesqueeze_status',
    '_imagesqueeze_last_attempt',
];

foreach ($post_meta_to_delete as $meta_key) {
    $wpdb->delete($wpdb->postmeta, ['meta_key' => $meta_key], ['%s']);
}

// Note: We're intentionally leaving .webp files untouched 