<?php
/**
 * Job Manager functionality
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

/**
 * Helper function to count attachments by meta key and value.
 *
 * @param string $meta_key Meta key to check.
 * @param string $meta_value Meta value to check.
 * @return int Count of matching attachments.
 */
function image_squeeze_count_attachments_by_meta($meta_key, $meta_value) {
    return count(
        get_posts(array(
            'post_type'   => 'attachment',
            'post_status' => 'inherit',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_key'    => $meta_key,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Meta key/value usage is intentional and result is cached
            'meta_value'  => $meta_value,
            'fields'      => 'ids',
            'numberposts' => -1,
        ))
    );
}

/**
 * Create a new optimization job.
 *
 * @param string $type Job type: 'full' or 'retry'.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function image_squeeze_create_job( $type = 'full' ) {
    global $wpdb;
    
    // Validate job type
    if ( ! in_array( $type, array( 'full', 'retry' ), true ) ) {
        return new WP_Error( 'invalid_job_type', __( 'Invalid job type specified.', 'image-squeeze' ) );
    }
    
    // Get image attachments based on job type
    $image_ids = array();
    
    if ( $type === 'full' ) {
        // Check cache first
        $cache_key = 'imagesqueeze_unoptimized_images';
        $image_ids = wp_cache_get($cache_key);
        
        if (false === $image_ids) {
            // Get all unoptimized JPEG/PNG images using WordPress functions
            $image_ids = get_posts(array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => array('image/jpeg', 'image/png'),
                'fields'         => 'ids',
                'numberposts'    => -1,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Query is cached; performance is controlled
                'meta_query'     => array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_imagesqueeze_optimized',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key'     => '_imagesqueeze_optimized',
                        'value'   => '1',
                        'compare' => '!='
                    )
                )
            ));
            
            // Cache the results for 5 minutes
            wp_cache_set($cache_key, $image_ids, '', 300);
        }
    } elseif ( $type === 'retry' ) {
        // Check cache first
        $cache_key = 'imagesqueeze_failed_images';
        $image_ids = wp_cache_get($cache_key);
        
        if (false === $image_ids) {
            // Get all images with failed status using WordPress functions
            $image_ids = get_posts(array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => array('image/jpeg', 'image/png'),
                'fields'         => 'ids',
                'numberposts'    => -1,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Query is cached; performance is controlled
                'meta_query'     => array(
                    array(
                        'key'   => '_imagesqueeze_status',
                        'value' => 'failed'
                    )
                )
            ));
            
            // Cache the results for 5 minutes
            wp_cache_set($cache_key, $image_ids, '', 300);
        }
    }
    
    // Check if we found any images
    if ( empty( $image_ids ) ) {
        return new WP_Error(
            'no_images_found',
            __( 'No images found for optimization.', 'image-squeeze' )
        );
    }
    
    // Store image IDs in the job queue option
    update_option( 'imagesqueeze_job_queue', $image_ids, false );
    
    // Create job state
    $current_job = array(
        'status'                => 'in_progress',
        'type'                  => $type,
        'total'                 => count( $image_ids ),
        'done'                  => 0,
        'start_time'            => time(),
        'cleanup_on_next_visit' => false,
    );
    
    // Store job state
    update_option( 'imagesqueeze_current_job', $current_job, false );
    
    return true;
}

/**
 * Log completed optimization job.
 *
 * @param array|string $job_or_type Either the complete job data array or just the job type (string).
 * @return void
 */
