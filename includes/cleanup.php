<?php
/**
 * Cleanup functionality
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

/**
 * Find orphaned WebP files in the uploads directory.
 *
 * @return array List of orphaned WebP file paths.
 */
function image_squeeze_find_orphaned_webps() {
    // Get the uploads directory
    $upload_dir = wp_upload_dir();
    $base_dir = $upload_dir['basedir'];
    
    // Array to store orphaned WebP files
    $orphaned_webps = array();
    
    // Only proceed if the directory exists
    if ( ! file_exists( $base_dir ) || ! is_dir( $base_dir ) ) {
        return $orphaned_webps;
    }
    
    // Recursively scan the uploads directory
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS )
    );
    
    // Loop through all files
    foreach ( $iterator as $file ) {
        // Skip directories
        if ( $file->isDir() ) {
            continue;
        }
        
        $file_path = $file->getPathname();
        
        // Check if this is a WebP file
        if ( strtolower( $file->getExtension() ) === 'webp' ) {
            // Get the base filename by removing .webp extension
            $base_file_path = substr( $file_path, 0, -5 ); // Remove .webp
            
            // Check if the original file exists
            if ( ! file_exists( $base_file_path ) ) {
                // This is an orphaned WebP file
                $orphaned_webps[] = $file_path;
            }
        }
    }
    
    return $orphaned_webps;
}

/**
 * Delete orphaned WebP files.
 *
 * @param array $webp_files Array of file paths to delete.
 * @return int Number of files deleted.
 */
function image_squeeze_delete_orphaned_webps( $webp_files ) {
    // Get the uploads directory for safety check
    $upload_dir = wp_upload_dir();
    $base_dir = $upload_dir['basedir'];
    
    // Normalize the path for comparison (ensure trailing slash)
    $base_dir = trailingslashit( $base_dir );
    
    $deleted_count = 0;
    
    // Initialize the WordPress Filesystem
    global $wp_filesystem;
    if ( ! $wp_filesystem ) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
    }
    
    foreach ( $webp_files as $file_path ) {
        // Safety check: Ensure the file is within the uploads directory
        if ( strpos( $file_path, $base_dir ) !== 0 ) {
            // Skip files outside the uploads directory
            continue;
        }
        
        // Safety check: Only delete .webp files
        if ( ! preg_match( '/\.webp$/i', $file_path ) ) {
            continue;
        }
        
        // Try to delete the file using WordPress functions
        if ( file_exists( $file_path ) && $wp_filesystem->exists( $file_path ) && wp_delete_file( $file_path ) ) {
            $deleted_count++;
        }
    }
    
    return $deleted_count;
} 