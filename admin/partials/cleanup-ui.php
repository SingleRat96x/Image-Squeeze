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
        <?php esc_html_e('WebP Cleanup Tool', 'imagesqueeze'); ?>
    </h2>
    
    <div class="imagesqueeze-cleanup-card">
        <div class="imagesqueeze-card-header">
            <span class="dashicons dashicons-cleaning"></span>
            <h2><?php esc_html_e('Clean Up Unused WebP Files', 'imagesqueeze'); ?></h2>
        </div>
        
        <div class="imagesqueeze-card-content">
            <div class="imagesqueeze-cleanup-description">
                <p>
                    <?php esc_html_e('This tool will scan your uploads directory for "orphaned" WebP files - these are WebP images that were created by Image Squeeze, but their original files (JPG, PNG) no longer exist.', 'imagesqueeze'); ?>
                </p>
                <p>
                    <?php esc_html_e('Removing these orphaned files helps keep your server clean and saves disk space.', 'imagesqueeze'); ?>
                </p>
            </div>
            
            <div class="imagesqueeze-cleanup-actions">
                <button id="imagesqueeze-cleanup-button" class="button button-primary" aria-label="<?php esc_attr_e('Scan and delete orphaned WebP files', 'imagesqueeze'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Scan & Clean Orphaned Files', 'imagesqueeze'); ?>
                </button>
            </div>
            
            <div id="imagesqueeze-cleanup-results" class="imagesqueeze-cleanup-results hidden">
                <!-- Results will be populated here via JS -->
            </div>
        </div>
    </div>
</div>

<!-- Cleanup JavaScript has been moved to cleanup-ui.js and is properly enqueued in the admin JS --> 