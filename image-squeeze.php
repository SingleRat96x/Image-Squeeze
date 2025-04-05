<?php
/**
 * Plugin Name: Image Squeeze
 * Plugin URI: 
 * Description: Optimize and compress images to improve site performance.
 * Version: 1.0.0
 * Author: 
 * Author URI: 
 * Text Domain: image-squeeze
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

// Define plugin constants.
define('IMAGESQUEEZE_VERSION', '1.0.0');
define('IMAGESQUEEZE_PATH', plugin_dir_path(__FILE__));
define('IMAGESQUEEZE_URL', plugin_dir_url(__FILE__));

// Include required files.
require_once IMAGESQUEEZE_PATH . 'includes/job-runner.php';
require_once IMAGESQUEEZE_PATH . 'includes/job-manager.php';
require_once IMAGESQUEEZE_PATH . 'includes/image-tools.php';
require_once IMAGESQUEEZE_PATH . 'includes/logger.php';
require_once IMAGESQUEEZE_PATH . 'includes/cleanup.php';
require_once IMAGESQUEEZE_PATH . 'includes/webp-serving.php';
require_once IMAGESQUEEZE_PATH . 'includes/cleanup-handler.php';
require_once IMAGESQUEEZE_PATH . 'includes/upload-handler.php';

// Include admin UI files if in admin.
if (is_admin()) {
    require_once IMAGESQUEEZE_PATH . 'admin/admin-ui.php';
}

/**
 * Plugin activation function.
 *
 * Injects WebP rules into .htaccess if the server is Apache.
 */
function image_squeeze_activate() {
    // Check server compatibility
    $server_software = sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'] ?? ''));
    // Only proceed if server is Apache.
    if (stripos($server_software, 'apache') !== false) {
        // WebP rules to inject.
        $webp_rules = "
# BEGIN Image Squeeze WebP Rules
<IfModule mod_mime.c>
    AddType image/webp .webp
</IfModule>

<IfModule mod_headers.c>
  <FilesMatch \"\\.(jpe?g|png)$\">
    Header append Vary Accept
  </FilesMatch>
</IfModule>
# END Image Squeeze WebP Rules
";

        // .htaccess no longer rewrites .jpg or .png to .webp.
        // All WebP delivery is handled via WordPress filters in the plugin.

        // Get uploads directory path.
        $upload_dir = wp_upload_dir();
        $htaccess_path = $upload_dir['basedir'] . '/.htaccess';

        // Create or modify .htaccess file.
        if (file_exists($htaccess_path)) {
            // Check if our rules are already in the file.
            $htaccess_content = file_get_contents($htaccess_path);
            if (strpos($htaccess_content, '# BEGIN Image Squeeze WebP Rules') === false) {
                // Create a backup before modifying.
                copy($htaccess_path, $htaccess_path . '.imagesqueeze-backup');
                // Append our rules.
                file_put_contents($htaccess_path, $htaccess_content . $webp_rules);
            } else {
                // Rules exist, but might be old rewrite rules. Update them.
                $pattern = '/# BEGIN Image Squeeze WebP Rules.*?# END Image Squeeze WebP Rules\s*/s';
                $htaccess_content = preg_replace($pattern, $webp_rules, $htaccess_content);
                file_put_contents($htaccess_path, $htaccess_content);
            }
        } else {
            // Create new .htaccess file with our rules.
            file_put_contents($htaccess_path, $webp_rules);
        }
    }
}

/**
 * Plugin deactivation function.
 *
 * Removes WebP rules from .htaccess if the server is Apache.
 */
function image_squeeze_deactivate() {
    // Check server compatibility
    $server_software = sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'] ?? ''));
    // Only proceed if server is Apache.
    if (stripos($server_software, 'apache') !== false) {
        // Get uploads directory path.
        $upload_dir = wp_upload_dir();
        $htaccess_path = $upload_dir['basedir'] . '/.htaccess';

        if (file_exists($htaccess_path)) {
            // Read the current content.
            $htaccess_content = file_get_contents($htaccess_path);
            
            // Only proceed if our rules are in the file.
            if (strpos($htaccess_content, '# BEGIN Image Squeeze WebP Rules') !== false) {
                // Create a backup before modifying.
                copy($htaccess_path, $htaccess_path . '.imagesqueeze-deactivate-backup');
                
                // Remove our rules (includes whitespace).
                $pattern = '/\s*# BEGIN Image Squeeze WebP Rules.*?# END Image Squeeze WebP Rules\s*/s';
                $htaccess_content = preg_replace($pattern, '', $htaccess_content);
                
                // Save the modified content.
                file_put_contents($htaccess_path, $htaccess_content);
            }
        }
    }
}

/**
 * Initialize admin features and check for job recovery.
 */
function image_squeeze_admin_init() {
    // Check for and recover any stuck or abandoned jobs
    if (function_exists('image_squeeze_check_and_recover_job')) {
        image_squeeze_check_and_recover_job();
    }
}

// Register activation and deactivation hooks.
register_activation_hook(__FILE__, 'image_squeeze_activate');
register_deactivation_hook(__FILE__, 'image_squeeze_deactivate');

// Register admin initialization hook to check for job recovery
add_action('admin_init', 'image_squeeze_admin_init'); 