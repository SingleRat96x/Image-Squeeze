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
    
    <?php if (!empty($logs)) : ?>
    <div class="imagesqueeze-logs-filters">
        <div class="imagesqueeze-filter-group">
            <label for="log-type-filter"><?php echo esc_html__('Filter by Status:', 'image-squeeze'); ?></label>
            <select id="log-type-filter" class="imagesqueeze-filter">
                <option value="all"><?php echo esc_html__('All', 'image-squeeze'); ?></option>
                <option value="success"><?php echo esc_html__('Success', 'image-squeeze'); ?></option>
                <option value="failed"><?php echo esc_html__('Failed', 'image-squeeze'); ?></option>
                <option value="cleanup"><?php echo esc_html__('Cleanup', 'image-squeeze'); ?></option>
                <option value="retry"><?php echo esc_html__('Retry', 'image-squeeze'); ?></option>
            </select>
        </div>
        
        <div class="imagesqueeze-filter-group">
            <label for="date-range-filter"><?php echo esc_html__('Date Range:', 'image-squeeze'); ?></label>
            <select id="date-range-filter" class="imagesqueeze-filter">
                <option value="all"><?php echo esc_html__('All', 'image-squeeze'); ?></option>
                <option value="today"><?php echo esc_html__('Today', 'image-squeeze'); ?></option>
                <option value="week"><?php echo esc_html__('Last 7 Days', 'image-squeeze'); ?></option>
                <option value="month"><?php echo esc_html__('Last 30 Days', 'image-squeeze'); ?></option>
                <option value="custom"><?php echo esc_html__('Custom', 'image-squeeze'); ?></option>
            </select>
        </div>
        
        <div id="custom-date-inputs" class="imagesqueeze-filter-group" style="display: none;">
            <label for="start-date"><?php echo esc_html__('Start Date:', 'image-squeeze'); ?></label>
            <input type="date" id="start-date" class="imagesqueeze-date-input">
            
            <label for="end-date"><?php echo esc_html__('End Date:', 'image-squeeze'); ?></label>
            <input type="date" id="end-date" class="imagesqueeze-date-input">
            
            <button id="apply-custom-date" class="button button-secondary">
                <?php echo esc_html__('Apply', 'image-squeeze'); ?>
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
                } elseif ($has_failed) {
                    $card_class .= ' failed-job';
                    $log_icon = 'dashicons-warning';
                    $log_title = __('Failed Job', 'image-squeeze');
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
                                    <span class="detail-label"><?php echo esc_html__('Deleted Files:', 'image-squeeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($log['deleted'] ?? 0); ?></span>
                                </li>
                            </ul>
                        <?php else : ?>
                            <ul class="timeline-details-list">
                                <?php if (isset($log['total']) && $log['total'] > 0) : ?>
                                <li>
                                    <span class="dashicons dashicons-images-alt2"></span>
                                    <span class="detail-label"><?php echo esc_html__('Total Processed:', 'image-squeeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($log['total'] ?? 0); ?></span>
                                </li>
                                <?php endif; ?>
                                
                                <li class="success">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <span class="detail-label"><?php echo esc_html__('Optimized:', 'image-squeeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($log['optimized'] ?? 0); ?></span>
                                </li>
                                
                                <?php if (isset($log['failed']) && $log['failed'] > 0) : ?>
                                <li class="error">
                                    <span class="dashicons dashicons-warning"></span>
                                    <span class="detail-label"><?php echo esc_html__('Failed:', 'image-squeeze'); ?></span>
                                    <span class="detail-value"><?php echo esc_html($log['failed'] ?? 0); ?></span>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (isset($log['saved_bytes']) && $log['saved_bytes'] > 0) : ?>
                                <li class="savings">
                                    <span class="dashicons dashicons-database"></span>
                                    <span class="detail-label"><?php echo esc_html__('Space Saved:', 'image-squeeze'); ?></span>
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
                    <?php echo esc_html__('No optimization logs found. Run a batch optimization to see results here.', 'image-squeeze'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($logs)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filtering variables
    let activeTypeFilter = 'all';
    let activeDateRange = 'all';
    let customStartDate = '';
    let customEndDate = '';
    
    // DOM Elements
    const typeFilter = document.getElementById('log-type-filter');
    const dateRangeFilter = document.getElementById('date-range-filter');
    const customDateInputs = document.getElementById('custom-date-inputs');
    const startDateInput = document.getElementById('start-date');
    const endDateInput = document.getElementById('end-date');
    const applyCustomDateBtn = document.getElementById('apply-custom-date');
    const logEntries = document.querySelectorAll('.imagesqueeze-timeline-card');
    
    // Set today's date as default for date inputs
    const today = new Date();
    const todayFormatted = today.toISOString().split('T')[0];
    endDateInput.value = todayFormatted;
    
    // Set default start date to 30 days ago
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);
    startDateInput.value = thirtyDaysAgo.toISOString().split('T')[0];
    
    // Event Listeners
    typeFilter.addEventListener('change', filterLogs);
    dateRangeFilter.addEventListener('change', handleDateRangeChange);
    applyCustomDateBtn.addEventListener('click', filterLogs);
    
    function handleDateRangeChange() {
        activeDateRange = dateRangeFilter.value;
        
        // Show/hide custom date inputs
        if (activeDateRange === 'custom') {
            customDateInputs.style.display = 'flex';
        } else {
            customDateInputs.style.display = 'none';
            filterLogs();
        }
    }
    
    function filterLogs() {
        // Get current filter values
        activeTypeFilter = typeFilter.value;
        activeDateRange = dateRangeFilter.value;
        
        if (activeDateRange === 'custom') {
            customStartDate = startDateInput.value;
            customEndDate = endDateInput.value;
        }
        
        // Apply filters to each log entry
        logEntries.forEach(function(logEntry) {
            const logType = logEntry.getAttribute('data-log-type');
            const logDate = logEntry.getAttribute('data-log-date');
            
            // Initialize visibility
            let showByType = (activeTypeFilter === 'all' || 
                              (activeTypeFilter === 'success' && logType === 'full') ||
                              (activeTypeFilter !== 'success' && logType === activeTypeFilter));
            let showByDate = false;
            
            // Handle date filtering
            if (activeDateRange === 'all') {
                showByDate = true;
            } else if (activeDateRange === 'today') {
                showByDate = (logDate === todayFormatted);
            } else if (activeDateRange === 'week') {
                const sevenDaysAgo = new Date();
                sevenDaysAgo.setDate(today.getDate() - 7);
                const sevenDaysAgoFormatted = sevenDaysAgo.toISOString().split('T')[0];
                showByDate = (logDate >= sevenDaysAgoFormatted && logDate <= todayFormatted);
            } else if (activeDateRange === 'month') {
                const thirtyDaysAgoFormatted = thirtyDaysAgo.toISOString().split('T')[0];
                showByDate = (logDate >= thirtyDaysAgoFormatted && logDate <= todayFormatted);
            } else if (activeDateRange === 'custom') {
                showByDate = (logDate >= customStartDate && logDate <= customEndDate);
            }
            
            // Show/hide based on combined filters
            if (showByType && showByDate) {
                logEntry.style.display = 'block';
            } else {
                logEntry.style.display = 'none';
            }
        });
        
        // Check if no visible logs
        checkNoVisibleLogs();
    }
    
    function checkNoVisibleLogs() {
        // Fix: Improve the selector to count visible logs correctly
        // Find logs that are not hidden (either display:block or no display style)
        const visibleLogs = Array.from(logEntries).filter(entry => {
            const style = window.getComputedStyle(entry);
            return style.display !== 'none';
        });
        
        const timelineContainer = document.querySelector('.imagesqueeze-logs-timeline');
        let noLogsMessage = timelineContainer.querySelector('.imagesqueeze-no-visible-logs');
        
        if (visibleLogs.length === 0) {
            // Create "no visible logs" message if it doesn't exist
            if (!noLogsMessage) {
                noLogsMessage = document.createElement('div');
                noLogsMessage.className = 'imagesqueeze-no-visible-logs';
                noLogsMessage.innerHTML = `
                    <span class="dashicons dashicons-filter"></span>
                    <p>${typeFilter.options[typeFilter.selectedIndex].text} ${dateRangeFilter.options[dateRangeFilter.selectedIndex].text}</p>
                    <p>${'<?php echo esc_js(__('No logs match your current filter settings.', 'image-squeeze')); ?>'}</p>
                `;
                timelineContainer.appendChild(noLogsMessage);
            } else {
                // Update the message with current filter settings
                const filterText = document.createElement('p');
                filterText.textContent = `${typeFilter.options[typeFilter.selectedIndex].text} ${dateRangeFilter.options[dateRangeFilter.selectedIndex].text}`;
                noLogsMessage.querySelector('p').replaceWith(filterText);
            }
            noLogsMessage.style.display = 'flex';
        } else if (noLogsMessage) {
            // Hide "no visible logs" message if we have visible logs
            noLogsMessage.style.display = 'none';
        }
    }
    
    // Run initial filtering in case URL has parameters
    filterLogs();
});
</script>
<?php endif; ?> 