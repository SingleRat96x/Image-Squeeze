<?php
/**
 * Cleanup Handler
 *
 * Handles scanning and removing orphaned WebP files.
 *
 * @package ImageSqueeze
 */

defined('ABSPATH') || exit;

/**
 * Register AJAX handlers for cleanup operations.
 */
function medshi_imsqz_register_cleanup_handlers() {
    add_action('wp_ajax_imagesqueeze_cleanup_orphaned', 'medshi_imsqz_cleanup_orphaned_callback');
}
add_action('init', 'medshi_imsqz_register_cleanup_handlers');

/**
 * AJAX callback to find and delete orphaned WebP files.
 */
function medshi_imsqz_cleanup_orphaned_callback() {
    // Verify nonce
    check_ajax_referer('image_squeeze_nonce', 'security');
    
    // Check capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'imagesqueeze'));
    }
    
    // Get the uploads directory info
    $upload_dir = wp_upload_dir();
    $base_dir = $upload_dir['basedir'];
    
    // Find all WebP files in the uploads directory
    $orphaned_files = medshi_imsqz_find_orphaned_webp_files($base_dir);
    
    // Delete the orphaned files
    $deleted_files = array();
    $deleted_count = 0;
    
    foreach ($orphaned_files as $file) {
        if (wp_delete_file($file)) {
            $deleted_files[] = str_replace($base_dir, '', $file); // Only store relative path
            $deleted_count++;
        }
    }
    
    // Add to logs
    medshi_imsqz_log_cleanup($deleted_count);
    
    // Limit the number of files to return (for performance)
    $files_to_return = array_slice($deleted_files, 0, 100);
    
    wp_send_json_success(array(
        'deleted_count' => $deleted_count,
        'files' => $files_to_return,
        'more_files' => ($deleted_count > 100)
    ));
}

/**
 * Log cleanup operation to the optimization log.
 *
 * @param int $deleted_count Number of files deleted.
 */
function medshi_imsqz_log_cleanup($deleted_count) {
    // Get current logs
    $logs = get_option('imagesqueeze_optimization_log', array());
    
    // Create new log entry
    $log_entry = array(
        'date' => current_time('mysql'),
        'job_type' => 'cleanup',
        'deleted' => $deleted_count
    );
    
    // Add to beginning of logs array
    array_unshift($logs, $log_entry);
    
    // Keep only the last 50 logs
    if (count($logs) > 50) {
        $logs = array_slice($logs, 0, 50);
    }
    
    // Save updated logs
    update_option('imagesqueeze_optimization_log', $logs);
}

/**
 * Find orphaned WebP files (WebP files with no corresponding original file).
 *
 * @param string $base_dir The base directory to scan.
 * @return array Array of orphaned WebP file paths.
 */
function medshi_imsqz_find_orphaned_webp_files($base_dir) {
    $orphaned_files = array();
    
    // Safety check - make sure this is in the uploads directory
    if (strpos($base_dir, 'wp-content/uploads') === false) {
        return $orphaned_files;
    }
    
    // Find all WebP files
    $webp_files = medshi_imsqz_find_files_by_extension($base_dir, 'webp');
    
    foreach ($webp_files as $webp_file) {
        // Skip WebP files that don't follow our naming convention
        if (strpos(basename($webp_file), '.webp') === false) {
            continue;
        }
        
        // Get the potential original file path by removing .webp extension
        $original_path = preg_replace('/\.webp$/', '', $webp_file);
        
        // Get file extension of the original
        $extension = pathinfo($original_path, PATHINFO_EXTENSION);
        
        // Skip if no extension (shouldn't happen, but just in case)
        if (empty($extension)) {
            continue;
        }
        
        // Check if the original file exists
        if (!file_exists($original_path)) {
            // This is an orphaned WebP file, add it to our list
            $orphaned_files[] = $webp_file;
        }
    }
    
    return $orphaned_files;
}

/**
 * Recursively find files with a specific extension in a directory.
 *
 * @param string $base_dir The base directory to scan.
 * @param string $extension The file extension to find (without the dot).
 * @return array Array of file paths.
 */
function medshi_imsqz_find_files_by_extension($base_dir, $extension) {
    $files = array();
    
    // Safety check - make sure this is in the uploads directory
    if (strpos($base_dir, 'wp-content/uploads') === false) {
        return $files;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === $extension) {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
} 