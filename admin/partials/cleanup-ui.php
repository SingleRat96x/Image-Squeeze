<?php
/**
 * Cleanup UI Template
 *
 * @package ImageSqueeze
 */

defined('ABSPATH') || exit;
?>

<div class="imagesqueeze-cleanup-container">
    <h2>
        <span class="dashicons dashicons-cleaning" aria-hidden="true"></span>
        <?php esc_html_e('Clean Up Unused WebP Files', 'image-squeeze'); ?>
    </h2>
    
    <div class="description">
        <p>
            <?php esc_html_e('This tool will scan your uploads directory for "orphaned" WebP files - these are WebP images that were created by Image Squeeze, but their original files (JPG, PNG) no longer exist.', 'image-squeeze'); ?>
        </p>
        <p>
            <?php esc_html_e('Removing these orphaned files helps keep your server clean and saves disk space.', 'image-squeeze'); ?>
        </p>
    </div>
    
    <div class="imagesqueeze-actions-card">
        <button id="imagesqueeze-cleanup-button" class="button button-primary" aria-label="<?php esc_attr_e('Scan and delete orphaned WebP files', 'image-squeeze'); ?>">
            <span class="dashicons dashicons-cleaning" aria-hidden="true"></span>
            <?php esc_html_e('Clean Up Orphaned Files', 'image-squeeze'); ?>
        </button>
    </div>
    
    <div id="imagesqueeze-cleanup-results" style="display: none;" class="imagesqueeze-cleanup-results">
        <!-- Results will be populated here via JS -->
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
                $results.show().html('<p class="imagesqueeze-cleanup-status"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php echo esc_js(__('Scanning for orphaned WebP files...', 'image-squeeze')); ?></p>');
                
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
                            var html = '<p class="imagesqueeze-cleanup-status success-message"><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php echo esc_js(__('Cleanup complete!', 'image-squeeze')); ?></p>';
                            
                            if (response.data.deleted_count > 0) {
                                html += '<p><span class="dashicons dashicons-trash" aria-hidden="true"></span> <?php echo esc_js(__('Deleted', 'image-squeeze')); ?> ' + response.data.deleted_count + ' <?php echo esc_js(__('orphaned WebP files.', 'image-squeeze')); ?></p>';
                                
                                if (response.data.files && response.data.files.length > 0) {
                                    // Only show first 10 files
                                    var filesToShow = response.data.files.slice(0, 10);
                                    html += '<div class="imagesqueeze-cleanup-files"><p><?php echo esc_js(__('Files removed:', 'image-squeeze')); ?></p><ul>';
                                    
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
                                html += '<div class="notice notice-success inline"><p><?php echo esc_js(__('No orphaned WebP files were found. Your media library is clean!', 'image-squeeze')); ?></p></div>';
                            }
                            
                            $results.html(html);
                        } else {
                            $results.html('<div class="notice notice-error inline"><p><?php echo esc_js(__('Error:', 'image-squeeze')); ?> ' + response.data + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $button.prop('disabled', false);
                        $results.html('<div class="notice notice-error inline"><p><?php echo esc_js(__('Error:', 'image-squeeze')); ?> ' + error + '</p></div>');
                    }
                });
            });
        });
    })(jQuery);
</script> 