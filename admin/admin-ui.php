<?php
/**
 * Admin UI functionality
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

// Include settings UI
require_once plugin_dir_path(__FILE__) . 'settings-ui.php';

// Include job manager for helper functions
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/job-manager.php';

/**
 * Register admin menu and pages.
 */
function medshi_imsqz_register_admin_menu() {
    add_menu_page(
        'Image Squeeze',
        'Image Squeeze',
        'manage_options',
        'image-squeeze',
        'medshi_imsqz_render_admin_page',
        'dashicons-format-image',
        80
    );
}
add_action('admin_menu', 'medshi_imsqz_register_admin_menu');

/**
 * Register and enqueue admin assets.
 * 
 * @param string $hook The current admin page.
 */
function medshi_imsqz_enqueue_admin_assets($hook) {
    // Only load assets on our plugin's page
    if (strpos($hook, 'page_image-squeeze') === false) {
        return;
    }
    
    // Enqueue CSS
    wp_enqueue_style(
        'image-squeeze-admin',
        MEDSHI_IMSQZ_URL . 'assets/css/admin.css',
        array(),
        MEDSHI_IMSQZ_VERSION
    );
    
    // Enqueue JS with jQuery dependency
    wp_enqueue_script(
        'image-squeeze-admin',
        MEDSHI_IMSQZ_URL . 'assets/js/admin.js',
        array('jquery'),
        MEDSHI_IMSQZ_VERSION,
        true
    );
    
    // Create a fresh nonce for AJAX security
    $ajax_nonce = wp_create_nonce('image_squeeze_nonce');
    
    // Add nonce and other data for AJAX
    wp_localize_script(
        'image-squeeze-admin',
        'imageSqueeze',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => $ajax_nonce,
            'strings' => array(
                'optimizationComplete' => __('Optimization complete!', 'imagesqueeze'),
                'optimizationSuccess' => __('Optimization completed successfully!', 'imagesqueeze'),
                /* translators: %1$d is the number of processed images, %2$d is the total number of images */
                'processing' => __('Processing %1$d of %2$d images', 'imagesqueeze'),
                /* translators: %1$d is the percentage of completion */
                'percentComplete' => __('Progress: %1$d percent complete', 'imagesqueeze'),
                'scanningForOrphaned' => __('Scanning for orphaned WebP files...', 'imagesqueeze'),
                'cleanupComplete' => __('Cleanup complete!', 'imagesqueeze'),
                'errorOccurred' => __('An error occurred. Please try again.', 'imagesqueeze'),
                'optimizationCancelled' => __('Optimization cancelled.', 'imagesqueeze'),
                'confirmClearLogs' => __('Are you sure you want to clear all optimization logs? This action cannot be undone.', 'imagesqueeze'),
                'noLogsMatch' => __('No logs match your current filter settings.', 'imagesqueeze'),
                'orphanedFilesRemoved' => __('orphaned WebP files have been removed.', 'imagesqueeze'),
                'filesRemoved' => __('Files Removed:', 'imagesqueeze'),
                'showingFirstTen' => __('Only showing first 10 files for brevity.', 'imagesqueeze'),
                'allClean' => __('All Clean!', 'imagesqueeze'),
                'noOrphanedFiles' => __('No orphaned WebP files were found. Your media library is clean!', 'imagesqueeze')
            )
        )
    );
    
    // Get current tab with nonce verification
    $tab_nonce_verified = false;
    if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'imagesqueeze_tab_nonce')) {
        $tab_nonce_verified = true;
    }
    
    // Get current tab (only use tab parameter if nonce is verified, otherwise default to dashboard)
    $current_tab = ($tab_nonce_verified && isset($_GET['tab'])) ? sanitize_key($_GET['tab']) : 'dashboard';
    
    // Enqueue logs-ui.js only on the logs tab
    if ($current_tab === 'logs') {
        wp_enqueue_script(
            'image-squeeze-logs-ui',
            MEDSHI_IMSQZ_URL . 'assets/js/logs-ui.js',
            array('image-squeeze-admin'),
            MEDSHI_IMSQZ_VERSION,
            true
        );
    }
    
    // Enqueue cleanup-ui.js only on the cleanup tab
    if ($current_tab === 'cleanup') {
        wp_enqueue_script(
            'image-squeeze-cleanup-ui',
            MEDSHI_IMSQZ_URL . 'assets/js/cleanup-ui.js',
            array('jquery', 'image-squeeze-admin'),
            MEDSHI_IMSQZ_VERSION,
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'medshi_imsqz_enqueue_admin_assets');

/**
 * Render admin page with tabs.
 */
function medshi_imsqz_render_admin_page() {
    // Verify request is valid
    $verified = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'image_squeeze_admin_page') : false;
    
    // Get current tab with nonce verification
    $tab_nonce_verified = false;
    if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'imagesqueeze_tab_nonce')) {
        $tab_nonce_verified = true;
    }
    
    // Get current tab (only use tab parameter if nonce is verified, otherwise default to dashboard)
    $current_tab = ($tab_nonce_verified && isset($_GET['tab'])) ? sanitize_key($_GET['tab']) : 'dashboard';
    
    // Define available tabs
    $tabs = array(
        'dashboard' => __('Dashboard', 'imagesqueeze'),
        'logs' => __('Logs', 'imagesqueeze'),
        'settings' => __('Settings', 'imagesqueeze'),
        'cleanup' => __('Cleanup', 'imagesqueeze'),
    );
    
    // Start admin page container
    ?>
    <div class="wrap image-squeeze-admin">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-format-gallery" style="font-size: 26px; width: 26px; height: 26px; margin-right: 10px; vertical-align: text-top;"></span>
            <?php echo esc_html__('Image Squeeze', 'imagesqueeze'); ?>
        </h1>
        
        <h2 class="nav-tab-wrapper">
            <?php
            // Output tabs
            foreach ($tabs as $tab_id => $tab_name) {
                $active_class = ($current_tab === $tab_id) ? 'nav-tab-active' : '';
                $tab_nonce = wp_create_nonce('imagesqueeze_tab_nonce');
                $tab_url = add_query_arg(
                    array(
                        'page' => 'image-squeeze', 
                        'tab' => $tab_id,
                        '_wpnonce' => $tab_nonce
                    ), 
                    admin_url('admin.php')
                );
                printf(
                    '<a href="%s" class="nav-tab %s" data-tab="%s">%s</a>',
                    esc_url($tab_url),
                    esc_attr($active_class),
                    esc_attr($tab_id),
                    esc_html($tab_name)
                );
            }
            ?>
        </h2>
        
        <div class="tab-content">
            <?php
            // Include the appropriate tab content based on current tab
            switch ($current_tab) {
                case 'dashboard':
                    ?>
                    <div id="tab-dashboard" class="tab-pane">
                        <?php medshi_imsqz_render_dashboard_tab(); ?>
                    </div>
                    <?php
                    break;
                    
                case 'logs':
                    ?>
                    <div id="tab-logs" class="tab-pane">
                        <?php medshi_imsqz_render_logs_tab(); ?>
                    </div>
                    <?php
                    break;
                    
                case 'settings':
                    ?>
                    <div id="tab-settings" class="tab-pane">
                        <?php medshi_imsqz_render_settings_tab(); ?>
                    </div>
                    <?php
                    break;
                    
                case 'cleanup':
                    ?>
                    <div id="tab-cleanup" class="tab-pane">
                        <?php medshi_imsqz_render_cleanup_tab(); ?>
                    </div>
                    <?php
                    break;
                    
                default:
                    ?>
                    <div id="tab-dashboard" class="tab-pane">
                        <?php medshi_imsqz_render_dashboard_tab(); ?>
                    </div>
                    <?php
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Render Dashboard tab content.
 */
function medshi_imsqz_render_dashboard_tab() {
    // Count total images (JPG/PNG)
    try {
        // Check for cached value first
        $cache_key = 'imagesqueeze_total_images_count';
        $total_images = wp_cache_get($cache_key);
        
        if (false === $total_images) {
            // Get attachment counts by mime type
            $attachment_counts = wp_count_attachments();
            
            // Count JPEG and PNG images
            $jpeg_count = isset($attachment_counts->{'image/jpeg'}) ? (int)$attachment_counts->{'image/jpeg'} : 0;
            $png_count = isset($attachment_counts->{'image/png'}) ? (int)$attachment_counts->{'image/png'} : 0;
            
            // Total of JPEG and PNG images
            $total_images = $jpeg_count + $png_count;
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $total_images, '', 300);
        }
    } catch (Exception $e) {
        $total_images = 'Error: ' . $e->getMessage();
    }
    
    // Count optimized images
    try {
        // Check for cached value first
        $cache_key = 'imagesqueeze_optimized_images_count';
        $optimized_images = wp_cache_get($cache_key);
        
        if (false === $optimized_images) {
            $optimized_images = medshi_imsqz_count_attachments_by_meta('_imagesqueeze_optimized', '1');
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $optimized_images, '', 300);
        }
    } catch (Exception $e) {
        $optimized_images = 'Error: ' . $e->getMessage();
    }
    
    // Count failed images
    try {
        // Check for cached value first
        $cache_key = 'imagesqueeze_failed_images_count';
        $failed_images = wp_cache_get($cache_key);
        
        if (false === $failed_images) {
            $failed_images = medshi_imsqz_count_attachments_by_meta('_imagesqueeze_status', 'failed');
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $failed_images, '', 300);
        }
    } catch (Exception $e) {
        $failed_images = 'Error: ' . $e->getMessage();
    }
    
    // Get last optimization log
    $logs = get_option('imagesqueeze_optimization_log', array());
    $last_run = !empty($logs) ? $logs[0] : null;
    
    // If no logs available, try getting last run summary directly
    if (!$last_run) {
        $last_run = get_option('imagesqueeze_last_run_summary', null);
    }
    
    // Safely initialize last run values to prevent undefined array key warnings
    $last_run_optimized = isset($last_run['optimized']) ? intval($last_run['optimized']) : 0;
    $last_run_failed = isset($last_run['failed']) ? intval($last_run['failed']) : 0;
    $last_run_date = isset($last_run['date']) ? $last_run['date'] : '';
    $last_run_saved_bytes = isset($last_run['saved_bytes']) ? intval($last_run['saved_bytes']) : 0;
    
    // Get current job
    $current_job = get_option('imagesqueeze_current_job', array());
    $job_in_progress = !empty($current_job) && isset($current_job['status']) && $current_job['status'] === 'in_progress';
    
    // Get total saved bytes
    $total_saved_bytes = get_option('imagesqueeze_total_saved_bytes', 0);
    
    // Helper function to format bytes
    function medshi_imsqz_format_bytes($bytes) {
        if ($bytes >= 1048576) { // 1MB
            return round($bytes / 1048576, 2) . ' MB';
        } else {
            return round($bytes / 1024, 2) . ' KB';
        }
    }
    
    ?>
    <div class="image-squeeze-dashboard">
        <h2 class="imagesqueeze-section-title">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php echo esc_html__('Image Optimization Overview', 'imagesqueeze'); ?>
        </h2>
        
        <!-- Stats Cards -->
        <div class="imagesqueeze-stats-grid">
            <!-- Total Images -->
            <div class="imagesqueeze-stat-card">
                <div class="imagesqueeze-stat-icon">
                    <span class="dashicons dashicons-format-image"></span>
                </div>
                <div class="imagesqueeze-stat-title">
                    <?php echo esc_html__('Total Images', 'imagesqueeze'); ?>
                </div>
                <div class="imagesqueeze-stat-value">
                    <?php echo esc_html($total_images); ?>
                </div>
                <div class="imagesqueeze-stat-subtext">
                    <?php echo esc_html__('in Media Library', 'imagesqueeze'); ?>
                </div>
            </div>
            
            <!-- Optimized Images -->
            <div class="imagesqueeze-stat-card">
                <div class="imagesqueeze-stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="imagesqueeze-stat-title">
                    <?php echo esc_html__('Optimized', 'imagesqueeze'); ?>
                </div>
                <div class="imagesqueeze-stat-value">
                    <?php echo esc_html($optimized_images); ?>
                </div>
                <div class="imagesqueeze-stat-subtext">
                    <?php echo esc_html__('Compressed Images', 'imagesqueeze'); ?>
                </div>
            </div>
            
            <!-- Failed Images -->
            <div class="imagesqueeze-stat-card <?php echo ($failed_images > 0) ? 'imagesqueeze-stat-warning' : ''; ?>">
                <div class="imagesqueeze-stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="imagesqueeze-stat-title">
                    <?php echo esc_html__('Failed', 'imagesqueeze'); ?>
                </div>
                <div class="imagesqueeze-stat-value">
                    <?php echo esc_html($failed_images); ?>
                </div>
                <div class="imagesqueeze-stat-subtext">
                    <?php echo esc_html__('Optimization Issues', 'imagesqueeze'); ?>
                </div>
            </div>
            
            <!-- Space Saved Globally -->
            <div class="imagesqueeze-stat-card">
                <div class="imagesqueeze-stat-icon">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <div class="imagesqueeze-stat-title">
                    <?php echo esc_html__('Space Saved', 'imagesqueeze'); ?>
                </div>
                <div class="imagesqueeze-stat-value">
                    <?php echo esc_html(medshi_imsqz_format_bytes($total_saved_bytes)); ?>
                </div>
                <div class="imagesqueeze-stat-subtext">
                    <?php echo esc_html__('Total Disk Space Saved', 'imagesqueeze'); ?>
                </div>
            </div>
            
            <!-- Space Saved Last Run -->
            <div class="imagesqueeze-stat-card">
                <div class="imagesqueeze-stat-icon">
                    <span class="dashicons dashicons-backup"></span>
                </div>
                <div class="imagesqueeze-stat-title">
                    <?php echo esc_html__('Last Run Savings', 'imagesqueeze'); ?>
                </div>
                <div class="imagesqueeze-stat-value">
                    <?php echo esc_html(medshi_imsqz_format_bytes($last_run_saved_bytes)); ?>
                </div>
                <div class="imagesqueeze-stat-subtext">
                    <?php echo esc_html__('Last Optimization Run', 'imagesqueeze'); ?>
                </div>
            </div>
        </div>
        
        <!-- Last Optimization Summary -->
        <div class="imagesqueeze-summary-card">
            <div class="imagesqueeze-summary-header">
                <span class="dashicons dashicons-clock"></span>
                <h2><?php echo esc_html__('Last Optimization Summary', 'imagesqueeze'); ?></h2>
            </div>
            
            <div class="imagesqueeze-summary-content">
                <?php if ($last_run): ?>
                    <div class="imagesqueeze-summary-grid">
                        <!-- Last Run -->
                        <div class="imagesqueeze-summary-stat-card">
                            <div class="summary-icon">
                                <span class="dashicons dashicons-calendar-alt"></span>
                            </div>
                            <div class="summary-info">
                                <span class="summary-label"><?php echo esc_html__('Last Run', 'imagesqueeze'); ?></span>
                                <span class="summary-value">
                                    <?php 
                                    // Get the last run time from options
                                    $last_run_time = get_option('imagesqueeze_last_run_time', '');
                                    
                                    if (!empty($last_run_time)) {
                                        // Create a DateTime object to ensure proper timestamp parsing
                                        $datetime = new DateTime($last_run_time);
                                        
                                        // Format with explicit time components
                                        echo esc_html(date_i18n('F j, Y \a\t g:i a', $datetime->getTimestamp()));
                                    } elseif (!empty($last_run_date)) {
                                        // Create a DateTime object for the fallback date
                                        $datetime = new DateTime($last_run_date);
                                        
                                        // Format without time component (date only)
                                        echo esc_html(date_i18n('F j, Y', $datetime->getTimestamp()));
                                    } else {
                                        echo esc_html__('Never', 'imagesqueeze');
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Optimized Images -->
                        <div class="imagesqueeze-summary-stat-card">
                            <div class="summary-icon success">
                                <span class="dashicons dashicons-yes-alt"></span>
                            </div>
                            <div class="summary-info">
                                <span class="summary-label"><?php echo esc_html__('Optimized Images', 'imagesqueeze'); ?></span>
                                <span class="summary-value"><?php echo esc_html($last_run_optimized); ?></span>
                                
                                <?php if ($last_run_failed > 0): ?>
                                <span class="summary-value warning">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php echo esc_html__('Failed:', 'imagesqueeze'); ?> 
                                    <strong><?php echo esc_html($last_run_failed); ?></strong>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Space Saved -->
                        <div class="imagesqueeze-summary-stat-card">
                            <div class="summary-icon">
                                <span class="dashicons dashicons-database"></span>
                            </div>
                            <div class="summary-info">
                                <span class="summary-label"><?php echo esc_html__('Space Saved', 'imagesqueeze'); ?></span>
                                <span class="summary-value"><?php echo esc_html(medshi_imsqz_format_bytes($last_run_saved_bytes)); ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="imagesqueeze-no-summary">
                        <span class="dashicons dashicons-info"></span>
                        <p><?php echo esc_html__('No optimization jobs have been run yet.', 'imagesqueeze'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Action Buttons and Progress -->
        <div class="imagesqueeze-actions-card">
            <div class="imagesqueeze-card-header">
                <span class="dashicons dashicons-controls-play"></span>
                <h2><?php echo esc_html__('Optimization Actions', 'imagesqueeze'); ?></h2>
            </div>
            
            <div class="imagesqueeze-card-content">
                <div class="imagesqueeze-actions-container">
                    <div class="imagesqueeze-actions-top-row">
                        <?php if ($job_in_progress): ?>
                            <div class="imagesqueeze-status-badge in-progress">
                                <span class="dashicons dashicons-update"></span>
                                <?php echo esc_html__('Optimizing...', 'imagesqueeze'); ?>
                            </div>
                        <?php else: ?>
                            <div class="imagesqueeze-status-badge idle">
                                <span class="dashicons dashicons-marker"></span>
                                <?php echo esc_html__('Ready for Optimization', 'imagesqueeze'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="imagesqueeze-action-buttons">
                            <button id="optimize-images" class="button button-primary" <?php echo $job_in_progress ? 'disabled' : ''; ?>>
                                <span class="dashicons dashicons-update"></span>
                                <?php echo esc_html__('Optimize Images Now', 'imagesqueeze'); ?>
                            </button>
                            
                            <?php if ($job_in_progress): ?>
                                <button id="cancel-optimization" class="button button-secondary">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php echo esc_html__('Cancel Optimization', 'imagesqueeze'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($failed_images > 0): ?>
                                <button id="retry-failed-images" class="button button-secondary" <?php echo $job_in_progress ? 'disabled' : ''; ?>>
                                    <span class="dashicons dashicons-controls-repeat"></span>
                                    <?php echo esc_html__('Retry Failed Images', 'imagesqueeze'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Progress Container with Spinner -->
                    <div id="progress-container" class="imagesqueeze-progress-container" style="<?php echo $job_in_progress ? '' : 'display: none;'; ?>">
                        <div class="imagesqueeze-progress-with-spinner">
                            <div class="imagesqueeze-spinner">
                                <span class="dashicons dashicons-update"></span>
                            </div>
                            
                            <div class="imagesqueeze-progress-details">
                                <?php if ($job_in_progress && isset($current_job['done']) && isset($current_job['total']) && $current_job['total'] > 0): ?>
                                    <div class="imagesqueeze-progress-label">
                                        <?php 
                                        $done = intval($current_job['done']);
                                        $total = intval($current_job['total']);
                                        printf(
                                            /* translators: %1$d is the number of processed images, %2$d is the total number of images */
                                            esc_html__('Compressed %1$d of %2$d images', 'imagesqueeze'),
                                            esc_html($done),
                                            esc_html($total)
                                        );
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div id="progress-text" class="imagesqueeze-progress-text" aria-live="polite">
                                    <?php 
                                    if ($job_in_progress && isset($current_job['done']) && isset($current_job['total']) && $current_job['total'] > 0) {
                                        $done = intval($current_job['done']);
                                        $total = intval($current_job['total']);
                                        $percent = $total > 0 ? round(($done / $total) * 100) : 0;
                                        echo sprintf(
                                            /* translators: %1$d is the percentage complete */
                                            esc_html__('%1$d%% complete', 'imagesqueeze'),
                                            esc_html($percent)
                                        );
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- For screen readers -->
                        <span id="progress-screen-reader-text" class="screen-reader-text"></span>
                    </div>
                    
                    <!-- Success/Failure Message (Hidden by default, shown by JS) -->
                    <div id="optimization-status" class="imagesqueeze-final-status" style="display: none;">
                        <span class="dashicons dashicons-yes"></span>
                        <span id="optimization-status-text"><?php echo esc_html__('Optimization complete!', 'imagesqueeze'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Logs tab content.
 */
function medshi_imsqz_render_logs_tab() {
    // Path to the logs partial
    $logs_partial = plugin_dir_path(__FILE__) . 'partials/logs-ui.php';
    
    // Check if file exists
    if (file_exists($logs_partial)) {
        include_once $logs_partial;
    } else {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Logs UI partial file not found.', 'imagesqueeze');
        echo '</p></div>';
    }
}

/**
 * Render Settings tab content.
 */
function medshi_imsqz_render_settings_tab() {
    // Call the settings UI function
    medshi_imsqz_settings_ui();
}

/**
 * Render Cleanup tab content.
 */
function medshi_imsqz_render_cleanup_tab() {
    // Path to the cleanup partial
    $cleanup_partial = plugin_dir_path(__FILE__) . 'partials/cleanup-ui.php';
    
    // Check if file exists
    if (file_exists($cleanup_partial)) {
        include_once $cleanup_partial;
    } else {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Cleanup UI partial file not found.', 'imagesqueeze');
        echo '</p></div>';
    }
} 