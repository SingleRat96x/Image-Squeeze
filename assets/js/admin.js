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
        noticesContainer: null // Will be initialized during setup
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
        // Create notices container if it doesn't exist
        createNoticesContainer();
        
        // Attach event listeners
        elements.optimizeButton.on('click', startOptimization);
        elements.retryButton.on('click', startRetryOptimization);
        elements.cancelButton.on('click', cancelOptimization);
        
        // Check for existing job on page load
        checkExistingJob();
    }
    
    /**
     * Create notices container if it doesn't exist
     */
    function createNoticesContainer() {
        // Check if notices container exists
        if ($('.imagesqueeze-notices').length === 0) {
            // Create and insert the notices container above the progress container
            elements.progressContainer.before('<div class="imagesqueeze-notices"></div>');
        }
        
        // Store reference to the notices container
        elements.noticesContainer = $('.imagesqueeze-notices');
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
                }
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
        // Clear any previous notices
        clearNotices();
        
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
                    showNotice(errorMessage, 'error');
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
        const $notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
        elements.noticesContainer.append($notice);
        
        // Make sure the notice is visible
        elements.noticesContainer.show();
    }
    
    /**
     * Clear all notices
     */
    function clearNotices() {
        if (elements.noticesContainer) {
            elements.noticesContainer.empty().hide();
        }
    }
    
    /**
     * Start polling for job progress
     */
    function startPolling() {
        // Clear any existing timer
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        
        // Set up polling every 2 seconds
        pollTimer = setInterval(pollJobProgress, 2000);
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
     * Poll job progress from the server
     */
    function pollJobProgress() {
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
                    jobStatus.cleanup = data.cleanup_on_next_visit;
                    
                    // Update UI
                    updateProgress(jobStatus.done, jobStatus.total);
                    
                    // If job is complete, stop polling
                    if (data.status === 'completed') {
                        jobStatus.inProgress = false;
                        completeJob();
                    }
                } else {
                    // Error occurred, stop polling
                    stopPolling();
                    showNotice(response.data.message || 'Failed to get job progress', 'error');
                    resetUI();
                }
            },
            error: function() {
                // Communication error, stop polling
                stopPolling();
                showNotice('Failed to communicate with the server', 'error');
                resetUI();
            }
        });
    }
    
    /**
     * Process a batch of images
     */
    function processBatch() {
        if (!jobStatus.inProgress) {
            return; // Don't process if no job is in progress
        }
        
        $.ajax({
            url: imageSqueeze.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imagesqueeze_process_batch',
                security: imageSqueeze.nonce,
                batch_size: 10
            },
            success: function(response) {
                if (response.success) {
                    // Update progress
                    const done = parseInt(response.data.done) || 0;
                    const remaining = parseInt(response.data.remaining) || 0;
                    const total = done + remaining;
                    const status = response.data.status || 'in_progress';
                    
                    jobStatus.done = done;
                    jobStatus.total = total;
                    jobStatus.status = status;
                    
                    updateProgress(done, total);
                    
                    // Check if job is complete
                    if (status === 'completed' || remaining === 0) {
                        jobStatus.inProgress = false;
                        completeJob();
                    } else if (jobStatus.inProgress) {
                        // Continue processing batches with a slight delay
                        setTimeout(processBatch, 1000);
                    }
                } else {
                    // Show error
                    showNotice(response.data.message || 'Failed to process batch', 'error');
                    jobStatus.inProgress = false;
                    resetUI();
                }
            },
            error: function() {
                showNotice('Failed to communicate with the server', 'error');
                jobStatus.inProgress = false;
                resetUI();
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
        
        // Update progress bar with smooth animation
        elements.progressBar.css({
            'width': percentage + '%',
            'transition': 'width 0.5s cubic-bezier(0.4, 0, 0.2, 1)'
        });
        
        // Update progress text
        if (jobStatus.status === 'completed') {
            elements.progressText.html('<span class="dashicons dashicons-yes-alt"></span> ' + 
                                      jsTranslate.optimizationComplete);
            
            // Update screen reader text
            $('#progress-screen-reader-text').text(jsTranslate.optimizationComplete);
            
            // Show success notice if not already shown
            if (elements.noticesContainer && !elements.noticesContainer.find('.notice-success').length) {
                showNotice(jsTranslate.optimizationSuccess, 'success');
            }
        } else {
            const progressHtml = '<span class="dashicons dashicons-update"></span> ' + 
                               jsTranslate.processing.replace('%1$d', done).replace('%2$d', total);
            elements.progressText.html(progressHtml);
            
            // Update screen reader text
            $('#progress-screen-reader-text').text(
                jsTranslate.percentComplete.replace('%1$d', percentage)
            );
        }
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
        
        // Show completion message
        elements.progressText.text('Optimization complete!');
    }
    
    /**
     * Clean up job after completion
     */
    function cleanupJob() {
        // Make AJAX request to get progress (which will trigger cleanup)
        $.ajax({
            url: imageSqueeze.ajaxUrl,
            type: 'POST',
            data: {
                action: 'imagesqueeze_get_progress',
                security: imageSqueeze.nonce
            },
            complete: function() {
                // Refresh the page to show updated stats
                window.location.reload();
            }
        });
    }
    
    /**
     * Show the progress UI
     */
    function showProgressUI() {
        elements.progressContainer.show();
    }
    
    /**
     * Disable action buttons
     */
    function disableButtons() {
        elements.optimizeButton.prop('disabled', true);
        elements.retryButton.prop('disabled', true);
        elements.cancelButton.prop('disabled', true);
    }
    
    /**
     * Enable action buttons
     */
    function enableButtons() {
        elements.optimizeButton.prop('disabled', false);
        elements.retryButton.prop('disabled', false);
        elements.cancelButton.prop('disabled', false);
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
                    
                    // Show cancellation message
                    elements.progressText.html('<span class="dashicons dashicons-dismiss"></span> ' + 
                                             jsTranslate.optimizationCancelled);
                    
                    // Show notice
                    showNotice(jsTranslate.optimizationCancelled, 'info');
                    
                    // Enable buttons
                    enableButtons();
                    
                    // Reload the page after a delay to refresh stats
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
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
    
    // Batch Optimization
    $('#imagesqueeze-optimize-button').on('click', function() {
        var $button = $(this);
        var $status = $('#imagesqueeze-status');
        var $progress = $('#imagesqueeze-progress');
        var $progressBar = $progress.find('.progress-bar');
        
        $button.prop('disabled', true);
        $status.html('<p>Starting batch optimization...</p>');
        $progress.show();
        
        processBatch(1, 0, 0, 0);
        
        function processBatch(page, total, optimized, failed) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'imagesqueeze_batch_optimize',
                    page: page,
                    security: imageSqueeze.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Update progress
                        var processedTotal = optimized + failed + data.processed;
                        var newOptimized = optimized + data.optimized;
                        var newFailed = failed + data.failed;
                        
                        // Update the total if this is page 1
                        if (page === 1) {
                            total = data.total;
                        }
                        
                        // Update progress bar
                        var percentComplete = total > 0 ? Math.round((processedTotal / total) * 100) : 0;
                        $progressBar.css('width', percentComplete + '%');
                        
                        // Update status message
                        $status.html('<p>Processing: ' + processedTotal + ' of ' + total + ' images (' + percentComplete + '%)');
                        $status.append('<p>Optimized: ' + newOptimized + ' | Failed: ' + newFailed + '</p>');
                        
                        // If there are more images to process, continue with the next batch
                        if (data.hasMore) {
                            processBatch(page + 1, total, newOptimized, newFailed);
                        } else {
                            // All done
                            $button.prop('disabled', false);
                            $status.append('<p><strong>Batch optimization complete!</strong></p>');
                            
                            // Reload the page to update the stats
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                        }
                    } else {
                        // Error occurred
                        $button.prop('disabled', false);
                        $status.html('<p class="notice notice-error">Error: ' + response.data + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false);
                    $status.html('<p class="notice notice-error">Error: ' + error + '</p>');
                }
            });
        }
    });
    
    // Retry Failed Images
    $('#imagesqueeze-retry-button').on('click', function() {
        var $button = $(this);
        var $status = $('#imagesqueeze-retry-status');
        
        $button.prop('disabled', true);
        $status.html('<p>Retrying failed images...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'imagesqueeze_retry_failed',
                security: imageSqueeze.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<p class="notice notice-success">Success! Retried ' + response.data.total + ' images. ' +
                                'Optimized: ' + response.data.optimized + ' | Still failed: ' + response.data.failed + '</p>');
                    
                    // Reload the page after a short delay to update stats
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $button.prop('disabled', false);
                    $status.html('<p class="notice notice-error">Error: ' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false);
                $status.html('<p class="notice notice-error">Error: ' + error + '</p>');
            }
        });
    });
    
    // Cleanup Orphaned WebP Files
    $('#imagesqueeze-cleanup-button').on('click', function() {
        var $button = $(this);
        var $results = $('#imagesqueeze-cleanup-results');
        
        $button.prop('disabled', true);
        $results.show().html('<p class="imagesqueeze-cleanup-status">Scanning for orphaned WebP files...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'imagesqueeze_cleanup_orphaned',
                security: imageSqueeze.nonce
            },
            success: function(response) {
                $button.prop('disabled', false);
                
                if (response.success) {
                    var html = '<p class="imagesqueeze-cleanup-status success-message">Cleanup complete!</p>';
                    
                    if (response.data.deleted_count > 0) {
                        html += '<p>Deleted ' + response.data.deleted_count + ' orphaned WebP files.</p>';
                        
                        if (response.data.files && response.data.files.length > 0) {
                            html += '<div class="imagesqueeze-cleanup-files"><p>Deleted files:</p><ul>';
                            
                            for (var i = 0; i < response.data.files.length; i++) {
                                html += '<li>' + response.data.files[i] + '</li>';
                            }
                            
                            html += '</ul></div>';
                        }
                    } else {
                        html += '<p>No orphaned WebP files were found. Your media library is clean!</p>';
                    }
                    
                    $results.html(html);
                } else {
                    $results.html('<p class="notice notice-error">Error: ' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false);
                $results.html('<p class="notice notice-error">Error: ' + error + '</p>');
            }
        });
    });
})(jQuery); 