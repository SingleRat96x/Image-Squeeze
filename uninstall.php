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
$post_meta_to_delete = [
    '_imagesqueeze_optimized',
    '_imagesqueeze_webp_sizes',
    '_imagesqueeze_error_message',
    '_imagesqueeze_status',
    '_imagesqueeze_last_attempt',
];

// Use WordPress API to delete post meta instead of direct database queries
// First, get all attachment IDs (we'll limit this for large sites)
$attachment_ids = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'any',
    'fields' => 'ids',
    'posts_per_page' => -1,
    'meta_query' => [
        'relation' => 'OR',
        ['key' => '_imagesqueeze_optimized', 'compare' => 'EXISTS'],
        ['key' => '_imagesqueeze_webp_sizes', 'compare' => 'EXISTS'],
        ['key' => '_imagesqueeze_error_message', 'compare' => 'EXISTS'],
        ['key' => '_imagesqueeze_status', 'compare' => 'EXISTS'],
        ['key' => '_imagesqueeze_last_attempt', 'compare' => 'EXISTS'],
    ]
]);

// Delete meta for each attachment
if (!empty($attachment_ids)) {
    foreach ($attachment_ids as $attachment_id) {
        foreach ($post_meta_to_delete as $meta_key) {
            delete_post_meta($attachment_id, $meta_key);
        }
    }
}

// Clear all caches related to image squeeze
$cache_keys = [
    'imagesqueeze_unoptimized_images',
    'imagesqueeze_failed_images',
    'imagesqueeze_optimized_count',
    'imagesqueeze_failed_count'
];

foreach ($cache_keys as $cache_key) {
    wp_cache_delete($cache_key);
}

// Note: We're intentionally leaving .webp files untouched 