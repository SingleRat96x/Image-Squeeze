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
        <?php echo esc_html__('Optimization History', 'imagesqueeze'); ?>
        
        <?php if (!empty($logs)) : ?>
            <div class="imagesqueeze-clear-logs-container">
                <button id="imagesqueeze-clear-logs" class="button button-secondary">
                    <span class="dashicons dashicons-trash" style="margin-top: 3px; margin-right: 5px;"></span>
                    <?php echo esc_html__('Clear All Logs', 'imagesqueeze'); ?>
                </button>
                <span id="imagesqueeze-clear-logs-spinner" class="spinner" style="display: none;"></span>
            </div>
        <?php endif; ?>
    </h2>
    
    <?php if (!empty($logs)) : ?>
    <div class="imagesqueeze-logs-filters">
        <div class="imagesqueeze-filter-group">
            <label for="log-type-filter"><?php echo esc_html__('Filter by Status:', 'imagesqueeze'); ?></label>
            <select id="log-type-filter" class="imagesqueeze-filter">
                <option value="all"><?php echo esc_html__('All', 'imagesqueeze'); ?></option>
                <option value="success"><?php echo esc_html__('Success', 'imagesqueeze'); ?></option>
                <option value="failed"><?php echo esc_html__('Failed', 'imagesqueeze'); ?></option>
                <option value="cleanup"><?php echo esc_html__('Cleanup', 'imagesqueeze'); ?></option>
                <option value="retry"><?php echo esc_html__('Retry', 'imagesqueeze'); ?></option>
            </select>
        </div>
        
        <div class="imagesqueeze-filter-group">
            <label for="date-range-filter"><?php echo esc_html__('Date Range:', 'imagesqueeze'); ?></label>
            <select id="date-range-filter" class="imagesqueeze-filter">
                <option value="all"><?php echo esc_html__('All', 'imagesqueeze'); ?></option>
                <option value="today"><?php echo esc_html__('Today', 'imagesqueeze'); ?></option>
                <option value="week"><?php echo esc_html__('Last 7 Days', 'imagesqueeze'); ?></option>
                <option value="month"><?php echo esc_html__('Last 30 Days', 'imagesqueeze'); ?></option>
                <option value="custom"><?php echo esc_html__('Custom', 'imagesqueeze'); ?></option>
            </select>
        </div>
        
        <div id="custom-date-inputs" class="imagesqueeze-filter-group" style="display: none;">
            <label for="start-date"><?php echo esc_html__('Start Date:', 'imagesqueeze'); ?></label>
            <input type="date" id="start-date" class="imagesqueeze-date-input">
            
            <label for="end-date"><?php echo esc_html__('End Date:', 'imagesqueeze'); ?></label>
            <input type="date" id="end-date" class="imagesqueeze-date-input">
            
            <button id="apply-custom-date" class="button button-secondary">
                <?php echo esc_html__('Apply', 'imagesqueeze'); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="imagesqueeze-logs-timeline">
        <?php if (!empty($logs)) : ?>
            <?php foreach ($logs as $log) : ?>
                <?php
                // Determine log type
                $is_retry = isset($log['job_type']) && $log['job_type'] === 'retry';
                $is_cleanup = isset($log['job_type']) && $log['job_type'] === 'cleanup';
                $has_failed = isset($log['failed']) && $log['failed'] > 0;
                
                // Get log type for filtering
                $log_type = $is_retry ? 'retry' : ($is_cleanup ? 'cleanup' : ($has_failed ? 'failed' : 'full'));
                
                // Determine class based on job type
                $card_class = 'imagesqueeze-timeline-card';
                $log_icon = 'dashicons-update';
                $log_title = __('Full Optimization', 'imagesqueeze');
                $badge_class = 'badge-full';
                
                if ($is_retry) {
                    $card_class .= ' retry-job';
                    $log_icon = 'dashicons-controls-repeat';
                    $log_title = __('Retry Job', 'imagesqueeze');
                    $badge_class = 'badge-retry';
                } elseif ($is_cleanup) {
                    $card_class .= ' cleanup-job';
                    $log_icon = 'dashicons-trash';
                    $log_title = __('Cleanup Job', 'imagesqueeze');
                    $badge_class = 'badge-cleanup';
                } elseif ($has_failed) {
                    $card_class .= ' failed-job';
                    $log_icon = 'dashicons-warning';
                    $log_title = __('Failed Job', 'imagesqueeze');
                    $badge_class = 'badge-failed';
                }
                
                // Format the date for display
                $display_date = isset($log['date']) ? date_i18n(get_option('date_format'), strtotime($log['date'])) : '';
                
                // Get the date in standard format for filtering (YYYY-MM-DD)
                // Replaced date() with gmdate() per WP coding standards
                $filter_date = isset($log['date']) ? gmdate('Y-m-d', strtotime($log['date'])) : '';
                ?>
                
                <div class="<?php echo esc_attr($card_class); ?>" data-log-type="<?php echo esc_attr($log_type); ?>" data-log-date="<?php echo esc_attr($filter_date); ?>">
                    <div class="timeline-connector"></div>
                    
                    <div class="timeline-card-header">
                        <div class="timeline-date">
                            <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                            <span><?php echo esc_html($display_date); ?></span>
                        </div>
                        
                        <div class="timeline-type">
                            <span class="timeline-badge <?php echo esc_attr($badge_class); ?>">
                                <span class="dashicons <?php echo esc_attr($log_icon); ?>"></span>
                                <?php echo esc_html($log_title); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="timeline-card-content">
                        <?php if ($is_cleanup) : ?>
                            <ul class="timeline-details-list">
                                <li class="success">
                                    <span class="dashicons dashicons-trash"></span>
                                    <span class="detail-label"><?php echo esc_html__('Deleted Files:', 'imagesqueeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($log['deleted'] ?? 0); ?></span>
                                </li>
                            </ul>
                        <?php else : ?>
                            <ul class="timeline-details-list">
                                <?php if (isset($log['total']) && $log['total'] > 0) : ?>
                                <li>
                                    <span class="dashicons dashicons-images-alt2"></span>
                                    <span class="detail-label"><?php echo esc_html__('Total Processed:', 'imagesqueeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($log['total'] ?? 0); ?></span>
                                </li>
                                <?php endif; ?>
                                
                                <li class="success">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <span class="detail-label"><?php echo esc_html__('Optimized:', 'imagesqueeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($log['optimized'] ?? 0); ?></span>
                                </li>
                                
                                <?php if (isset($log['failed']) && $log['failed'] > 0) : ?>
                                <li class="error">
                                    <span class="dashicons dashicons-warning"></span>
                                    <span class="detail-label"><?php echo esc_html__('Failed:', 'imagesqueeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($log['failed'] ?? 0); ?></span>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (isset($log['saved_bytes']) && $log['saved_bytes'] > 0) : ?>
                                <li class="savings">
                                    <span class="dashicons dashicons-database"></span>
                                    <span class="detail-label"><?php echo esc_html__('Space Saved:', 'imagesqueeze'); ?></span>
                                    <span class="detail-value">
                                        <?php 
                                        $saved_bytes = intval($log['saved_bytes']);
                                        if ($saved_bytes >= 1048576) { // 1MB
                                            echo esc_html(round($saved_bytes / 1048576, 2) . ' MB');
                                        } else {
                                            echo esc_html(round($saved_bytes / 1024, 2) . ' KB');
                                        }
                                        ?>
                                    </span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="imagesqueeze-no-logs">
                <span class="dashicons dashicons-info-outline"></span>
                <p>
                    <?php echo esc_html__('No optimization logs found. Run a batch optimization to see results here.', 'imagesqueeze'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

