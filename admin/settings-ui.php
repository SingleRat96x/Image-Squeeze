<?php
/**
 * Settings UI functionality
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

/**
 * Register plugin settings.
 */
function image_squeeze_register_settings() {
    // Register settings group
    register_setting(
        'image_squeeze_settings_group',   // Option group
        'imagesqueeze_settings',          // Option name
        [
            'sanitize_callback' => 'image_squeeze_sanitize_settings',
            'default' => [
                'quality' => 80,
                'webp_delivery' => true,
                'retry_on_next' => true,
                'max_output_size_kb' => 0,
                'optimize_on_upload' => false,
            ]
        ]
    );

    // Add settings section
    add_settings_section(
        'image_squeeze_general_section',          // ID
        __('General Settings', 'image-squeeze'),  // Title
        'image_squeeze_general_section_callback', // Callback
        'image_squeeze_settings_page'             // Page
    );
    
    // Add compression quality field
    add_settings_field(
        'quality',                                    // ID
        __('Compression Quality (%)', 'image-squeeze'), // Title
        'image_squeeze_quality_field_callback',       // Callback
        'image_squeeze_settings_page',                // Page
        'image_squeeze_general_section'               // Section
    );
    
    // Add WebP delivery field
    add_settings_field(
        'webp_delivery',                              // ID
        __('Serve WebP Images Automatically', 'image-squeeze'), // Title
        'image_squeeze_webp_delivery_field_callback', // Callback
        'image_squeeze_settings_page',                // Page
        'image_squeeze_general_section'               // Section
    );
    
    // Add auto-retry field
    add_settings_field(
        'retry_on_next',                              // ID
        __('Retry Failed Images Automatically', 'image-squeeze'), // Title
        'image_squeeze_retry_field_callback',         // Callback
        'image_squeeze_settings_page',                // Page
        'image_squeeze_general_section'               // Section
    );
    
    // Add max output size field
    add_settings_field(
        'max_output_size_kb',                           // ID
        __('Maximum Output File Size (KB)', 'image-squeeze'), // Title
        'image_squeeze_max_size_field_callback',        // Callback
        'image_squeeze_settings_page',                  // Page
        'image_squeeze_general_section'                 // Section
    );
    
    // Add auto-optimize on upload field
    add_settings_field(
        'optimize_on_upload',                           // ID
        __('Auto-Optimize on Upload', 'image-squeeze'), // Title
        'image_squeeze_auto_optimize_field_callback',   // Callback
        'image_squeeze_settings_page',                  // Page
        'image_squeeze_general_section'                 // Section
    );
}
add_action('admin_init', 'image_squeeze_register_settings');

/**
 * Sanitize settings input.
 *
 * @param array $input The settings input array.
 * @return array Sanitized settings array.
 */
function image_squeeze_sanitize_settings($input) {
    $output = [];
    
    // Sanitize the quality slider value (ensure it's between 50-100)
    $output['quality'] = isset($input['quality']) ? min(100, max(50, intval($input['quality']))) : 80;
    
    // Sanitize WebP delivery setting
    $output['webp_delivery'] = !empty($input['webp_delivery']);
    
    // Sanitize auto-retry setting
    $output['retry_on_next'] = !empty($input['retry_on_next']);
    
    // Sanitize max output size setting (ensure it's not negative)
    $output['max_output_size_kb'] = isset($input['max_output_size_kb']) ? max(0, intval($input['max_output_size_kb'])) : 0;
    
    // Sanitize auto-optimize on upload setting
    $output['optimize_on_upload'] = !empty($input['optimize_on_upload']);
    
    return $output;
}

/**
 * Render settings section description.
 */
function image_squeeze_general_section_callback() {
    echo '<p>' . esc_html__('Configure general plugin settings below.', 'image-squeeze') . '</p>';
}

/**
 * Render compression quality slider field.
 */
