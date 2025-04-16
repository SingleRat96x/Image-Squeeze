/**
 * ImageSqueeze Logs UI functionality
 *
 * @package ImageSqueeze
 */

(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we have the necessary elements
        const typeFilter = document.getElementById('log-type-filter');
        const dateRangeFilter = document.getElementById('date-range-filter');
        const customDateInputs = document.getElementById('custom-date-inputs');
        const startDateInput = document.getElementById('start-date');
        const endDateInput = document.getElementById('end-date');
        const applyCustomDateBtn = document.getElementById('apply-custom-date');
        const logEntries = document.querySelectorAll('.imagesqueeze-timeline-card');

        // Exit if we don't have logs
        if (!typeFilter || !dateRangeFilter || !logEntries.length) {
            return;
        }
        
        // Filtering variables
        let activeTypeFilter = 'all';
        let activeDateRange = 'all';
        let customStartDate = '';
        let customEndDate = '';
        
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
        
        /**
         * Handle date range dropdown change
         */
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
        
        /**
         * Filter logs based on selected criteria
         */
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
        
        /**
         * Check if there are no visible logs after filtering and show a message
         */
        function checkNoVisibleLogs() {
            // Find logs that are not hidden
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
                    
                    // Get filter text from selected options
                    const typeText = typeFilter.options[typeFilter.selectedIndex].text;
                    const dateText = dateRangeFilter.options[dateRangeFilter.selectedIndex].text;
                    
                    noLogsMessage.innerHTML = `
                        <span class="dashicons dashicons-filter"></span>
                        <p>${typeText} ${dateText}</p>
                        <p>${imageSqueeze.strings.noLogsMatch}</p>
                    `;
                    timelineContainer.appendChild(noLogsMessage);
                } else {
                    // Update the message with current filter settings
                    const filterText = document.createElement('p');
                    const typeText = typeFilter.options[typeFilter.selectedIndex].text;
                    const dateText = dateRangeFilter.options[dateRangeFilter.selectedIndex].text;
                    filterText.textContent = `${typeText} ${dateText}`;
                    
                    // Replace the first paragraph
                    const paragraphs = noLogsMessage.querySelectorAll('p');
                    if (paragraphs.length > 0) {
                        paragraphs[0].replaceWith(filterText);
                    }
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
})(); 