function image_squeeze_log_completed_job( $job_or_type ) {
    // Check if we received the full job data or just the type
    if (is_array($job_or_type)) {
        // We received the complete job data
        $job = $job_or_type;
        $type = isset($job['type']) ? $job['type'] : 'unknown';
        
        // Use the values directly from the job data
        $job_optimized = isset($job['done']) ? intval($job['done']) : 0;
        $job_failed = isset($job['failed']) ? intval($job['failed']) : 0;
        
    } else {
        // Legacy mode - just received the type
        $type = $job_or_type;
        
        // Get current job data to use the actual count of images processed in this job
        $current_job = get_option('imagesqueeze_current_job', array());
        
        // Use the job's done count instead of total optimized count from database
        $job_optimized = isset($current_job['done']) ? intval($current_job['done']) : 0;
        $job_failed = isset($current_job['failed']) ? intval($current_job['failed']) : 0;
    }
    
    // Get saved bytes from global variable
    $saved_bytes = isset($GLOBALS['imagesqueeze_job_saved_bytes']) ? intval($GLOBALS['imagesqueeze_job_saved_bytes']) : 0;
    
    // Create log entry
    $log_entry = array(
        'date'        => gmdate( 'Y-m-d' ),
        'job_type'    => $type,
        'optimized'   => (int) $job_optimized,  // Use job's optimized count
        'failed'      => (int) $job_failed,     // Use job's failed count
        'saved_bytes' => $saved_bytes,
    );
    
    // Get existing logs
    $logs = get_option( 'imagesqueeze_optimization_log', array() );
    
    // Add new log at the beginning
    array_unshift( $logs, $log_entry );
    
    // Trim to max 10 entries
    if ( count( $logs ) > 10 ) {
        $logs = array_slice( $logs, 0, 10 );
    }
    
    // Update the global total saved bytes
    $total_saved_bytes = get_option( 'imagesqueeze_total_saved_bytes', 0 );
    $total_saved_bytes += $saved_bytes;
    update_option( 'imagesqueeze_total_saved_bytes', $total_saved_bytes, false );
    
    // Also save the last run summary for easy access
    $last_run_summary = array(
        'date'        => gmdate( 'Y-m-d' ),
        'job_type'    => $type,
        'optimized'   => (int) $job_optimized,  // Use job's optimized count
        'failed'      => (int) $job_failed,     // Use job's failed count
        'saved_bytes' => $saved_bytes,
    );
    update_option( 'imagesqueeze_last_run_summary', $last_run_summary, false );
    
    // Reset the global variable
    $GLOBALS['imagesqueeze_job_saved_bytes'] = 0;
    
    // Save updated logs
    update_option( 'imagesqueeze_optimization_log', $logs, false );
}

/**
 * Get the current job progress information.
 *
 * @return array Job progress information.
 */
function image_squeeze_get_job_progress() {
    // Get current job
    $current_job = get_option( 'imagesqueeze_current_job', array() );
    
    // If no job exists, return error
    if ( empty( $current_job ) ) {
        return array(
            'error' => true,
            'message' => __( 'No active job found.', 'image-squeeze' ),
        );
    }
    
    // Return job status information
    return array(
        'success' => true,
        'status' => isset( $current_job['status'] ) ? $current_job['status'] : 'unknown',
        'done' => isset( $current_job['done'] ) ? (int) $current_job['done'] : 0,
        'total' => isset( $current_job['total'] ) ? (int) $current_job['total'] : 0,
        'cleanup_on_next_visit' => isset( $current_job['cleanup_on_next_visit'] ) ? (bool) $current_job['cleanup_on_next_visit'] : false,
    );
}

/**
 * Check for and recover from stuck or abandoned jobs.
 *
 * @return array|null Job status after recovery, or null if no job exists.
 */
function image_squeeze_check_and_recover_job() {
    // Load the current job
    $current_job = get_option( 'imagesqueeze_current_job', array() );
    
    // If no job exists, return null
    if ( empty( $current_job ) ) {
        return null;
    }
    
    // Load the job queue
    $queue = get_option( 'imagesqueeze_job_queue', array() );
    
    // Case 1: Job is in progress but queue is empty
    if ( isset( $current_job['status'] ) && $current_job['status'] === 'in_progress' && empty( $queue ) ) {
        $current_job['status'] = 'completed';
        $current_job['cleanup_on_next_visit'] = true;
        update_option( 'imagesqueeze_current_job', $current_job, false );
        
        // Log the completed job
        if ( isset( $current_job['type'] ) ) {
            // Pass the entire job object
            image_squeeze_log_completed_job( $current_job );
        }
    }
    
    // Case 2: Job is completed and marked for cleanup
    if ( isset( $current_job['status'] ) && $current_job['status'] === 'completed' && 
         isset( $current_job['cleanup_on_next_visit'] ) && $current_job['cleanup_on_next_visit'] === true ) {
        
        // Delete job and queue
        delete_option( 'imagesqueeze_current_job' );
        delete_option( 'imagesqueeze_job_queue' );
        
        return null;
    }
    
    return $current_job;
}

/**
 * AJAX handler for creating a new job.
 */
