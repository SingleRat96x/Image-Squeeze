// Add the AJAX handler for getting dashboard stats
add_action('wp_ajax_imagesqueeze_get_dashboard_stats', 'image_squeeze_ajax_get_dashboard_stats');

/**
 * AJAX handler for getting dashboard stats
 */
function image_squeeze_ajax_get_dashboard_stats() {
    // Set proper headers for JSON response
    header('Content-Type: application/json');
    
    try {
        // Check if security parameter exists
        if (!isset($_POST['security'])) {
            // Log this for debugging
            error_log('ImageSqueeze: Security parameter missing in dashboard stats request');
            wp_send_json_error(array(
                'message' => 'Security parameter missing',
                'code' => 'missing_nonce'
            ));
            return;
        }
        
        // Sanitize the nonce
        $nonce = sanitize_text_field(wp_unslash($_POST['security']));
        
        // Verify nonce
        if (!wp_verify_nonce($nonce, 'image_squeeze_nonce')) {
            // Log for debugging
            error_log('ImageSqueeze: Invalid nonce in dashboard stats request: ' . $nonce);
            wp_send_json_error(array(
                'message' => 'Security check failed - invalid nonce',
                'code' => 'invalid_nonce',
            ));
            return;
        }
        
        // Count total images (JPG/PNG)
        $total_images = 0;
        $attachment_counts = wp_count_attachments();
        $jpeg_count = isset($attachment_counts->{'image/jpeg'}) ? (int)$attachment_counts->{'image/jpeg'} : 0;
        $png_count = isset($attachment_counts->{'image/png'}) ? (int)$attachment_counts->{'image/png'} : 0;
        $total_images = $jpeg_count + $png_count;
        
        // Get optimized and failed counts
        $optimized_images = image_squeeze_count_attachments_by_meta('_imagesqueeze_optimized', '1');
        $failed_images = image_squeeze_count_attachments_by_meta('_imagesqueeze_status', 'failed');
        
        // Get total saved bytes
        $total_saved_bytes = get_option('imagesqueeze_total_saved_bytes', 0);
        $total_saved = image_squeeze_format_bytes($total_saved_bytes);
        
        // Get last run summary
        $logs = get_option('imagesqueeze_optimization_log', array());
        $last_run = !empty($logs) ? $logs[0] : null;
        
        // If no logs available, try getting last run summary directly
        if (!$last_run) {
            $last_run = get_option('imagesqueeze_last_run_summary', null);
        }
        
        // Format last run data
        $last_run_saved_bytes = isset($last_run['saved_bytes']) ? intval($last_run['saved_bytes']) : 0;
        $last_run_saved = image_squeeze_format_bytes($last_run_saved_bytes);
        
        $last_run_optimized = isset($last_run['optimized']) ? intval($last_run['optimized']) : 0;
        $last_run_failed = isset($last_run['failed']) ? intval($last_run['failed']) : 0;
        
        // Get and format the last run timestamp
        $last_run_time = get_option('imagesqueeze_last_run_time', '');
        $last_run_date = '';
        
        // Log the optimized count for debugging
        error_log('ImageSqueeze: Last run optimized count: ' . $last_run_optimized);
        error_log('ImageSqueeze: Raw last run data: ' . json_encode($last_run));
        
        if (!empty($last_run_time)) {
            // Debug the raw timestamp value
            error_log('ImageSqueeze: Raw last_run_time from DB: ' . $last_run_time);
            
            // Create a DateTime object to ensure proper timestamp parsing
            $datetime = new DateTime($last_run_time);
            
            // Format with explicit time components
            $last_run_date = date_i18n(
                'F j, Y \a\t g:i a', 
                $datetime->getTimestamp()
            );
            
            error_log('ImageSqueeze: Formatted last_run_date: ' . $last_run_date);
        } elseif (isset($last_run['date']) && !empty($last_run['date'])) {
            // Debug the fallback date value
            error_log('ImageSqueeze: Using fallback date from log: ' . $last_run['date']);
            
            // Create a DateTime object for the fallback date
            $datetime = new DateTime($last_run['date']);
            
            // Format with explicit time components
            $last_run_date = date_i18n(
                'F j, Y \a\t g:i a', 
                $datetime->getTimestamp()
            );

            // Format without time component - simple date only
            $last_run_date = date_i18n(
                'F j, Y', 
                $datetime->getTimestamp()
            );

            // Format with date only (no time)
            $last_run_date = date_i18n(
                'F j, Y', 
                $datetime->getTimestamp()
            );
            
            error_log('ImageSqueeze: Formatted last_run_date: ' . $last_run_date);
        } elseif (isset($last_run['date']) && !empty($last_run['date'])) {
            // Debug the fallback date value
            error_log('ImageSqueeze: Using fallback date from log: ' . $last_run['date']);
            
            // Create a DateTime object for the fallback date
            $datetime = new DateTime($last_run['date']);
            
            // Format without time component - simple date only
            $last_run_date = date_i18n(
                'F j, Y', 
                $datetime->getTimestamp()
            );
            
            error_log('ImageSqueeze: Formatted fallback date: ' . $last_run_date);
        }
        
        // Log successful processing
        error_log('ImageSqueeze: Successfully processed dashboard stats request');
        
        // Return stats as JSON
        wp_send_json_success(array(
            'total_images' => $total_images,
            'optimized_images' => $optimized_images,
            'failed_images' => $failed_images,
            'total_saved' => $total_saved,
            'last_run_saved' => $last_run_saved,
            'last_run_optimized' => $last_run_optimized,
            'last_run_failed' => $last_run_failed,
            'last_run_date' => $last_run_date
        ));
    } catch (Exception $e) {
        error_log('ImageSqueeze: Exception in dashboard stats: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => 'Error getting dashboard stats: ' . $e->getMessage(),
            'code' => 'exception',
            'trace' => $e->getTraceAsString()
        ));
    }
}

/**
 * Helper function to format bytes
 */
function image_squeeze_format_bytes($bytes) {
    if ($bytes >= 1048576) { // 1MB
        return round($bytes / 1048576, 2) . ' MB';
    } else {
        return round($bytes / 1024, 2) . ' KB';
    }
} 