<?php
/**
 * Upload handler functionality
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

/**
 * Handle image upload and perform optimization if enabled
 *
 * @param array $metadata      The attachment metadata.
 * @param int   $attachment_id The attachment ID.
 * @return array The attachment metadata.
 */
function medshi_imsqz_handle_upload($metadata, $attachment_id) {
    // Check if auto-optimize is enabled
    $settings = get_option('imagesqueeze_settings', []);
    if (empty($settings['optimize_on_upload'])) {
        return $metadata;
    }
    
    // Get the attachment
    $attachment = get_post($attachment_id);
    if (!$attachment) {
        return $metadata;
    }
    
    // Check if it's an image
    if (strpos($attachment->post_mime_type, 'image/') !== 0) {
        return $metadata;
    }
    
    // Only process JPEG and PNG images
    if ($attachment->post_mime_type !== 'image/jpeg' && $attachment->post_mime_type !== 'image/png') {
        return $metadata;
    }
    
    // Get file path
    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path)) {
        return $metadata;
    }
    
    // Process the image using our optimization function
    $quality = isset($settings['quality']) ? intval($settings['quality']) : 80;
    
    // Initialize the saved bytes counter if not already set
    if (!isset($GLOBALS['imagesqueeze_job_saved_bytes'])) {
        $GLOBALS['imagesqueeze_job_saved_bytes'] = 0;
    }
    
    // Convert to WebP (use existing function)
    if (function_exists('medshi_imsqz_process_image')) {
        // Process the image but never delete the original
        $result = medshi_imsqz_process_image($attachment_id, $quality);
    }
    
    return $metadata;
}

// Hook into WordPress media handling
add_filter('wp_generate_attachment_metadata', 'medshi_imsqz_handle_upload', 20, 2); 