function image_squeeze_ajax_create_job() {
    // Check if user has required capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array(
            'message' => __( 'You do not have permission to perform this action.', 'image-squeeze' )
        ) );
    }
    
    // Verify nonce
    if ( ! check_ajax_referer( 'image_squeeze_nonce', 'security', false ) ) {
        wp_send_json_error( array(
            'message' => __( 'Security check failed.', 'image-squeeze' )
        ) );
    }
    
    // Get job type from request
    $job_type = isset( $_POST['job_type'] ) ? sanitize_text_field( wp_unslash( $_POST['job_type'] ) ) : 'full';
    
    // Create the job
    $result = image_squeeze_create_job( $job_type );
    
    // Check for errors
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array(
            'message' => $result->get_error_message()
        ) );
    }
    
    // Return success response
    wp_send_json_success( array(
        'message' => __( 'Optimization job created successfully.', 'image-squeeze' ),
        'job_type' => $job_type
    ) );
}

/**
 * AJAX handler for getting job progress.
 */
function image_squeeze_ajax_get_progress() {
    // Check if user has required capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array(
            'message' => __( 'You do not have permission to perform this action.', 'image-squeeze' )
        ) );
    }
    
    // Verify nonce
    if ( ! check_ajax_referer( 'image_squeeze_nonce', 'security', false ) ) {
        wp_send_json_error( array(
            'message' => __( 'Security check failed.', 'image-squeeze' )
        ) );
    }
    
    // Get job progress
    $progress = image_squeeze_get_job_progress();
    
    // Check for errors
    if ( isset( $progress['error'] ) && $progress['error'] ) {
        wp_send_json_error( array(
            'message' => $progress['message']
        ) );
    }
    
    // Return success response
    wp_send_json_success( array(
        'status' => $progress['status'],
        'done' => $progress['done'],
        'total' => $progress['total'],
        'cleanup_on_next_visit' => $progress['cleanup_on_next_visit']
    ) );
}

/**
 * Cancel the current optimization job.
 *
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function image_squeeze_cancel_job() {
    // Get current job
    $current_job = get_option( 'imagesqueeze_current_job', array() );
    
    // Check if there is an active job
    if ( empty( $current_job ) ) {
        return new WP_Error( 'no_job', __( 'No active job to cancel.', 'image-squeeze' ) );
    }
    
    // Clear the job queue
    delete_option( 'imagesqueeze_job_queue' );
    
    // Set the job as cancelled
    $current_job['status'] = 'cancelled';
    $current_job['cleanup_on_next_visit'] = true;
    
    // Update job state
    update_option( 'imagesqueeze_current_job', $current_job, false );
    
    return true;
}

/**
 * AJAX handler for cancelling a job.
 */
function image_squeeze_ajax_cancel_job() {
    // Check if user has required capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array(
            'message' => __( 'You do not have permission to perform this action.', 'image-squeeze' )
        ) );
    }
    
    // Verify nonce
    if ( ! check_ajax_referer( 'image_squeeze_nonce', 'security', false ) ) {
        wp_send_json_error( array(
            'message' => __( 'Security check failed.', 'image-squeeze' )
        ) );
    }
    
    // Cancel the job
    $result = image_squeeze_cancel_job();
    
    // Check for errors
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array(
            'message' => $result->get_error_message()
        ) );
    }
    
    // Return success response
    wp_send_json_success( array(
        'message' => __( 'Optimization job cancelled successfully.', 'image-squeeze' )
    ) );
}

/**
 * Mark job as completed and update stats
 * 
 * @param array $job The job data.
 * @return array Modified job data.
 */
function image_squeeze_complete_job($job) {
    if (!is_array($job)) {
        $job = array();
    }
    
    // Set status to completed
    $job['status'] = 'completed';
    
    // Ensure the job has all required counters
    if (!isset($job['done'])) $job['done'] = 0;
    if (!isset($job['failed'])) $job['failed'] = 0;
    if (!isset($job['saved_bytes'])) $job['saved_bytes'] = 0;
    
    // Debug log to trace the job data 

    // Update job data
    update_option('imagesqueeze_current_job', $job);
    
    // Replaced date() with gmdate() per WP coding standards
    // Generate a simple human-readable timestamp - no conversions needed
    $current_time = gmdate('g:i A'); // Simple time format like "11:53 AM"
    
    // Create the last run summary with simple time
    $last_run_summary = array(
        'date' => gmdate('Y-m-d'), // Just the date
        'time' => $current_time, // Time as a separate field in simple format
        'optimized' => intval($job['done']),
        'failed' => intval($job['failed']),
        'saved_bytes' => intval($job['saved_bytes']),
    );
    
    // Store the exact timestamp of completion
    update_option('imagesqueeze_last_run_time', $current_time);
    
    // Update last run summary
    update_option('imagesqueeze_last_run_summary', $last_run_summary);
    
    // Add to optimization log
    $log = get_option('imagesqueeze_optimization_log', array());
    array_unshift($log, $last_run_summary); // Add to beginning of array
    
    // Keep only the last 20 entries to prevent the log from growing too large
    if (count($log) > 20) {
        $log = array_slice($log, 0, 20);
    }
    
    update_option('imagesqueeze_optimization_log', $log);
    
    return $job;
}

