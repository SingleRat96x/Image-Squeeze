/**
 * Image Squeeze Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Translations for UI elements
    const jsTranslate = {
        optimizationComplete: 'Optimization complete!',
        optimizationSuccess: 'Optimization completed successfully!',
        processing: 'Processing %1$d of %2$d images',
        percentComplete: 'Progress: %1$d percent complete',
        scanningForOrphaned: 'Scanning for orphaned WebP files...',
        cleanupComplete: 'Cleanup complete!',
        errorOccurred: 'An error occurred. Please try again.',
        optimizationCancelled: 'Optimization cancelled.'
    };
    
    // Store references to DOM elements
    const elements = {
        optimizeButton: $('#optimize-images'),
        retryButton: $('#retry-failed-images'),
        cancelButton: $('#cancel-optimization'),
        progressContainer: $('#progress-container'),
        progressBar: $('#progress-bar'),
        progressText: $('#progress-text'),
        optimizationStatus: $('#optimization-status'),
        optimizationStatusText: $('#optimization-status-text'),
        progressLabel: $('.imagesqueeze-progress-label'),
        statusBadge: $('.imagesqueeze-status-badge'),
        spinner: $('.imagesqueeze-spinner'),
        statCards: $('.imagesqueeze-stat-value'),
        noticesContainer: $('.imagesqueeze-notices')
    };
    
    // Poll timer reference
    let pollTimer = null;
    
    // Track current job state
    let jobStatus = {
        inProgress: false,
        total: 0,
        done: 0,
        status: '',
        cleanup: false
    };
    
    /**
     * Initialize the admin UI
     */
    function init() {
        // Hide the optimization status initially
        elements.optimizationStatus.hide();
        
        // Attach event listeners
        elements.optimizeButton.on('click', startOptimization);
        elements.retryButton.on('click', startRetryOptimization);
        elements.cancelButton.on('click', cancelOptimization);
        
        // Check for existing job on page load
        checkExistingJob();
    }
    
    /**
     * Check if a job already exists and set up UI accordingly
     */
    function checkExistingJob() {
        $.ajax({
            url: imageSqueeze.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imagesqueeze_get_progress',
                security: imageSqueeze.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    jobStatus.status = data.status;
                    jobStatus.done = parseInt(data.done) || 0;
                    jobStatus.total = parseInt(data.total) || 0;
                    jobStatus.cleanup = data.cleanup_on_next_visit;
                    
                    // If job is in progress, show UI and start polling
                    if (data.status === 'in_progress') {
                        jobStatus.inProgress = true;
                        showProgressUI();
                        updateProgress(jobStatus.done, jobStatus.total);
                        startPolling();
                        processBatch();
                    } 
                    // If job is completed but needs cleanup
                    else if (data.status === 'completed' && data.cleanup_on_next_visit) {
                        cleanupJob();
                    }
                    // If job is complete
                    else if (data.status === 'completed') {
                        updateStatusBadgeToComplete();
                        showCompletedMessage();
                    }
                }
            },
            error: function(xhr) {
                // Handle different error types
                let errorMessage = 'Something went wrong. Please try again.';
                let isNoImagesError = false;
                
                // Try to extract a better error message if available
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                    
                    // Check if this is a "no images" error
                    if (errorMessage.includes('No images found')) {
                        isNoImagesError = true;
                    }
                }
                
                if (isNoImagesError) {
                    // Show a friendly message for no images error
                    elements.progressContainer.hide();
                    elements.optimizationStatus.show();
                    elements.optimizationStatus.find('.dashicons')
                        .removeClass('dashicons-yes dashicons-update')
                        .addClass('dashicons-info');
                    elements.optimizationStatusText.text('No images found for optimization');
                    
                    // Also show a notice
                    showNotice('There are no unoptimized images in your media library. All images are already optimized!', 'info');
                } else {
                    // Show regular error notice
                    showNotice(errorMessage, 'error');
                }
                
                resetUI();
            }
        });
    }
    
    /**
     * Start a full optimization job
     */
    function startOptimization() {
        if (jobStatus.inProgress) {
            return; // Prevent starting a new job if one is already running
        }
        
        startJob('full');
    }
    
    /**
     * Start a retry optimization job
     */
    function startRetryOptimization() {
        if (jobStatus.inProgress) {
            return; // Prevent starting a new job if one is already running
        }
        
        startJob('retry');
    }
    
    /**
     * Start an optimization job
     * @param {string} jobType - Type of job ('full' or 'retry')
     */
    function startJob(jobType) {
        // Clear any previous notices and status messages
        clearNotices();
        elements.optimizationStatus.hide();
        
        // Update UI state
        disableButtons();
        
        // Make AJAX request to create job
        $.ajax({
            url: imageSqueeze.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imagesqueeze_create_job',
                job_type: jobType,
                security: imageSqueeze.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Job created successfully
                    jobStatus.inProgress = true;
                    jobStatus.status = 'in_progress';
                    
                    // Update status badge to in-progress
                    updateStatusBadgeToInProgress();
                    
                    // Show progress UI
                    showProgressUI();
                    
                    // Reset progress
                    updateProgress(0, 0);
                    
                    // Start polling for progress updates
                    startPolling();
                    
                    // Start processing batches
                    processBatch();
                } else {
                    // Show error and reset UI
                    const errorMessage = response.data.message || 'Failed to create job';
                    
                    // Show special notice for "no images" error
                    if (response.data.message && response.data.message.includes('No images found')) {
                        // Display message in a more prominent way
                        elements.progressContainer.hide();
                        elements.optimizationStatus.show();
                        elements.optimizationStatus.find('.dashicons')
                            .removeClass('dashicons-yes dashicons-update')
                            .addClass('dashicons-info');
                        elements.optimizationStatusText.text('No images found for optimization');
                        
                        // Also show a notice
                        showNotice('There are no unoptimized images in your media library. All images are already optimized!', 'info');
                    } else {
                        // Show regular error notice
                        showNotice(errorMessage, 'error');
                    }
                    
                    resetUI();
                }
            },
            error: function(xhr) {
                // Handle different error types
                let errorMessage = 'Something went wrong. Please try again.';
                
                // Try to extract a better error message if available
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                
                showNotice(errorMessage, 'error');
                resetUI();
            }
        });
    }
    
    /**
     * Show a notice in the notices container
     * @param {string} message - The message to display
     * @param {string} type - The notice type ('error', 'success', 'info', 'warning')
     */
    function showNotice(message, type) {
        // Clear previous notices
        clearNotices();
        
        // Create appropriate notice type class
        const noticeClass = 'notice notice-' + (type || 'info');
        
        // Create and append the notice
        elements.noticesContainer.append('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
    }
    
    /**
     * Clear all notices
     */
    function clearNotices() {
        if (elements.noticesContainer) {
            elements.noticesContainer.empty();
        }
    }
    
    /**
     * Start polling for job progress
     */
    function startPolling() {
        // Stop any existing poll
        stopPolling();
        
        // Start a new poll interval
        pollTimer = setInterval(pollJobProgress, 5000); // Poll every 5 seconds
    }
    
    /**
     * Stop polling for job progress
     */
    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }
    
    /**
     * Poll for job progress
     */
    function pollJobProgress() {
        if (!jobStatus.inProgress) {
            stopPolling(); // Stop polling if job is not in progress
            return;
        }
        
        $.ajax({
            url: imageSqueeze.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imagesqueeze_get_progress',
                security: imageSqueeze.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Update job status
                    jobStatus.status = data.status;
                    jobStatus.done = parseInt(data.done) || 0;
                    jobStatus.total = parseInt(data.total) || 0;
                    
                    // Update progress UI
                    updateProgress(jobStatus.done, jobStatus.total);
                    
                    // If job is completed, finish up
                    if (data.status === 'completed') {
                        completeJob();
                    }
                    // If job is cancelled, show cancelled state
                    else if (data.status === 'cancelled') {
                        stopPolling();
                        jobStatus.inProgress = false;
                        resetUI();
                        
                        // Show cancellation message in our custom UI instead of a notice
                        elements.progressContainer.hide();
                        elements.optimizationStatus.show();
                        elements.optimizationStatus.find('.dashicons')
                            .removeClass('dashicons-yes dashicons-update')
                            .addClass('dashicons-dismiss');
                        elements.optimizationStatusText.text(jsTranslate.optimizationCancelled);
                    }
                } else {
                    // Show error if there's an issue with the response but don't use notice
                    stopPolling();
                    jobStatus.inProgress = false;
                    resetUI();
                    
                    // Show error in our custom UI
                    elements.progressContainer.hide();
                    elements.optimizationStatus.show();
                    elements.optimizationStatus.find('.dashicons')
                        .removeClass('dashicons-yes dashicons-update')
                        .addClass('dashicons-warning');
                    elements.optimizationStatusText.text(jsTranslate.errorOccurred);
                }
            },
            error: function() {
                // Handle network errors in our custom UI
                stopPolling();
                jobStatus.inProgress = false;
                resetUI();
                
                elements.progressContainer.hide();
                elements.optimizationStatus.show();
                elements.optimizationStatus.find('.dashicons')
                    .removeClass('dashicons-yes dashicons-update')
                    .addClass('dashicons-warning');
                elements.optimizationStatusText.text('Network error. Please try again.');
            }
        });
    }
    
    /**
     * Process a batch of images
     */
    function processBatch() {
        if (!jobStatus.inProgress) {
            return; // Don't process if job is not in progress
        }
        
        $.ajax({
            url: imageSqueeze.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imagesqueeze_process_batch',
                security: imageSqueeze.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Update job status
                    jobStatus.done = parseInt(data.done) || jobStatus.done;
                    jobStatus.total = parseInt(data.total) || jobStatus.total;
                    jobStatus.status = data.status;
                    
                    // Update progress UI
                    updateProgress(jobStatus.done, jobStatus.total);
                    
                    // If there are more batches to process
                    if (data.status === 'in_progress') {
                        // Continue processing
                        processBatch();
                    } else if (data.status === 'completed') {
                        // Job completed successfully
                        completeJob();
                    } else if (data.status === 'cancelled') {
                        // Job was cancelled
                        stopPolling();
                        jobStatus.inProgress = false;
                        resetUI();
                        
                        // Show cancellation message in our custom UI
                        elements.progressContainer.hide();
                        elements.optimizationStatus.show();
                        elements.optimizationStatus.find('.dashicons')
                            .removeClass('dashicons-yes dashicons-update')
                            .addClass('dashicons-dismiss');
                        elements.optimizationStatusText.text(jsTranslate.optimizationCancelled);
                    }
                } else {
                    // Error occurred - show in our custom UI
                    jobStatus.inProgress = false;
                    stopPolling();
                    resetUI();
                    
                    elements.progressContainer.hide();
                    elements.optimizationStatus.show();
                    elements.optimizationStatus.find('.dashicons')
                        .removeClass('dashicons-yes dashicons-update')
                        .addClass('dashicons-warning');
                    elements.optimizationStatusText.text(response.data.message || jsTranslate.errorOccurred);
                }
            },
            error: function() {
                // Network or server error - show in our custom UI
                jobStatus.inProgress = false;
                stopPolling();
                resetUI();
                
                elements.progressContainer.hide();
                elements.optimizationStatus.show();
                elements.optimizationStatus.find('.dashicons')
                    .removeClass('dashicons-yes dashicons-update')
                    .addClass('dashicons-warning');
                elements.optimizationStatusText.text('Network error. Please try again.');
            }
        });
    }
    
    /**
     * Update progress UI
     * @param {number} done - Number of completed images
     * @param {number} total - Total number of images
     */
    function updateProgress(done, total) {
        // Calculate percentage (avoid division by zero)
        const percentage = total > 0 ? Math.round((done / total) * 100) : 0;
        
        // Update progress label
        if (elements.progressLabel && elements.progressLabel.length) {
            elements.progressLabel.text('Compressed ' + done + ' of ' + total + ' images');
        }
        
        // Update progress text (percentage)
        if (elements.progressText && elements.progressText.length) {
            elements.progressText.text(percentage + '% complete');
        }
        
        // Update screen reader text
        if ($('#progress-screen-reader-text').length) {
            $('#progress-screen-reader-text').text(
                jsTranslate.percentComplete.replace('%1$d', percentage)
            );
        }
        
        // If job is completed, update UI accordingly
        if (jobStatus.status === 'completed') {
            // Hide spinner, show success message
            elements.progressContainer.hide();
            showCompletedMessage();
            updateStatusBadgeToComplete();
        }
    }
    
    /**
     * Show the completion message
     */
    function showCompletedMessage() {
        // Remove any existing notices
        clearNotices();
        
        // Show the custom success message
        elements.optimizationStatus.show();
        elements.optimizationStatusText.text(jsTranslate.optimizationComplete);
        
        // Make sure we're using the checkmark icon
        elements.optimizationStatus.find('.dashicons')
            .removeClass('dashicons-update')
            .addClass('dashicons-yes');
    }
    
    /**
     * Update status badge to "In Progress"
     */
    function updateStatusBadgeToInProgress() {
        elements.statusBadge.removeClass('idle complete').addClass('in-progress');
        elements.statusBadge.html('<span class="dashicons dashicons-update"></span> Optimizing...');
    }
    
    /**
     * Update status badge to "Complete"
     */
    function updateStatusBadgeToComplete() {
        elements.statusBadge.removeClass('idle in-progress').addClass('complete');
        elements.statusBadge.html('<span class="dashicons dashicons-yes"></span> Complete');
    }
    
    /**
     * Handle job completion
     */
    function completeJob() {
        // Stop polling
        stopPolling();
        
        // Update UI
        updateProgress(jobStatus.done, jobStatus.total);
        
        // Enable buttons
        enableButtons();
        
        // Mark job as completed
        jobStatus.inProgress = false;
        jobStatus.status = 'completed';
        
        // Show completion message with checkmark icon
        showCompletedMessage();
        updateStatusBadgeToComplete();
        
        // Refresh dashboard stats to update the numbers
        refreshDashboardStats();
    }
    
    /**
     * Refresh dashboard stats via AJAX
     */
    function refreshDashboardStats() {
        console.log('Refreshing dashboard stats - started');
        
        // Show a subtle loading indicator on all stat values
        const $statValues = $('.imagesqueeze-stat-value, .summary-value');
        $statValues.css('opacity', '0.5');
        
        // Add a temporary class to track updated elements
        $('.imagesqueeze-stat-card, .imagesqueeze-summary-stat-card').removeClass('updated-stats');
        
        // Log the nonce value for debugging (masked for security)
        const nonce = imageSqueeze.nonce;
        console.log('Using nonce: ' + (nonce ? nonce.substring(0, 3) + '...' : 'undefined'));
        
        // Prepare the AJAX data
        const ajaxData = {
            action: 'imagesqueeze_get_dashboard_stats',
            security: nonce
        };
        
        console.log('Sending AJAX request to:', imageSqueeze.ajaxUrl);
        console.log('AJAX data:', ajaxData);
        
        $.ajax({
            url: imageSqueeze.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function(response) {
                console.log('Stats AJAX response:', response);
                
                if (response && response.success && response.data) {
                    // Log all fields for debugging
                    console.log('Received data fields:', Object.keys(response.data).join(', '));
                    
                    // Update stat cards in the Image Optimization Overview section
                    updateOverviewStats(response.data);
                    
                    // Check if the Last Optimization Summary section is showing "No optimization jobs" but we just completed one
                    if ($('.imagesqueeze-no-summary').is(':visible') && response.data.last_run_date) {
                        console.log('Updating summary section to show job results');
                        updateSummarySection(response.data);
                    } else {
                        // Just update the existing summary stats
                        updateSummaryStats(response.data);
                    }
                    
                    // If any stat cards weren't updated, log for debugging
                    $('.imagesqueeze-stat-card:not(.updated-stats), .imagesqueeze-summary-stat-card:not(.updated-stats)').each(function() {
                        const $card = $(this);
                        const title = $card.find('.imagesqueeze-stat-title, .summary-label').text();
                        console.log('Stat card not updated:', title);
                    });
                    
                    // Restore opacity after updating
                    $statValues.css('opacity', '1');
                    
                    console.log('Dashboard stats refresh completed successfully');
                } else {
                    console.error('Failed to get stats data:', response);
                    $statValues.css('opacity', '1');
                    
                    // Show a more detailed error message
                    let errorMsg = 'Failed to update stats. Try refreshing the page.';
                    let errorCode = '';
                    
                    if (response && response.data) {
                        if (response.data.message) {
                            errorMsg = response.data.message;
                        }
                        if (response.data.code) {
                            errorCode = ' (Code: ' + response.data.code + ')';
                            console.error('Error code:', response.data.code);
                        }
                    }
                    
                    showNotice(errorMsg + errorCode, 'warning');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error getting stats:', error);
                console.error('Status:', status);
                console.error('Status code:', xhr.status);
                console.error('Response:', xhr.responseText || 'Empty response');
                
                // Restore opacity on error
                $statValues.css('opacity', '1');
                
                // Try to parse the response if it's JSON
                let errorDetail = '';
                try {
                    if (xhr.responseText) {
                        const jsonResponse = JSON.parse(xhr.responseText);
                        if (jsonResponse && jsonResponse.data && jsonResponse.data.message) {
                            errorDetail = ': ' + jsonResponse.data.message;
                        }
                    }
                } catch (e) {
                    console.log('Could not parse error response as JSON');
                }
                
                // Determine a more helpful error message
                let errorMsg = 'Failed to communicate with the server. Try refreshing the page.';
                if (xhr.status === 400) {
                    errorMsg = 'Bad Request: The server rejected the stats request' + errorDetail + '. Try refreshing the page.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Permission denied' + errorDetail + '. Your session may have expired. Try refreshing the page.';
                } else if (xhr.status === 404) {
                    errorMsg = 'AJAX endpoint not found. The plugin may not be properly installed.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Server error' + errorDetail + '. Check your server logs for details.';
                }
                
                showNotice(errorMsg, 'error');
            }
        });
    }
    
    /**
     * Update the Image Optimization Overview stats
     * @param {Object} data - The stats data from the server
     */
    function updateOverviewStats(data) {
        if (!data) {
            console.error('No data provided to updateOverviewStats');
            return;
        }
        
        console.log('Updating overview stats with data:', data);
        
        // Update each card directly with specific data fields
        updateStatCard('Optimized', data.optimized_images);
        updateStatCard('Space Saved', data.total_saved, 'Last Run');
        updateStatCard('Failed', data.failed_images);
        
        // Check for additional stats to update
        if (data.last_run_saved) {
            updateStatCard('Last Run', data.last_run_saved);
        }
        
        // Log any missing data fields for debugging
        if (data.optimized_images === undefined) console.log('Missing: optimized_images');
        if (data.total_saved === undefined) console.log('Missing: total_saved');
        if (data.failed_images === undefined) console.log('Missing: failed_images');
    }
    
    /**
     * Helper function to update a specific stat card by title text
     * @param {string} titleText - Text in the title to match (partial match)
     * @param {string} newValue - New value to set
     * @param {string} excludeText - Optional text to exclude from matching
     */
    function updateStatCard(titleText, newValue, excludeText = null) {
        if (newValue === undefined || newValue === null) {
            console.log(`Skipping update for ${titleText} - value is undefined`);
            return;
        }
        
        let updated = false;
        $('.imagesqueeze-stat-card').each(function() {
            const $card = $(this);
            const $title = $card.find('.imagesqueeze-stat-title');
            const titleContent = $title.text().trim();
            
            if (titleContent.includes(titleText)) {
                // Skip if excludeText is provided and the title contains it
                if (excludeText && titleContent.includes(excludeText)) {
                    return;
                }
                
                const $value = $card.find('.imagesqueeze-stat-value');
                if ($value.length === 0) {
                    console.log(`Found card with title "${titleText}" but no value element`);
                    return;
                }
                
                // Convert to string for proper comparison
                const oldValue = $value.text().trim();
                const newValueStr = String(newValue).trim();
                
                // Only update if the value has changed
                if (oldValue !== newValueStr) {
                    console.log(`Updating ${titleText} from "${oldValue}" to "${newValueStr}"`);
                    $value.text(newValueStr);
                    
                    // Add a brief highlight effect
                    $card.css('background-color', '#f0f6fc');
                    setTimeout(function() {
                        $card.css('background-color', '');
                    }, 1000);
                } else {
                    console.log(`No change needed for ${titleText}: ${oldValue}`);
                }
                
                $card.addClass('updated-stats');
                updated = true;
            }
        });
        
        if (!updated) {
            console.log(`No card found with title containing "${titleText}"`);
        }
    }
    
    /**
     * Update the Last Optimization Summary stats
     * @param {Object} data - The stats data from the server
     */
    function updateSummaryStats(data) {
        if (!data) {
            console.error('No valid data for summary stats update');
            return;
        }
        
        console.log('Updating summary stats with data:', {
            date: data.last_run_date,
            optimized: data.last_run_optimized,
            saved: data.last_run_saved,
            failed: data.last_run_failed
        });
        
        // Only proceed if we have a last run date
        if (!data.last_run_date) {
            console.log('No last_run_date provided, skipping summary update');
            return;
        }
        
        // Make sure the optimized value is visible and properly formatted
        if (data.last_run_optimized !== undefined) {
            console.log(`Last run optimized count from server: ${data.last_run_optimized}`);
        } else {
            console.warn('Missing last_run_optimized count in data from server');
        }
        
        // Update each field directly
        updateSummaryField('Last Run', data.last_run_date);
        updateSummaryField('Optimized Images', data.last_run_optimized);
        updateSummaryField('Space Saved', data.last_run_saved);
        
        // If there's a failed count in the summary, update it
        if (data.last_run_failed !== undefined) {
            // Handle failed count specially as it might be in a warning box
            const $warningEl = $('.summary-value.warning strong');
            if ($warningEl.length) {
                const oldValue = $warningEl.text().trim();
                const newValue = String(data.last_run_failed).trim();
                
                if (oldValue !== newValue) {
                    console.log(`Updating failed count from "${oldValue}" to "${newValue}"`);
                    $warningEl.text(newValue);
                    
                    // Highlight the warning
                    $warningEl.closest('.imagesqueeze-summary-stat-card').addClass('updated-stats')
                        .css('background-color', '#fcf9e8');
                    setTimeout(function() {
                        $warningEl.closest('.imagesqueeze-summary-stat-card').css('background-color', '');
                    }, 1000);
                }
            } else if (parseInt(data.last_run_failed) > 0) {
                console.log('Failed count > 0 but warning element not found in DOM');
            }
        }
    }
    
    /**
     * Helper function to update a specific summary field by label text
     * @param {string} labelText - Text in the label to match
     * @param {string} newValue - New value to set
     */
    function updateSummaryField(labelText, newValue) {
        if (newValue === undefined || newValue === null) {
            console.log(`Skipping update for summary ${labelText} - value is undefined`);
            return;
        }
        
        let updated = false;
        $('.imagesqueeze-summary-stat-card').each(function() {
            const $card = $(this);
            const $label = $card.find('.summary-label');
            const labelContent = $label.text().trim();
            
            if (labelContent.includes(labelText)) {
                const $value = $card.find('.summary-value').first(); // Get only the main value
                if ($value.length === 0) {
                    console.log(`Found summary card with label "${labelText}" but no value element`);
                    return;
                }
                
                // Convert to string for proper comparison
                const oldValue = $value.text().trim();
                const newValueStr = String(newValue).trim();
                
                // Only update if the value has changed
                if (oldValue !== newValueStr) {
                    console.log(`Updating summary ${labelText} from "${oldValue}" to "${newValueStr}"`);
                    $value.text(newValueStr);
                    
                    // Add a brief highlight effect
                    $card.css('background-color', '#edf9ee');
                    setTimeout(function() {
                        $card.css('background-color', '');
                    }, 1000);
                } else {
                    console.log(`No change needed for summary ${labelText}: ${oldValue}`);
                }
                
                $card.addClass('updated-stats');
                updated = true;
            }
        });
        
        if (!updated) {
            console.log(`No summary card found with label containing "${labelText}"`);
        }
    }
    
    /**
     * Clean up job after completion
     */
    function cleanupJob() {
        // Make AJAX request to clean up completed job
        $.ajax({
            url: imageSqueeze.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imagesqueeze_get_progress',
                security: imageSqueeze.nonce
            },
            success: function() {
                // After cleanup, refresh stats instead of reloading the page
                refreshDashboardStats();
            }
        });
    }
    
    /**
     * Show the progress UI
     */
    function showProgressUI() {
        elements.progressContainer.show();
        elements.optimizationStatus.hide();
    }
    
    /**
     * Disable action buttons
     */
    function disableButtons() {
        elements.optimizeButton.prop('disabled', true);
        if (elements.retryButton.length) {
            elements.retryButton.prop('disabled', true);
        }
    }
    
    /**
     * Enable action buttons
     */
    function enableButtons() {
        elements.optimizeButton.prop('disabled', false);
        if (elements.retryButton.length) {
            elements.retryButton.prop('disabled', false);
        }
    }
    
    /**
     * Reset the UI to initial state
     */
    function resetUI() {
        // Enable buttons
        enableButtons();
        
        // Reset job status
        jobStatus.inProgress = false;
        
        // Stop polling
        stopPolling();
    }
    
    /**
     * Cancel the current optimization job
     */
    function cancelOptimization() {
        if (!jobStatus.inProgress) {
            return; // No job to cancel
        }
        
        // Show loading state on button
        elements.cancelButton.prop('disabled', true)
            .html('<span class="dashicons dashicons-update"></span> ' + 'Cancelling...');
        
        // Send AJAX request to cancel the job
        $.ajax({
            url: imageSqueeze.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imagesqueeze_cancel_job',
                security: imageSqueeze.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Stop polling and processing
                    stopPolling();
                    
                    // Update job status
                    jobStatus.inProgress = false;
                    jobStatus.status = 'cancelled';
                    
                    // Update UI
                    elements.progressContainer.hide();
                    elements.optimizationStatus.show().find('#optimization-status-text').text(jsTranslate.optimizationCancelled);
                    elements.optimizationStatus.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-dismiss');
                    
                    // Update status badge
                    elements.statusBadge.removeClass('in-progress complete').addClass('idle');
                    elements.statusBadge.html('<span class="dashicons dashicons-marker"></span> Ready for Optimization');
                    
                    // Enable buttons
                    enableButtons();
                    
                    // Refresh stats
                    refreshDashboardStats();
                } else {
                    // Show error
                    showNotice(response.data.message || 'Failed to cancel job', 'error');
                    elements.cancelButton.prop('disabled', false)
                        .html('<span class="dashicons dashicons-dismiss"></span> ' + 'Cancel Optimization');
                }
            },
            error: function() {
                // Show error
                showNotice('Failed to communicate with the server', 'error');
                elements.cancelButton.prop('disabled', false)
                    .html('<span class="dashicons dashicons-dismiss"></span> ' + 'Cancel Optimization');
            }
        });
    }
    
    /**
     * Update the summary section to show optimization results
     * @param {Object} data - The stats data from the server
     */
    function updateSummarySection(data) {
        const $summaryContent = $('.imagesqueeze-summary-content');
        const $noSummary = $('.imagesqueeze-no-summary');
        
        // Check if we have the necessary data
        if (!data.last_run_date) {
            console.log('Cannot update summary section: missing last_run_date');
            return;
        }
        
        console.log('Replacing no-summary message with actual summary grid');
        console.log('Last run date from server:', data.last_run_date);
        
        // Create summary grid HTML with special formatting for the date
        const summaryGridHtml = `
            <div class="imagesqueeze-summary-grid">
                <!-- Last Run -->
                <div class="imagesqueeze-summary-stat-card">
                    <div class="summary-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="summary-info">
                        <span class="summary-label">Last Run</span>
                        <span class="summary-value">${data.last_run_date}</span>
                    </div>
                </div>
                
                <!-- Optimized Images -->
                <div class="imagesqueeze-summary-stat-card">
                    <div class="summary-icon success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="summary-info">
                        <span class="summary-label">Optimized Images</span>
                        <span class="summary-value">${data.last_run_optimized}</span>
                        ${parseInt(data.last_run_failed) > 0 ? `
                        <span class="summary-value warning">
                            <span class="dashicons dashicons-warning"></span>
                            Failed: <strong>${data.last_run_failed}</strong>
                        </span>
                        ` : ''}
                    </div>
                </div>
                
                <!-- Space Saved -->
                <div class="imagesqueeze-summary-stat-card">
                    <div class="summary-icon">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                    <div class="summary-info">
                        <span class="summary-label">Space Saved</span>
                        <span class="summary-value">${data.last_run_saved}</span>
                    </div>
                </div>
            </div>
        `;
        
        // Hide the no-summary message
        $noSummary.hide();
        
        // Add the summary grid to the container
        $summaryContent.append(summaryGridHtml);
        
        // Apply a highlight effect to the new content
        const $newGrid = $summaryContent.find('.imagesqueeze-summary-grid');
        $newGrid.css({
            'background-color': '#f0f9ff',
            'transition': 'background-color 1s'
        });
        
        // Remove highlight after a moment
        setTimeout(function() {
            $newGrid.css('background-color', '');
        }, 1500);
        
        console.log('Summary section updated with job results');
    }
    
    // Initialize when DOM is ready
    $(document).ready(init);
    
    // Tab Navigation - Handle both server-side and client-side tab switching
    $(document).ready(function() {
        // Initial setup - make sure the active tab is visible
        const $activeTab = $('.nav-tab-active');
        if ($activeTab.length) {
            const activeTabId = $activeTab.data('tab');
            if (activeTabId) {
                // Hide all tabs except the active one
                $('.tab-pane').hide();
                $('#tab-' + activeTabId).show();
            }
        }
        
        // Allow clicking tabs to both navigate to server and handle client-side tab switching
        $('.nav-tab').on('click', function() {
            // Let the link work normally for page navigation
            // Server-side tab handling will take over on page reload
            return true;
        });
    });
})(jQuery); 