/**
 * ImageSqueeze Cleanup UI functionality
 *
 * @package ImageSqueeze
 */

(function($) {
    $(document).ready(function() {
        const $cleanupButton = $('#imagesqueeze-cleanup-button');
        const $resultsContainer = $('#imagesqueeze-cleanup-results');
        
        // Exit if we don't have the cleanup button
        if (!$cleanupButton.length || !$resultsContainer.length) {
            return;
        }
        
        // Handle cleanup button click
        $cleanupButton.on('click', function() {
            // Disable button and show loading state
            $cleanupButton.prop('disabled', true);
            
            // Show loading message with spinner
            $resultsContainer.removeClass('hidden').html(
                '<div class="imagesqueeze-cleanup-status in-progress">' +
                '<span class="dashicons dashicons-update" aria-hidden="true"></span> ' + 
                imageSqueeze.strings.scanningForOrphaned +
                '</div>'
            );
            
            // Make AJAX request to cleanup
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'imagesqueeze_cleanup_orphaned',
                    security: imageSqueeze.nonce
                },
                success: function(response) {
                    // Always re-enable the button
                    $cleanupButton.prop('disabled', false);
                    
                    if (response.success) {
                        let html = '';
                        
                        if (response.data.deleted_count > 0) {
                            // Show success message with count
                            html += '<div class="imagesqueeze-cleanup-summary success">';
                            html += '<span class="dashicons dashicons-yes-alt"></span>';
                            html += '<div class="cleanup-summary-content">';
                            html += '<div class="cleanup-summary-title">' + imageSqueeze.strings.cleanupComplete + '</div>';
                            html += '<div class="cleanup-summary-text">' + response.data.deleted_count + ' ' + imageSqueeze.strings.orphanedFilesRemoved + '</div>';
                            html += '</div></div>';
                            
                            // Show list of files if available
                            if (response.data.files && response.data.files.length > 0) {
                                // Only show first 10 files
                                const filesToShow = response.data.files.slice(0, 10);
                                html += '<div class="imagesqueeze-cleanup-filelist-container">';
                                html += '<h3 class="imagesqueeze-cleanup-filelist-title"><span class="dashicons dashicons-media-default"></span> ' + imageSqueeze.strings.filesRemoved + '</h3>';
                                html += '<ul class="imagesqueeze-cleanup-filelist">';
                                
                                for (let i = 0; i < filesToShow.length; i++) {
                                    html += '<li>' + filesToShow[i] + '</li>';
                                }
                                
                                html += '</ul>';
                                
                                // Add tooltip if more files were deleted than shown
                                if (response.data.files.length > 10) {
                                    html += '<div class="imagesqueeze-cleanup-tooltip">' + imageSqueeze.strings.showingFirstTen + '</div>';
                                }
                                
                                html += '</div>';
                            }
                        } else {
                            // Show "all clean" message
                            html += '<div class="imagesqueeze-cleanup-summary empty">';
                            html += '<span class="dashicons dashicons-yes-alt"></span>';
                            html += '<div class="cleanup-summary-content">';
                            html += '<div class="cleanup-summary-title">' + imageSqueeze.strings.allClean + '</div>';
                            html += '<div class="cleanup-summary-text">' + imageSqueeze.strings.noOrphanedFiles + '</div>';
                            html += '</div></div>';
                        }
                        
                        $resultsContainer.html(html);
                    } else {
                        // Show error message
                        showErrorMessage(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    // Re-enable button and show error
                    $cleanupButton.prop('disabled', false);
                    showErrorMessage(error);
                }
            });
        });
        
        /**
         * Display error message in the results container
         * 
         * @param {string} errorText The error message text
         */
        function showErrorMessage(errorText) {
            const html = '<div class="imagesqueeze-cleanup-summary error">' +
                         '<span class="dashicons dashicons-warning"></span>' +
                         '<div class="cleanup-summary-content">' +
                         '<div class="cleanup-summary-title">' + imageSqueeze.strings.errorOccurred + '</div>' +
                         '<div class="cleanup-summary-text">' + errorText + '</div>' +
                         '</div></div>';
            
            $resultsContainer.html(html);
        }
    });
})(jQuery); 