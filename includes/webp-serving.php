<?php
/**
 * WebP Serving functionality
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

/**
 * Initialize WebP serving functionality.
 * 
 * This function checks if Apache with proper modules is available for .htaccess rules.
 * If not, it falls back to PHP-based filtering.
 */
function image_squeeze_init_webp_serving() {
    // Check if we're already using the filter method
    $using_filters = get_option( 'imagesqueeze_using_filters', false );
    
    // If we're already using filters, hook them up
    if ( $using_filters ) {
        add_filter( 'wp_get_attachment_image_src', 'image_squeeze_filter_image_src', 10, 4 );
        add_filter( 'wp_calculate_image_srcset', 'image_squeeze_filter_image_srcset', 10, 5 );
        return;
    }
    
    // If not using filters, check if we're on Apache
    $is_apache = ( isset( $_SERVER['SERVER_SOFTWARE'] ) && stripos( $_SERVER['SERVER_SOFTWARE'], 'apache' ) !== false );
    
    // If not Apache, fall back to PHP filters
    if ( ! $is_apache ) {
        update_option( 'imagesqueeze_using_filters', true, false );
        add_filter( 'wp_get_attachment_image_src', 'image_squeeze_filter_image_src', 10, 4 );
        add_filter( 'wp_calculate_image_srcset', 'image_squeeze_filter_image_srcset', 10, 5 );
    }
}

/**
 * Filter for wp_get_attachment_image_src to provide WebP versions when available.
 *
 * @param array|false  $image         Array of image data, or false if no image is available.
 * @param int          $attachment_id Image attachment ID.
 * @param string|array $size          Requested size. Image size or array of width and height values.
 * @param bool         $icon          Whether the image should be treated as an icon.
 * @return array|false Modified image data.
 */
function image_squeeze_filter_image_src( $image, $attachment_id, $size, $icon ) {
    // If no image or icon mode, return as is
    if ( ! $image || $icon ) {
        return $image;
    }
    
    // Check if the browser supports WebP
    if ( ! image_squeeze_browser_supports_webp() ) {
        return $image;
    }
    
    // Check if this image has been optimized
    $optimized = get_post_meta( $attachment_id, '_imagesqueeze_optimized', true );
    if ( empty( $optimized ) ) {
        return $image;
    }
    
    // Get WebP sizes data
    $webp_sizes = get_post_meta( $attachment_id, '_imagesqueeze_webp_sizes', true );
    if ( empty( $webp_sizes ) || ! is_array( $webp_sizes ) ) {
        return $image;
    }
    
    // For named sizes like 'thumbnail', 'medium', etc.
    $size_key = $size;
    if ( is_array( $size ) ) {
        // For custom sizes, we'll use 'full' as fallback
        $size_key = 'full';
    }
    
    // Check if this specific size has a WebP version
    if ( empty( $webp_sizes[$size_key] ) ) {
        return $image;
    }
    
    // Modify the URL to point to the WebP version
    $image[0] = $image[0] . '.webp';
    
    return $image;
}

/**
 * Filter for wp_calculate_image_srcset to provide WebP versions when available.
 *
 * @param array  $sources       Array of image sources.
 * @param array  $size_array    Requested size.
 * @param string $image_src     Image URL.
 * @param array  $image_meta    Image metadata.
 * @param int    $attachment_id Image attachment ID.
 * @return array Modified array of image sources.
 */
function image_squeeze_filter_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
    // If no sources or no attachment ID, return as is
    if ( empty( $sources ) || empty( $attachment_id ) ) {
        return $sources;
    }
    
    // Check if the browser supports WebP
    if ( ! image_squeeze_browser_supports_webp() ) {
        return $sources;
    }
    
    // Check if this image has been optimized
    $optimized = get_post_meta( $attachment_id, '_imagesqueeze_optimized', true );
    if ( empty( $optimized ) ) {
        return $sources;
    }
    
    // Get WebP sizes data
    $webp_sizes = get_post_meta( $attachment_id, '_imagesqueeze_webp_sizes', true );
    if ( empty( $webp_sizes ) || ! is_array( $webp_sizes ) ) {
        return $sources;
    }
    
    // Modify each source URL to use WebP if available
    foreach ( $sources as $width => $source ) {
        // Find the size name from the URL
        $url = $source['url'];
        $size_name = 'full';
        
        // Try to determine the size name from URL pattern
        foreach ( $image_meta['sizes'] as $size => $size_data ) {
            if ( ! empty( $size_data['file'] ) && strpos( $url, $size_data['file'] ) !== false ) {
                $size_name = $size;
                break;
            }
        }
        
        // Check if this specific size has a WebP version
        if ( ! empty( $webp_sizes[$size_name] ) ) {
            // Update URL to WebP version
            $sources[$width]['url'] = $url . '.webp';
        }
    }
    
    return $sources;
}

/**
 * Check if the current browser supports WebP images.
 *
 * @return bool True if the browser supports WebP, false otherwise.
 */
function image_squeeze_browser_supports_webp() {
    // Check Accept header for image/webp
    if ( isset( $_SERVER['HTTP_ACCEPT'] ) && strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false ) {
        return true;
    }
    
    // Check User-Agent for known WebP supporting browsers (less reliable)
    if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        // Chrome 9+, Opera 12+, Firefox 65+
        if ( 
            ( strpos( $user_agent, 'Chrome/' ) !== false && preg_match( '/Chrome\/([0-9]+)/', $user_agent, $matches ) && (int) $matches[1] >= 9 ) ||
            ( strpos( $user_agent, 'Opera/' ) !== false ) ||
            ( strpos( $user_agent, 'Firefox/' ) !== false && preg_match( '/Firefox\/([0-9]+)/', $user_agent, $matches ) && (int) $matches[1] >= 65 )
        ) {
            return true;
        }
    }
    
    return false;
}

// Initialize WebP serving on plugins loaded
add_action( 'plugins_loaded', 'image_squeeze_init_webp_serving' ); 