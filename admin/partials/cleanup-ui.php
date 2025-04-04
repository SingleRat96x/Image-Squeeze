<?php
/**
 * Cleanup UI Template
 *
 * @package ImageSqueeze
 */

defined('ABSPATH') || exit;
?>

<div class="imagesqueeze-section">
    <h2 class="imagesqueeze-section-title">
        <span class="dashicons dashicons-trash"></span>
        <?php esc_html_e('WebP Cleanup Tool', 'image-squeeze'); ?>
    </h2>
    
    <div class="imagesqueeze-cleanup-card">
        <div class="imagesqueeze-card-header">
            <span class="dashicons dashicons-cleaning"></span>
            <h2><?php esc_html_e('Clean Up Unused WebP Files', 'image-squeeze'); ?></h2>
        </div>
        
        <div class="imagesqueeze-card-content">
            <div class="imagesqueeze-cleanup-description">
                <p>
                    <?php esc_html_e('This tool will scan your uploads directory for "orphaned" WebP files - these are WebP images that were created by Image Squeeze, but their original files (JPG, PNG) no longer exist.', 'image-squeeze'); ?>
                </p>
                <p>
                    <?php esc_html_e('Removing these orphaned files helps keep your server clean and saves disk space.', 'image-squeeze'); ?>
                </p>
            </div>
            
            <div class="imagesqueeze-cleanup-actions">
                <button id="imagesqueeze-cleanup-button" class="button button-primary" aria-label="<?php esc_attr_e('Scan and delete orphaned WebP files', 'image-squeeze'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Scan & Clean Orphaned Files', 'image-squeeze'); ?>
                </button>
            </div>
            
            <div id="imagesqueeze-cleanup-results" class="imagesqueeze-cleanup-results hidden">
                <!-- Results will be populated here via JS -->
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    (function($) {
        // Update the cleanup JS handler to limit displayed files and add tooltip
        $(document).ready(function() {
            var originalHandler = $('#imagesqueeze-cleanup-button').data('events') ? 
                                  $('#imagesqueeze-cleanup-button').data('events').click[0].handler : null;
            
            if(!originalHandler) return; // No handler attached yet
            
            $('#imagesqueeze-cleanup-button').off('click').on('click', function() {
                var $button = $(this);
                var $results = $('#imagesqueeze-cleanup-results');
                
                $button.prop('disabled', true);
                $results.removeClass('hidden').html('<div class="imagesqueeze-cleanup-status in-progress"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php echo esc_js(__('Scanning for orphaned WebP files...', 'image-squeeze')); ?></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'imagesqueeze_cleanup_orphaned',
                        security: imageSqueeze.nonce
                    },
                    success: function(response) {
                        // Always re-enable the button
                        $button.prop('disabled', false);
                        
                        if (response.success) {
                            var html = '';
                            
                            if (response.data.deleted_count > 0) {
                                html += '<div class="imagesqueeze-cleanup-summary success">';
                                html += '<span class="dashicons dashicons-yes-alt"></span>';
                                html += '<div class="cleanup-summary-content">';
                                html += '<div class="cleanup-summary-title"><?php echo esc_js(__('Cleanup complete!', 'image-squeeze')); ?></div>';
                                html += '<div class="cleanup-summary-text">' + response.data.deleted_count + ' <?php echo esc_js(__('orphaned WebP files have been removed.', 'image-squeeze')); ?></div>';
                                html += '</div></div>';
                                
                                if (response.data.files && response.data.files.length > 0) {
                                    // Only show first 10 files
                                    var filesToShow = response.data.files.slice(0, 10);
                                    html += '<div class="imagesqueeze-cleanup-filelist-container">';
                                    html += '<h3 class="imagesqueeze-cleanup-filelist-title"><span class="dashicons dashicons-media-default"></span> <?php echo esc_js(__('Files Removed:', 'image-squeeze')); ?></h3>';
                                    html += '<ul class="imagesqueeze-cleanup-filelist">';
                                    
                                    for (var i = 0; i < filesToShow.length; i++) {
                                        html += '<li>' + filesToShow[i] + '</li>';
                                    }
                                    
                                    html += '</ul>';
                                    
                                    // Add tooltip if more files were deleted than shown
                                    if (response.data.files.length > 10) {
                                        html += '<div class="imagesqueeze-cleanup-tooltip"><?php echo esc_js(__('Only showing first 10 files for brevity.', 'image-squeeze')); ?></div>';
                                    }
                                    
                                    html += '</div>';
                                }
                            } else {
                                html += '<div class="imagesqueeze-cleanup-summary empty">';
                                html += '<span class="dashicons dashicons-yes-alt"></span>';
                                html += '<div class="cleanup-summary-content">';
                                html += '<div class="cleanup-summary-title"><?php echo esc_js(__('All Clean!', 'image-squeeze')); ?></div>';
                                html += '<div class="cleanup-summary-text"><?php echo esc_js(__('No orphaned WebP files were found. Your media library is clean!', 'image-squeeze')); ?></div>';
                                html += '</div></div>';
                            }
                            
                            $results.html(html);
                        } else {
                            $results.html('<div class="imagesqueeze-cleanup-summary error"><span class="dashicons dashicons-warning"></span><div class="cleanup-summary-content"><div class="cleanup-summary-title"><?php echo esc_js(__('Error Occurred', 'image-squeeze')); ?></div><div class="cleanup-summary-text">' + response.data + '</div></div></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $button.prop('disabled', false);
                        $results.html('<div class="imagesqueeze-cleanup-summary error"><span class="dashicons dashicons-warning"></span><div class="cleanup-summary-content"><div class="cleanup-summary-title"><?php echo esc_js(__('Error Occurred', 'image-squeeze')); ?></div><div class="cleanup-summary-text">' + error + '</div></div></div>');
                    }
                });
            });
        });
    })(jQuery);
</script> 