function image_squeeze_quality_field_callback() {
    $settings = get_option('imagesqueeze_settings', []);
    $quality = isset($settings['quality']) ? intval($settings['quality']) : 80;
    ?>
    <div class="quality-slider-container">
        <input 
            type="range" 
            id="quality-slider" 
            name="imagesqueeze_settings[quality]" 
            min="50" 
            max="100" 
            step="1" 
            value="<?php echo esc_attr($quality); ?>" 
            oninput="document.getElementById('quality-value').textContent = this.value"
            aria-valuemin="50"
            aria-valuemax="100"
            aria-valuenow="<?php echo esc_attr($quality); ?>"
            aria-labelledby="quality-slider-label"
        />
        <span class="quality-value-display" id="quality-slider-label">
            <span id="quality-value"><?php echo esc_html($quality); ?></span>%
        </span>
    </div>
    <p class="description">
        <?php esc_html_e('Lower values reduce file size more, but may slightly affect visual quality. 80% is recommended for most websites.', 'image-squeeze'); ?>
    </p>
    <?php
}

/**
 * Render WebP delivery field.
 */
function image_squeeze_webp_delivery_field_callback() {
    $options = get_option('imagesqueeze_settings');
    $checked = isset($options['webp_delivery']) ? $options['webp_delivery'] : true;
    ?>
    <label for="webp_delivery">
        <input 
            type="checkbox" 
            id="webp_delivery" 
            name="imagesqueeze_settings[webp_delivery]" 
            value="1" 
            <?php checked(1, $checked); ?>
        />
        <?php esc_html_e('Serve WebP Images Automatically', 'image-squeeze'); ?>
    </label>
    <p class="description">
        <?php esc_html_e('Automatically serves WebP versions of your images to supported browsers. If the browser doesn\'t support WebP, it will fall back to the original image.', 'image-squeeze'); ?>
    </p>
    <p class="description" style="margin-top: 8px;">
        <?php esc_html_e('You might want to disable this if you have CDN conflicts or theme compatibility issues.', 'image-squeeze'); ?>
    </p>
    <?php
}

/**
 * Render auto-retry field.
 */
function image_squeeze_retry_field_callback() {
    $options = get_option('imagesqueeze_settings');
    $checked = isset($options['retry_on_next']) ? $options['retry_on_next'] : true;
    ?>
    <label for="retry_on_next">
        <input 
            type="checkbox" 
            id="retry_on_next" 
            name="imagesqueeze_settings[retry_on_next]" 
            value="1" 
            <?php checked(1, $checked); ?>
        />
        <?php esc_html_e('Retry Failed Images Automatically', 'image-squeeze'); ?>
    </label>
    <p class="description">
        <?php esc_html_e('Automatically reprocess failed images when starting the next optimization job.', 'image-squeeze'); ?>
    </p>
    <?php
}

/**
 * Render max output size field.
 */
function image_squeeze_max_size_field_callback() {
    $settings = get_option('imagesqueeze_settings', []);
    $max_size = isset($settings['max_output_size_kb']) ? intval($settings['max_output_size_kb']) : 0;
    ?>
    <input 
        type="number" 
        id="max_output_size_kb" 
        name="imagesqueeze_settings[max_output_size_kb]" 
        min="0" 
        value="<?php echo esc_attr($max_size); ?>"
        class="small-text"
        aria-describedby="max-size-description"
    />
    <span><?php esc_html_e('KB', 'image-squeeze'); ?></span>
    <p class="description" id="max-size-description">
        <?php esc_html_e('If set, the plugin will attempt to reduce optimized image size below this limit (while still respecting the selected compression quality).', 'image-squeeze'); ?>
    </p>
    <p class="description">
        <?php esc_html_e('Leave as 0 to disable this feature.', 'image-squeeze'); ?>
    </p>
    <?php
}

/**
 * Render auto-optimize on upload field.
 */
