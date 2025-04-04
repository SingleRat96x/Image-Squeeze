<?php
/**
 * Job Manager functionality
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

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
        // Get all unoptimized JPEG/PNG images
        $query = $wpdb->prepare(
            "SELECT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_imagesqueeze_optimized'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type IN ('image/jpeg', 'image/png')
            AND (pm.meta_value IS NULL OR pm.meta_value != '1')
            ORDER BY p.ID DESC"
        );
        
        $image_ids = $wpdb->get_col( $query );
    } elseif ( $type === 'retry' ) {
        // Get all images with failed status
        $query = $wpdb->prepare(
            "SELECT p.ID
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type IN ('image/jpeg', 'image/png')
            AND pm.meta_key = '_imagesqueeze_status'
            AND pm.meta_value = 'failed'
            ORDER BY p.ID DESC"
        );
        
        $image_ids = $wpdb->get_col( $query );
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
 * @param string $type Job type ('full' or 'retry').
 * @return void
 */
function image_squeeze_log_completed_job( $type ) {
    global $wpdb;
    
    // Count optimized images
    $optimized_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*)
        FROM {$wpdb->postmeta}
        WHERE meta_key = '_imagesqueeze_optimized'
        AND meta_value = '1'"
    ) );
    
    // Count failed images
    $failed_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*)
        FROM {$wpdb->postmeta}
        WHERE meta_key = '_imagesqueeze_status'
        AND meta_value = 'failed'"
    ) );
    
    // Get saved bytes from global variable
    $saved_bytes = isset($GLOBALS['imagesqueeze_job_saved_bytes']) ? intval($GLOBALS['imagesqueeze_job_saved_bytes']) : 0;
    
    // Create log entry
    $log_entry = array(
        'date'      => date( 'Y-m-d' ),
        'job_type'  => $type,
        'optimized' => (int) $optimized_count,
        'failed'    => (int) $failed_count,
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
        'date' => date( 'Y-m-d' ),
        'job_type' => $type,
        'optimized' => (int) $optimized_count,
        'failed' => (int) $failed_count,
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
            image_squeeze_log_completed_job( $current_job['type'] );
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

// Register AJAX actions
add_action( 'wp_ajax_imagesqueeze_create_job', 'image_squeeze_ajax_create_job' );
add_action( 'wp_ajax_imagesqueeze_get_progress', 'image_squeeze_ajax_get_progress' ); 