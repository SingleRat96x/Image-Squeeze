<?php
/**
 * Job Runner functionality
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

/**
 * Process a batch of images from the job queue.
 *
 * @param int $batch_size Number of images to process in this batch.
 * @return array Status information about the batch processing.
 */
function image_squeeze_process_batch( $batch_size = 10 ) {
    // Load the job queue
    $queue = get_option( 'imagesqueeze_job_queue', array() );
    
    // Load the current job
    $current_job = get_option( 'imagesqueeze_current_job', array() );
    
    // Check if we have an active job
    if ( empty( $current_job ) || empty( $queue ) || empty( $current_job['status'] ) || $current_job['status'] !== 'in_progress' ) {
        return array(
            'error' => true,
            'message' => __( 'No active job found.', 'image-squeeze' ),
        );
    }
    
    // Calculate how many images to process in this batch
    $to_process = min( $batch_size, count( $queue ) );
    
    // Get the IDs for this batch
    $batch_ids = array_slice( $queue, 0, $to_process );
    
    $processed_count = 0;
    
    // Process each image in the batch
    foreach ( $batch_ids as $attachment_id ) {
        // Process the image
        $result = image_squeeze_process_image( $attachment_id );
        
        // Increment counters based on result
        $processed_count++;
        
        // Track success/failure counts separately
        if (is_wp_error($result)) {
            // If it failed, increment the failed counter
            $current_job['failed'] = isset($current_job['failed']) ? $current_job['failed'] + 1 : 1;
        } else {
            // If it succeeded, increment the done counter
            $current_job['done'] = isset($current_job['done']) ? $current_job['done'] + 1 : 1;
        }
        
        // Make sure we always have all the fields needed for logs
        if (!isset($current_job['done'])) $current_job['done'] = 0;
        if (!isset($current_job['failed'])) $current_job['failed'] = 0;
    }
    
    // Remove processed IDs from the queue
    $queue = array_slice( $queue, $to_process );
    
    // Update job status if queue is now empty
    if ( empty( $queue ) ) {
        $current_job['status'] = 'completed';
        $current_job['cleanup_on_next_visit'] = true;
        
        // Log the completed job if we have a job type
        if ( isset( $current_job['type'] ) && function_exists( 'image_squeeze_log_completed_job' ) ) {
            // Force log the counts to debug
            error_log('JOB COMPLETE - Passing data to log function: done=' . $current_job['done'] . ' failed=' . $current_job['failed']);
            
            // Pass the entire job object instead of just the type
            image_squeeze_log_completed_job( $current_job );
        }
    }
    
    // Save updated queue and job state
    update_option( 'imagesqueeze_job_queue', $queue, false );
    update_option( 'imagesqueeze_current_job', $current_job, false );
    
    // Return status information
    return array(
        'success' => true,
        'done' => $processed_count,
        'remaining' => count( $queue ),
        'status' => $current_job['status'],
    );
}

/**
 * AJAX handler for processing a batch of images.
 */
function image_squeeze_ajax_process_batch() {
    // Check if user has required capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array(
            'message' => __( 'You do not have permission to perform this action.', 'image-squeeze' ),
        ) );
    }
    
    // Verify nonce
    if ( ! check_ajax_referer( 'image_squeeze_nonce', 'security', false ) ) {
        wp_send_json_error( array(
            'message' => __( 'Security check failed.', 'image-squeeze' ),
        ) );
    }
    
    // Get batch size (optional)
    $batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 10;
    
    // Make sure batch size is reasonable
    if ( $batch_size < 1 ) {
        $batch_size = 1;
    } elseif ( $batch_size > 50 ) {
        $batch_size = 50;
    }
    
    // Process the batch
    $result = image_squeeze_process_batch( $batch_size );
    
    // Check for errors
    if ( isset( $result['error'] ) && $result['error'] ) {
        wp_send_json_error( array(
            'message' => $result['message'],
        ) );
    }
    
    // Return success response
    wp_send_json_success( array(
        'done' => $result['done'],
        'remaining' => $result['remaining'],
        'status' => $result['status'],
    ) );
}

// Register AJAX action
add_action( 'wp_ajax_imagesqueeze_process_batch', 'image_squeeze_ajax_process_batch' ); 