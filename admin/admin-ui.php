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

/**
 * Register admin menu and pages.
 */
function image_squeeze_register_admin_menu() {
    add_menu_page(
        'Image Squeeze',
        'Image Squeeze',
        'manage_options',
        'image-squeeze',
        'image_squeeze_render_admin_page',
        'dashicons-format-image',
        80
    );
}
add_action('admin_menu', 'image_squeeze_register_admin_menu');

/**
 * Register and enqueue admin assets.
 * 
 * @param string $hook The current admin page.
 */
function image_squeeze_enqueue_admin_assets($hook) {
    // Only load assets on our plugin's page
    if (strpos($hook, 'page_image-squeeze') === false) {
        return;
    }
    
    // Enqueue CSS
    wp_enqueue_style(
        'image-squeeze-admin',
        IMAGESQUEEZE_URL . 'assets/css/admin.css',
        array(),
        IMAGESQUEEZE_VERSION
    );
    
    // Enqueue JS with jQuery dependency
    wp_enqueue_script(
        'image-squeeze-admin',
        IMAGESQUEEZE_URL . 'assets/js/admin.js',
        array('jquery'),
        IMAGESQUEEZE_VERSION,
        true
    );
    
    // Add nonce and other data for AJAX
    wp_localize_script(
        'image-squeeze-admin',
        'imageSqueeze',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('image_squeeze_nonce')
        )
    );
}
add_action('admin_enqueue_scripts', 'image_squeeze_enqueue_admin_assets');

/**
 * Render admin page with tabs.
 */
