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
        
        <?php if (!empty($logs)) : ?>
            <div class="imagesqueeze-clear-logs-container">
                <button id="imagesqueeze-clear-logs" class="button button-secondary">
                    <span class="dashicons dashicons-trash" style="margin-top: 3px; margin-right: 5px;"></span>
                    <?php echo esc_html__('Clear All Logs', 'image-squeeze'); ?>
                </button>
                <span id="imagesqueeze-clear-logs-spinner" class="spinner" style="display: none;"></span>
            </div>
        <?php endif; ?>
    </h2>
    
    <div class="imagesqueeze-logs-timeline">
        <?php if (!empty($logs)) : ?>
            <?php foreach ($logs as $log) : ?>
                <?php
                // Determine log type
                $is_retry = isset($log['job_type']) && $log['job_type'] === 'retry';
                $is_cleanup = isset($log['job_type']) && $log['job_type'] === 'cleanup';
                
                // Determine class based on job type
                $card_class = 'imagesqueeze-timeline-card';
                $log_icon = 'dashicons-update';
                $log_title = __('Full Optimization', 'image-squeeze');
                $badge_class = 'badge-full';
                
                if ($is_retry) {
                    $card_class .= ' retry-job';
                    $log_icon = 'dashicons-controls-repeat';
                    $log_title = __('Retry Job', 'image-squeeze');
                    $badge_class = 'badge-retry';
                } elseif ($is_cleanup) {
                    $card_class .= ' cleanup-job';
                    $log_icon = 'dashicons-trash';
                    $log_title = __('Cleanup Job', 'image-squeeze');
                    $badge_class = 'badge-cleanup';
                }
                
                // Format the date
                $date = isset($log['date']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['date'])) : '';
                $short_date = isset($log['date']) ? date_i18n(get_option('date_format'), strtotime($log['date'])) : '';
                
                // Explicitly extract and format the time component for better reliability
                $time_display = '';
                if (isset($log['date']) && !empty($log['date'])) {
                    try {
                        // Create a DateTime object from the stored timestamp
                        $date_obj = new DateTime($log['date']);
                        
                        // Apply WordPress timezone
                        $timezone_string = get_option('timezone_string');
                        $gmt_offset = get_option('gmt_offset');
                        
                        if (!empty($timezone_string)) {
                            $date_obj->setTimezone(new DateTimeZone($timezone_string));
                        } elseif ($gmt_offset !== false) {
                            $offset = (float) $gmt_offset;
                            $hours = (int) $offset;
                            $minutes = ($offset - $hours) * 60;
                            
                            $sign = ($offset >= 0) ? '+' : '-';
                            $timezone_offset = sprintf('%s%02d:%02d', $sign, abs($hours), abs($minutes));
                            $date_obj->setTimezone(new DateTimeZone($timezone_offset));
                        }
                        
                        // Format using WordPress time format settings
                        $time_format = get_option('time_format', 'g:i a');
                        $time_display = $date_obj->format($time_format);
                        
                        // Also update date display for consistency
                        $date_format = get_option('date_format', 'F j, Y');
                        $short_date = $date_obj->format($date_format);
                        $date = $date_obj->format($date_format . ' ' . $time_format);
                    } catch (Exception $e) {
                        // Fallback to the original method if DateTime fails
                        $timestamp = strtotime($log['date']);
                        if ($timestamp !== false) {
                            $time_display = date_i18n(get_option('time_format'), $timestamp);
                        }
                    }
                }
                
                // Get counts
                $total = isset($log['total']) ? intval($log['total']) : 0;
                $optimized = isset($log['optimized']) ? intval($log['optimized']) : 0;
                $failed = isset($log['failed']) ? intval($log['failed']) : 0;
                $deleted = isset($log['deleted']) ? intval($log['deleted']) : 0;
                $saved_bytes = isset($log['saved_bytes']) ? intval($log['saved_bytes']) : 0;
                
                // Format saved bytes
                $saved_display = '';
                if ($saved_bytes > 0) {
                    if ($saved_bytes >= 1048576) { // 1MB
                        $saved_display = round($saved_bytes / 1048576, 2) . ' MB';
                    } else {
                        $saved_display = round($saved_bytes / 1024, 2) . ' KB';
                    }
                }
                ?>
                
                <div class="<?php echo esc_attr($card_class); ?>">
                    <div class="timeline-connector"></div>
                    
                    <div class="timeline-card-header">
                        <div class="timeline-date">
                            <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                            <span><?php echo esc_html($short_date); ?></span>
                        </div>
                        
                        <div class="timeline-type">
                            <span class="timeline-badge <?php echo esc_attr($badge_class); ?>">
                                <span class="dashicons <?php echo esc_attr($log_icon); ?>"></span>
                                <?php echo esc_html($log_title); ?>
                            </span>
                        </div>
                        
                        <div class="timeline-time" title="<?php echo esc_attr($date); ?>">
                            <?php if (isset($log['time'])): ?>
                                <span class="plain-timestamp">
                                    <?php echo esc_html($log['time']); ?>
                                </span>
                            <?php elseif (isset($log['timestamp'])): ?>
                                <span class="stored-timestamp" data-timestamp="<?php echo esc_attr($log['timestamp']); ?>">
                                    <?php echo esc_html($time_display); ?>
                                </span>
                            <?php else: ?>
                                <?php echo esc_html($time_display); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="timeline-card-content">
                        <?php if ($is_cleanup) : ?>
                            <ul class="timeline-details-list">
                                <li class="success">
                                    <span class="dashicons dashicons-trash"></span>
                                    <span class="detail-label"><?php echo esc_html__('Deleted Files:', 'image-squeeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($deleted); ?></span>
                                </li>
                            </ul>
                        <?php else : ?>
                            <ul class="timeline-details-list">
                                <?php if ($total > 0) : ?>
                                <li>
                                    <span class="dashicons dashicons-images-alt2"></span>
                                    <span class="detail-label"><?php echo esc_html__('Total Processed:', 'image-squeeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($total); ?></span>
                                </li>
                                <?php endif; ?>
                                
                                <li class="success">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <span class="detail-label"><?php echo esc_html__('Optimized:', 'image-squeeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($optimized); ?></span>
                                </li>
                                
                                <?php if ($failed > 0) : ?>
                                <li class="error">
                                    <span class="dashicons dashicons-warning"></span>
                                    <span class="detail-label"><?php echo esc_html__('Failed:', 'image-squeeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($failed); ?></span>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($saved_display)) : ?>
                                <li class="savings">
                                    <span class="dashicons dashicons-database"></span>
                                    <span class="detail-label"><?php echo esc_html__('Space Saved:', 'image-squeeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($saved_display); ?></span>
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
                    <?php echo esc_html__('No optimization logs found. Run a batch optimization to see results here.', 'image-squeeze'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($logs)): ?>
<script>
// Simple backup script to handle any legacy timestamp entries during transition
document.addEventListener('DOMContentLoaded', function() {
    // Only convert legacy timestamps (should be rare/none after migration)
    var legacyTimestamps = document.querySelectorAll('.stored-timestamp');
    if (legacyTimestamps.length > 0) {
        console.log('Converting ' + legacyTimestamps.length + ' legacy timestamps');
        
        legacyTimestamps.forEach(function(element) {
            var timestamp = element.getAttribute('data-timestamp');
            if (timestamp) {
                // Convert Unix timestamp to milliseconds for JavaScript Date
                var date = new Date(parseInt(timestamp) * 1000);
                
                // Format time as HH:MM AM/PM
                var hours = date.getHours();
                var minutes = date.getMinutes();
                var ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12; // the hour '0' should be '12'
                minutes = minutes < 10 ? '0' + minutes : minutes;
                var localTime = hours + ':' + minutes + ' ' + ampm;
                
                // Update the element
                element.textContent = localTime;
            }
        });
    }
});
</script>
<?php endif; ?> 