function image_squeeze_auto_optimize_field_callback() {
    $options = get_option('imagesqueeze_settings');
    $checked = isset($options['optimize_on_upload']) ? $options['optimize_on_upload'] : false;
    ?>
    <label for="optimize_on_upload">
        <input 
            type="checkbox" 
            id="optimize_on_upload" 
            name="imagesqueeze_settings[optimize_on_upload]" 
            value="1" 
            <?php checked(1, $checked); ?>
        />
        <?php esc_html_e('Auto-Optimize on Upload', 'image-squeeze'); ?>
    </label>
    <p class="description">
        <?php esc_html_e('Automatically compress and convert new uploads to WebP. Original image (JPG/PNG) will be deleted after successful conversion.', 'image-squeeze'); ?>
    </p>
    <p class="description" style="margin-top: 8px;">
        <?php esc_html_e('This is ideal for WooCommerce or content-heavy sites with frequent uploads.', 'image-squeeze'); ?>
    </p>
    <?php
}

/**
 * Render the settings UI.
 */
function image_squeeze_settings_ui() {
    // Get current settings
    $settings = get_option('imagesqueeze_settings', []);
    $quality = isset($settings['quality']) ? intval($settings['quality']) : 80;
    $webp_enabled = isset($settings['webp_delivery']) ? $settings['webp_delivery'] : true;
    $auto_retry = isset($settings['retry_on_next']) ? $settings['retry_on_next'] : true;
    $max_size = isset($settings['max_output_size_kb']) ? intval($settings['max_output_size_kb']) : 0;
    $auto_optimize = isset($settings['optimize_on_upload']) ? $settings['optimize_on_upload'] : false;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Image Squeeze Settings', 'image-squeeze'); ?></h1>
        
        <form method="post" action="options.php" class="imagesqueeze-settings-form">
            <?php
                settings_fields('image_squeeze_settings_group');
            ?>
            
            <!-- Optimization Settings -->
            <div class="imagesqueeze-settings-section">
                <h2><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Optimization Settings', 'image-squeeze'); ?></h2>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <!-- Quality Slider -->
                        <tr>
                            <th scope="row">
                                <label for="quality-slider"><?php esc_html_e('Compression Quality', 'image-squeeze'); ?></label>
                            </th>
                            <td>
                                <div class="quality-slider-container">
                                    <input 
                                        type="range" 
                                        id="quality-slider" 
                                        name="imagesqueeze_settings[quality]" 
                                        min="50" 
                                        max="100" 
                                        step="1" 
                                        value="<?php echo esc_attr($quality); ?>" 
                                        oninput="document.getElementById('quality-value').textContent = this.value"
                                        aria-valuemin="50"
                                        aria-valuemax="100"
                                        aria-valuenow="<?php echo esc_attr($quality); ?>"
                                        aria-labelledby="quality-slider-label"
                                    />
                                    <span class="quality-value-display" id="quality-slider-label">
                                        <span id="quality-value"><?php echo esc_html($quality); ?></span>%
                                    </span>
                                </div>
                                <p class="description">
                                    <?php esc_html_e('Controls the compression ratio for all optimized images. Lower values reduce file size more, but may slightly affect visual quality. 80% is recommended for most websites.', 'image-squeeze'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Max File Size -->
                        <tr>
                            <th scope="row">
                                <label for="max_output_size_kb"><?php esc_html_e('Maximum Output File Size', 'image-squeeze'); ?></label>
                            </th>
                            <td>
                                <input 
                                    type="number" 
                                    id="max_output_size_kb" 
                                    name="imagesqueeze_settings[max_output_size_kb]" 
                                    min="0" 
                                    value="<?php echo esc_attr($max_size); ?>"
                                    class="small-text"
                                    aria-describedby="max-size-description"
                                />
                                <span><?php esc_html_e('KB', 'image-squeeze'); ?></span>
                                <p class="description" id="max-size-description">
                                    <?php esc_html_e('If set, the plugin will attempt to reduce optimized image size below this limit (while still respecting the selected compression quality).', 'image-squeeze'); ?>
                                </p>
                                <p class="description">
                                    <?php esc_html_e('Leave blank or 0 to disable max file size targeting.', 'image-squeeze'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- WebP Settings -->
            <div class="imagesqueeze-settings-section">
                <h2><span class="dashicons dashicons-images-alt2"></span> <?php esc_html_e('WebP Settings', 'image-squeeze'); ?></h2>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <!-- WebP Delivery -->
                        <tr>
                            <th scope="row">
                                <label for="webp_delivery"><?php esc_html_e('Serve WebP Images', 'image-squeeze'); ?></label>
                            </th>
                            <td>
                                <div class="imagesqueeze-toggle-container">
                                    <label class="imagesqueeze-toggle">
                                        <input 
                                            type="checkbox" 
                                            id="webp_delivery" 
                                            name="imagesqueeze_settings[webp_delivery]" 
                                            value="1" 
                                            <?php checked(1, $webp_enabled); ?>
                                        />
                                        <span class="imagesqueeze-toggle-slider"></span>
                                        <span class="imagesqueeze-toggle-label"><?php esc_html_e('Serve WebP Images Automatically', 'image-squeeze'); ?></span>
                                    </label>
                                </div>
                                <p class="description">
                                    <?php esc_html_e('Automatically serves WebP versions of your images to supported browsers. If the browser doesn\'t support WebP, it will fall back to the original image.', 'image-squeeze'); ?>
                                </p>
                                <p class="description">
                                    <?php esc_html_e('You might want to disable this if you have CDN conflicts or theme compatibility issues.', 'image-squeeze'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Automation Settings -->
            <div class="imagesqueeze-settings-section">
                <h2><span class="dashicons dashicons-update"></span> <?php esc_html_e('Automation Settings', 'image-squeeze'); ?></h2>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <!-- Auto-Optimize on Upload -->
                        <tr>
                            <th scope="row">
                                <label for="optimize_on_upload"><?php esc_html_e('Optimize New Uploads', 'image-squeeze'); ?></label>
                            </th>
                            <td>
                                <div class="imagesqueeze-toggle-container">
                                    <label class="imagesqueeze-toggle">
                                        <input 
                                            type="checkbox" 
                                            id="optimize_on_upload" 
                                            name="imagesqueeze_settings[optimize_on_upload]" 
                                            value="1" 
                                            <?php checked(1, $auto_optimize); ?>
                                        />
                                        <span class="imagesqueeze-toggle-slider"></span>
                                        <span class="imagesqueeze-toggle-label"><?php esc_html_e('Auto-Optimize on Upload', 'image-squeeze'); ?></span>
                                    </label>
                                </div>
                                <p class="description">
                                    <?php esc_html_e('Automatically compress and convert new uploads to WebP. Original image (JPG/PNG) will be deleted after successful conversion.', 'image-squeeze'); ?>
                                </p>
                                <p class="description">
                                    <?php esc_html_e('This is ideal for WooCommerce or content-heavy sites with frequent uploads.', 'image-squeeze'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Auto-Retry Failed Images -->
                        <tr>
                            <th scope="row">
                                <label for="retry_on_next"><?php esc_html_e('Auto-Retry Failed Images', 'image-squeeze'); ?></label>
                            </th>
                            <td>
                                <div class="imagesqueeze-toggle-container">
                                    <label class="imagesqueeze-toggle">
                                        <input 
                                            type="checkbox" 
                                            id="retry_on_next" 
                                            name="imagesqueeze_settings[retry_on_next]" 
                                            value="1" 
                                            <?php checked(1, $auto_retry); ?>
                                        />
                                        <span class="imagesqueeze-toggle-slider"></span>
                                        <span class="imagesqueeze-toggle-label"><?php esc_html_e('Retry Failed Images Automatically', 'image-squeeze'); ?></span>
                                    </label>
                                </div>
                                <p class="description">
                                    <?php esc_html_e('Automatically reprocess failed images when starting the next optimization job.', 'image-squeeze'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="imagesqueeze-save-settings">
                <?php submit_button(__('Save Settings', 'image-squeeze'), 'primary', 'submit', false); ?>
            </div>
        </form>
    </div>
    <?php
} 