function image_squeeze_render_admin_page() {
    // Get current tab, default to dashboard
    $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
    
    // Define available tabs
    $tabs = array(
        'dashboard' => __('Dashboard', 'image-squeeze'),
        'logs' => __('Logs', 'image-squeeze'),
        'settings' => __('Settings', 'image-squeeze'),
        'cleanup' => __('Cleanup', 'image-squeeze'),
    );
    
    // Start admin page container
    ?>
    <div class="wrap image-squeeze-admin">
        <h1><?php echo esc_html__('Image Squeeze', 'image-squeeze'); ?></h1>
        
        <!-- Debug message at the top -->
        <div class="notice notice-info">
            <p>Debug: Current tab is '<?php echo esc_html($current_tab); ?>'</p>
        </div>
        
        <h2 class="nav-tab-wrapper">
            <?php
            // Output tabs
            foreach ($tabs as $tab_id => $tab_name) {
                $active_class = ($current_tab === $tab_id) ? 'nav-tab-active' : '';
                $tab_url = add_query_arg(array('page' => 'image-squeeze', 'tab' => $tab_id), admin_url('admin.php'));
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
                        <?php image_squeeze_render_dashboard_tab(); ?>
                    </div>
                    <?php
                    break;
                    
                case 'logs':
                    ?>
                    <div id="tab-logs" class="tab-pane">
                        <?php image_squeeze_render_logs_tab(); ?>
                    </div>
                    <?php
                    break;
                    
                case 'settings':
                    ?>
                    <div id="tab-settings" class="tab-pane">
                        <?php image_squeeze_render_settings_tab(); ?>
                    </div>
                    <?php
                    break;
                    
                case 'cleanup':
                    ?>
                    <div id="tab-cleanup" class="tab-pane">
                        <?php image_squeeze_render_cleanup_tab(); ?>
                    </div>
                    <?php
                    break;
                    
                default:
                    ?>
                    <div id="tab-dashboard" class="tab-pane">
                        <?php image_squeeze_render_dashboard_tab(); ?>
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
function image_squeeze_render_dashboard_tab() {
    echo '<p>Dashboard render function loaded</p>'; // Debug line
    
    // Get stats from the database
    global $wpdb;
    
    // Count total images (JPG/PNG)
    try {
        $total_images = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND (post_mime_type = 'image/jpeg' OR post_mime_type = 'image/png')"
        );
        $total_images = $total_images ? intval($total_images) : 0;
    } catch (Exception $e) {
        $total_images = 'Error: ' . $e->getMessage();
    }
    
    // Count optimized images
    try {
        $optimized_images = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_imagesqueeze_optimized'
            AND meta_value = '1'"
        );
        $optimized_images = $optimized_images ? intval($optimized_images) : 0;
    } catch (Exception $e) {
        $optimized_images = 'Error: ' . $e->getMessage();
    }
    
    // Count failed images
    try {
        $failed_images = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_imagesqueeze_status'
            AND meta_value = 'failed'"
        );
        $failed_images = $failed_images ? intval($failed_images) : 0;
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
    
    // Get current job
    $current_job = get_option('imagesqueeze_current_job', array());
    $job_in_progress = !empty($current_job) && isset($current_job['status']) && $current_job['status'] === 'in_progress';
    
    // Get total saved bytes
    $total_saved_bytes = get_option('imagesqueeze_total_saved_bytes', 0);
    
    // Get last run saved bytes
    $last_run_saved_bytes = isset($last_run['saved_bytes']) ? intval($last_run['saved_bytes']) : 0;
    
    // Helper function to format bytes
    function image_squeeze_format_bytes($bytes) {
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
            <?php echo esc_html__('Image Optimization Overview', 'image-squeeze'); ?>
        </h2>
        
        <!-- Stats Cards -->
        <div class="image-squeeze-stats">
            <div class="imagesqueeze-stat-card">
                <div class="stat-header">
                    <span class="dashicons dashicons-format-image"></span>
                    <span class="stat-title"><?php echo esc_html__('Total Images', 'image-squeeze'); ?></span>
                </div>
                <span class="stat-label"><?php echo esc_html__('Images in Media Library', 'image-squeeze'); ?></span>
                <span class="stat-value"><?php echo esc_html($total_images); ?></span>
            </div>
            
            <div class="imagesqueeze-stat-card">
                <div class="stat-header">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <span class="stat-title"><?php echo esc_html__('Optimized', 'image-squeeze'); ?></span>
                </div>
                <span class="stat-label"><?php echo esc_html__('Compressed Images', 'image-squeeze'); ?></span>
                <span class="stat-value"><?php echo esc_html($optimized_images); ?></span>
            </div>
            
            <div class="imagesqueeze-stat-card <?php echo ($failed_images > 0) ? 'warning' : ''; ?>">
                <div class="stat-header">
                    <span class="dashicons dashicons-warning"></span>
                    <span class="stat-title"><?php echo esc_html__('Failed', 'image-squeeze'); ?></span>
                </div>
                <span class="stat-label"><?php echo esc_html__('Optimization Issues', 'image-squeeze'); ?></span>
                <span class="stat-value"><?php echo esc_html($failed_images); ?></span>
            </div>
            
            <div class="imagesqueeze-stat-card">
                <div class="stat-header">
                    <span class="dashicons dashicons-database"></span>
                    <span class="stat-title"><?php echo esc_html__('Space Saved Globally', 'image-squeeze'); ?></span>
                </div>
                <span class="stat-label"><?php echo esc_html__('Total Disk Space Saved', 'image-squeeze'); ?></span>
                <span class="stat-value"><?php echo esc_html(image_squeeze_format_bytes($total_saved_bytes)); ?></span>
            </div>
            
            <div class="imagesqueeze-stat-card">
                <div class="stat-header">
                    <span class="dashicons dashicons-backup"></span>
                    <span class="stat-title"><?php echo esc_html__('Space Saved (Last Run)', 'image-squeeze'); ?></span>
                </div>
                <span class="stat-label"><?php echo esc_html__('Last Optimization Run', 'image-squeeze'); ?></span>
                <span class="stat-value"><?php echo esc_html(image_squeeze_format_bytes($last_run_saved_bytes)); ?></span>
            </div>
        </div>
        
        <!-- Last Run Summary -->
        <div class="postbox">
            <h2 class="hndle">
                <span class="dashicons dashicons-clock"></span>
                <span><?php echo esc_html__('Last Optimization Summary', 'image-squeeze'); ?></span>
            </h2>
            <div class="inside">
                <?php if ($last_run): ?>
                    <p>
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php 
                        printf(
                            esc_html__('Last run on %1$s', 'image-squeeze'),
                            esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_run['date'])))
                        ); 
                        ?>
                    </p>
                    <p>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php echo esc_html__('Optimized:', 'image-squeeze'); ?> <strong><?php echo intval($last_run['optimized']); ?></strong>
                        
                        <?php if (isset($last_run['failed']) && $last_run['failed'] > 0): ?>
                            <span style="margin-left: 15px;"><span class="dashicons dashicons-no-alt"></span>
                            <?php echo esc_html__('Failed:', 'image-squeeze'); ?> <strong><?php echo intval($last_run['failed']); ?></strong></span>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p><?php echo esc_html__('No optimization jobs have been run yet.', 'image-squeeze'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="postbox">
            <h2 class="hndle">
                <span class="dashicons dashicons-controls-play"></span>
                <span><?php echo esc_html__('Optimization Actions', 'image-squeeze'); ?></span>
            </h2>
            <div class="inside">
                <?php if ($job_in_progress): ?>
                    <div class="notice notice-info">
                        <p><?php echo esc_html__('An optimization job is currently in progress.', 'image-squeeze'); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="imagesqueeze-actions-card">
                    <button id="optimize-images" class="button button-primary" <?php echo $job_in_progress ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html__('Start Optimization', 'image-squeeze'); ?>
                    </button>
                    
                    <?php if ($failed_images > 0): ?>
                        <button id="retry-failed-images" class="button" <?php echo $job_in_progress ? 'disabled' : ''; ?>>
                            <span class="dashicons dashicons-controls-repeat"></span>
                            <?php echo esc_html__('Retry Failed Images', 'image-squeeze'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Progress Bar -->
        <div class="postbox" id="progress-container" style="<?php echo $job_in_progress ? '' : 'display: none;'; ?>">
            <h2 class="hndle">
                <span class="dashicons dashicons-performance"></span>
                <span><?php echo esc_html__('Optimization Progress', 'image-squeeze'); ?></span>
            </h2>
            <div class="inside">
                <div class="image-squeeze-progress">
                    <p id="progress-text" class="imagesqueeze-progress-label">
                        <span class="dashicons dashicons-update"></span>
                        <?php 
                        if ($job_in_progress) {
                            printf(
                                esc_html__('Processing %1$d of %2$d images', 'image-squeeze'),
                                intval($current_job['done']),
                                intval($current_job['total'])
                            );
                        } else {
                            echo esc_html__('Progress: 0 of 0 images', 'image-squeeze');
                        }
                        ?>
                    </p>
                    <div class="progress-bar-container">
                        <div id="progress-bar" class="progress-bar" style="width: <?php echo $job_in_progress ? (($current_job['done'] / $current_job['total']) * 100) . '%' : '0%'; ?>"></div>
                    </div>
                    <div class="screen-reader-text" aria-live="polite" id="progress-screen-reader-text">
                        <?php 
                        if ($job_in_progress) {
                            printf(
                                esc_html__('Progress: %1$d percent complete', 'image-squeeze'),
                                intval(($current_job['done'] / $current_job['total']) * 100)
                            );
                        } else {
                            echo esc_html__('Progress: 0 percent complete', 'image-squeeze');
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Debug Info -->
        <div class="postbox">
            <h2 class="hndle">
                <span class="dashicons dashicons-info"></span>
                <span>Debug Info</span>
            </h2>
            <div class="inside">
                <p>Dashboard tab render completed. If you can see this, the dashboard tab is rendering correctly.</p>
                <p>Total Images: <?php echo isset($total_images) ? $total_images : 'Not set'; ?></p>
                <p>Optimized Images: <?php echo isset($optimized_images) ? $optimized_images : 'Not set'; ?></p>
                <p>Failed Images: <?php echo isset($failed_images) ? $failed_images : 'Not set'; ?></p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Logs tab content.
 */
function image_squeeze_render_logs_tab() {
    // Path to the logs partial
    $logs_partial = plugin_dir_path(__FILE__) . 'partials/logs-ui.php';
    
    // Check if file exists
    if (file_exists($logs_partial)) {
        include_once $logs_partial;
    } else {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Logs UI partial file not found.', 'image-squeeze');
        echo '</p></div>';
    }
}

/**
 * Render Settings tab content.
 */
function image_squeeze_render_settings_tab() {
    // Call the settings UI function
    image_squeeze_settings_ui();
}

/**
 * Render Cleanup tab content.
 */
function image_squeeze_render_cleanup_tab() {
    // Path to the cleanup partial
    $cleanup_partial = plugin_dir_path(__FILE__) . 'partials/cleanup-ui.php';
    
    // Check if file exists
    if (file_exists($cleanup_partial)) {
        include_once $cleanup_partial;
    } else {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Cleanup UI partial file not found.', 'image-squeeze');
        echo '</p></div>';
    }
} 