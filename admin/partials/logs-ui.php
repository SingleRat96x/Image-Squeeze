<?php
/**
 * Logs UI Template
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

// Get logs from the option
$logs = get_option('imagesqueeze_optimization_log', array());

// Sort logs by date (newest first) - they should already be sorted this way
// but we'll ensure it just in case
if (!empty($logs)) {
    usort($logs, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
?>

<div class="imagesqueeze-section">
    <h2 class="imagesqueeze-section-title">
        <span class="dashicons dashicons-list-view"></span>
        <?php echo esc_html__('Optimization History', 'image-squeeze'); ?>
    </h2>
    
    <div class="imagesqueeze-logs-container">
        <?php if (!empty($logs)) : ?>
            <?php foreach ($logs as $log) : ?>
                <?php
                // Determine log type
                $is_retry = isset($log['job_type']) && $log['job_type'] === 'retry';
                $is_cleanup = isset($log['job_type']) && $log['job_type'] === 'cleanup';
                
                // Determine class based on job type
                $log_class = 'imagesqueeze-log-entry';
                $log_icon = 'dashicons-admin-tools';
                $log_title = __('Optimization Job', 'image-squeeze');
                
                if ($is_retry) {
                    $log_class .= ' retry-job';
                    $log_icon = 'dashicons-update';
                    $log_title = __('Retry Job', 'image-squeeze');
                } elseif ($is_cleanup) {
                    $log_class .= ' cleanup-job';
                    $log_icon = 'dashicons-cleaning';
                    $log_title = __('Cleanup', 'image-squeeze');
                }
                
                // Format the date
                $date = isset($log['date']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['date'])) : '';
                
                // Get counts
                $total = isset($log['total']) ? intval($log['total']) : 0;
                $optimized = isset($log['optimized']) ? intval($log['optimized']) : 0;
                $failed = isset($log['failed']) ? intval($log['failed']) : 0;
                $deleted = isset($log['deleted']) ? intval($log['deleted']) : 0;
                ?>
                
                <div class="imagesqueeze-log-card <?php echo esc_attr($log_class); ?>">
                    <div class="imagesqueeze-log-header">
                        <div class="log-title">
                            <span class="dashicons <?php echo esc_attr($log_icon); ?>" aria-hidden="true"></span>
                            <?php echo esc_html($log_title); ?>
                        </div>
                        <span class="log-date" title="<?php echo esc_attr($date); ?>">
                            <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                            <?php echo esc_html($date); ?>
                        </span>
                    </div>
                    <div class="imagesqueeze-log-details">
                        <?php if ($is_cleanup) : ?>
                            <div class="imagesqueeze-log-stats">
                                <div class="imagesqueeze-log-stat success">
                                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                    <span class="stat-label"><?php echo esc_html__('Deleted Files:', 'image-squeeze'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($deleted); ?></span>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="imagesqueeze-log-summary">
                                <span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
                                <?php echo esc_html__('Total Images Processed:', 'image-squeeze'); ?> 
                                <strong><?php echo esc_html($total); ?></strong>
                            </div>
                            <div class="imagesqueeze-log-stats">
                                <div class="imagesqueeze-log-stat success">
                                    <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                                    <span class="stat-label"><?php echo esc_html__('Optimized:', 'image-squeeze'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($optimized); ?></span>
                                </div>
                                <?php if ($failed > 0) : ?>
                                    <div class="imagesqueeze-log-stat error">
                                        <span class="dashicons dashicons-no" aria-hidden="true"></span>
                                        <span class="stat-label"><?php echo esc_html__('Failed:', 'image-squeeze'); ?></span>
                                        <span class="stat-value"><?php echo esc_html($failed); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="notice notice-info imagesqueeze-no-logs">
                <span class="dashicons dashicons-info" aria-hidden="true"></span>
                <p>
                    <?php echo esc_html__('No optimization logs found. Run a batch optimization to see results here.', 'image-squeeze'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div> 