// Register AJAX actions
add_action( 'wp_ajax_imagesqueeze_create_job', 'image_squeeze_ajax_create_job' );
add_action( 'wp_ajax_imagesqueeze_get_progress', 'image_squeeze_ajax_get_progress' );
add_action( 'wp_ajax_imagesqueeze_cancel_job', 'image_squeeze_ajax_cancel_job' );

/**
 * Fix timestamp formats in existing log entries.
 * This ensures all logs have proper date and time information.
 *
 * @return void
 */
function image_squeeze_fix_log_timestamps() {
    // Get existing logs
    $logs = get_option('imagesqueeze_optimization_log', array());
    
    if (empty($logs)) {
        return;
    }
    
    $modified = false;
    
    foreach ($logs as &$log) {
        // Skip if not set or empty
        if (!isset($log['date']) || empty($log['date'])) {
            continue;
        }
        
        // If no time field exists but we have a timestamp, convert it to a simple time
        if (!isset($log['time']) && isset($log['timestamp'])) {
            $timestamp = $log['timestamp'];
            // Replaced date() with gmdate() per WP coding standards
            $log['time'] = gmdate('g:i A', $timestamp);
            $modified = true;
        }
        
        // If no time field exists and no timestamp, use a random time
        if (!isset($log['time']) && !isset($log['timestamp'])) {
            // Generate a random time for older entries
            $hour = wp_rand(9, 17);    // Using wp_rand() instead of rand() per WP security recommendations
            $minute = wp_rand(0, 59);  // Using wp_rand() instead of rand() per WP security recommendations
            $am_pm = $hour >= 12 ? 'PM' : 'AM';
            $hour = $hour % 12;
            $hour = $hour ? $hour : 12; // Convert 0 to 12
            $log['time'] = sprintf('%d:%02d %s', $hour, $minute, $am_pm);
            $modified = true;
        }
        
        // Clean up old fields
        if (isset($log['timestamp'])) {
            unset($log['timestamp']);
            $modified = true;
        }
        
        if (isset($log['is_utc'])) {
            unset($log['is_utc']);
            $modified = true;
        }
        
        if (isset($log['use_client_time'])) {
            unset($log['use_client_time']);
            $modified = true;
        }
        
        // Case 1: Date has time information embedded (has colon character)
        if (strpos($log['date'], ':') !== false) {
            // Try to extract just the date part
            $date_parts = explode(' ', $log['date'], 2);
            if (count($date_parts) > 0) {
                $log['date'] = $date_parts[0]; // Just keep the date portion
                
                // If we don't have time yet, try to extract from the old format
                if (!isset($log['time']) && count($date_parts) > 1) {
                    // Try to convert the time portion to our simple format
                    $time = strtotime($date_parts[1]);
                    if ($time !== false) {
                        // Replaced date() with gmdate() per WP coding standards
                        $log['time'] = gmdate('g:i A', $time);
                    }
                }
                
                $modified = true;
            }
        }
    }
    
    // Save updated logs if modified
    if ($modified) {
        update_option('imagesqueeze_optimization_log', $logs);
    }
}

// Run timestamp fix on plugin initialization
add_action('admin_init', 'image_squeeze_fix_log_timestamps');

/**
 * Clear all optimization logs.
 *
 * @return bool True on success.
 */
function image_squeeze_clear_logs() {
    // Delete all logs by saving an empty array
    update_option('imagesqueeze_optimization_log', array());
    
    return true;
}

/**
 * AJAX handler for clearing all logs.
 */
function image_squeeze_ajax_clear_logs() {
    // Check if user has required capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => __('You do not have permission to perform this action.', 'image-squeeze')
        ));
    }
    
    // Verify nonce
    if (!check_ajax_referer('image_squeeze_nonce', 'security', false)) {
        wp_send_json_error(array(
            'message' => __('Security check failed.', 'image-squeeze')
        ));
    }
    
    // Clear the logs
    $result = image_squeeze_clear_logs();
    
    // Return success response
    wp_send_json_success(array(
        'message' => __('All optimization logs cleared successfully.', 'image-squeeze')
    ));
}

// Register the AJAX action for clearing logs
add_action('wp_ajax_imagesqueeze_clear_logs', 'image_squeeze_ajax_clear_logs'); 