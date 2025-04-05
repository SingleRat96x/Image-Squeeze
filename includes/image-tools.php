<?php
/**
 * Image Tools functionality
 *
 * @package ImageSqueeze
 */

// Prevent direct access to this file.
defined('ABSPATH') || exit;

/**
 * Process an image attachment to create WebP versions.
 *
 * @param int $attachment_id The WordPress attachment ID.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function image_squeeze_process_image( $attachment_id ) {
    // Check if the attachment exists and is an image
    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return new WP_Error( 'invalid_image', __( 'The attachment is not a valid image.', 'image-squeeze' ) );
    }

    // Get attachment metadata
    $metadata = wp_get_attachment_metadata( $attachment_id );
    if ( ! $metadata || empty( $metadata['file'] ) ) {
        return new WP_Error( 'invalid_metadata', __( 'Could not retrieve image metadata.', 'image-squeeze' ) );
    }

    // Get mime type
    $mime_type = get_post_mime_type( $attachment_id );
    
    // Check if it's a supported type (JPEG or PNG)
    if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/png' ), true ) ) {
        return new WP_Error( 'unsupported_type', 
            /* translators: %s is the MIME type of the unsupported image */
            sprintf( __( 'Image type %s is not supported for WebP conversion.', 'image-squeeze' ), $mime_type )
        );
    }

    // Determine which image processing library to use
    $image_processor = _image_squeeze_get_processor();
    if ( is_wp_error( $image_processor ) ) {
        return $image_processor;
    }

    // Get upload directory info
    $upload_dir = wp_upload_dir();
    $base_dir = $upload_dir['basedir'];
    
    // Get the full path to the original image
    $file_path = $base_dir . '/' . $metadata['file'];
    $file_dir = dirname( $file_path );
    
    // Initialize tracking array for WebP sizes
    $webp_sizes = array();
    $total_saved_bytes = 0;
    
    // Process the full-size image
    $webp_result = _image_squeeze_convert_to_webp( $file_path, $image_processor );
    if ( is_wp_error( $webp_result ) ) {
        // Store error information
        update_post_meta( $attachment_id, '_imagesqueeze_status', 'failed' );
        update_post_meta( $attachment_id, '_imagesqueeze_error_message', $webp_result->get_error_message() );
        update_post_meta( $attachment_id, '_imagesqueeze_last_attempt', time() );
        return $webp_result;
    }
    
    // Check for array result with saved bytes information (new format)
    if (is_array($webp_result) && isset($webp_result['success']) && $webp_result['success']) {
        $webp_sizes['full'] = true;
        $total_saved_bytes += isset($webp_result['saved_bytes']) ? $webp_result['saved_bytes'] : 0;
    } else {
        // Legacy true response
        $webp_sizes['full'] = true;
    }
    
    // Process each image size
    if ( ! empty( $metadata['sizes'] ) ) {
        foreach ( $metadata['sizes'] as $size_name => $size_data ) {
            if ( empty( $size_data['file'] ) ) {
                continue;
            }
            
            $size_file_path = $file_dir . '/' . $size_data['file'];
            $size_result = _image_squeeze_convert_to_webp( $size_file_path, $image_processor );
            
            if (is_array($size_result) && isset($size_result['success']) && $size_result['success']) {
                $webp_sizes[$size_name] = true;
                $total_saved_bytes += isset($size_result['saved_bytes']) ? $size_result['saved_bytes'] : 0;
            } elseif (!is_wp_error($size_result)) {
                // Legacy true response
                $webp_sizes[$size_name] = true;
            }
        }
    }
    
    // Clear any error status if we got here
    delete_post_meta( $attachment_id, '_imagesqueeze_status' );
    delete_post_meta( $attachment_id, '_imagesqueeze_error_message' );
    
    // Store success metadata
    update_post_meta( $attachment_id, '_imagesqueeze_optimized', 1 );
    update_post_meta( $attachment_id, '_imagesqueeze_webp_sizes', $webp_sizes );
    update_post_meta( $attachment_id, '_imagesqueeze_last_attempt', time() );
    update_post_meta( $attachment_id, '_imagesqueeze_saved_bytes', $total_saved_bytes );
    
    // Update global saved bytes (used by job runner)
    $GLOBALS['imagesqueeze_job_saved_bytes'] = isset($GLOBALS['imagesqueeze_job_saved_bytes']) 
        ? $GLOBALS['imagesqueeze_job_saved_bytes'] + $total_saved_bytes 
        : $total_saved_bytes;
    
    return true;
}

/**
 * Determine which image processing library to use.
 *
 * @return string|WP_Error 'gd', 'imagick', or WP_Error if none available.
 */
function _image_squeeze_get_processor() {
    // Check for Imagick first (usually better quality)
    if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
        return 'imagick';
    }
    
    // Check for GD as fallback
    if ( extension_loaded( 'gd' ) && function_exists( 'imagewebp' ) ) {
        return 'gd';
    }
    
    // No supported library available
    return new WP_Error(
        'no_image_processor',
        __( 'No compatible image processing library found. Please install Imagick or GD with WebP support.', 'image-squeeze' )
    );
}

/**
 * Convert an image to WebP format.
 *
 * @param string $source_path Path to source image.
 * @param string $processor Image processor to use ('gd' or 'imagick').
 * @return bool|WP_Error|array True on success (legacy), WP_Error on failure, or array with saved bytes on success.
 */
function _image_squeeze_convert_to_webp( $source_path, $processor ) {
    // Check if source file exists
    if ( ! file_exists( $source_path ) || ! is_readable( $source_path ) ) {
        return new WP_Error( 'source_not_found', __( 'Source image file not found or not readable.', 'image-squeeze' ) );
    }
    
    // Check if destination directory is writable using WP_Filesystem
    $dest_dir = dirname( $source_path );
    
    // Initialize the WordPress Filesystem
    global $wp_filesystem;
    if ( ! $wp_filesystem ) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
    }
    
    // Check if destination is writable with WP_Filesystem
    if ( ! $wp_filesystem->is_writable( $dest_dir ) ) {
        return new WP_Error( 'dest_not_writable', __( 'Destination directory is not writable.', 'image-squeeze' ) );
    }
    
    // Define WebP destination path (append .webp to original filename)
    $dest_path = $source_path . '.webp';
    
    // Get original file size
    $original_size = filesize( $source_path );
    
    // Quality setting for conversion
    $quality = 80;
    
    // Convert using the appropriate processor
    if ( $processor === 'imagick' ) {
        try {
            $image = new Imagick( $source_path );
            
            // Set compression quality
            $image->setImageCompressionQuality( $quality );
            
            // For PNG, handle transparency
            if ( $image->getImageFormat() === 'PNG' ) {
                $image->setOption( 'webp:lossless', 'true' );
            }
            
            // Write WebP image
            $result = $image->writeImage( $dest_path );
            $image->clear();
            $image->destroy();
            
            if ( ! $result ) {
                return new WP_Error( 'webp_conversion_failed', __( 'Failed to create WebP image with Imagick.', 'image-squeeze' ) );
            }
        } catch ( Exception $e ) {
            return new WP_Error( 'imagick_error', $e->getMessage() );
        }
    } elseif ( $processor === 'gd' ) {
        // Get image type
        $image_info = getimagesize( $source_path );
        if ( ! $image_info ) {
            return new WP_Error( 'invalid_image', __( 'Could not determine image dimensions.', 'image-squeeze' ) );
        }
        
        $image_type = $image_info[2];
        
        // Create image resource based on type
        switch ( $image_type ) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg( $source_path );
                break;
                
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng( $source_path );
                
                // Handle transparency for PNG
                imagepalettetotruecolor( $image );
                imagealphablending( $image, true );
                imagesavealpha( $image, true );
                break;
                
            default:
                return new WP_Error( 'unsupported_type', __( 'Unsupported image type for GD processing.', 'image-squeeze' ) );
        }
        
        if ( ! $image ) {
            return new WP_Error( 'image_creation_failed', __( 'Failed to create image resource.', 'image-squeeze' ) );
        }
        
        // Convert to WebP
        $result = imagewebp( $image, $dest_path, $quality );
        imagedestroy( $image );
        
        if ( ! $result ) {
            return new WP_Error( 'webp_conversion_failed', __( 'Failed to create WebP image with GD.', 'image-squeeze' ) );
        }
    } else {
        return new WP_Error( 'invalid_processor', __( 'Invalid image processor specified.', 'image-squeeze' ) );
    }
    
    // Verify the WebP file was created
    if ( ! file_exists( $dest_path ) ) {
        return new WP_Error( 'webp_missing', __( 'WebP file was not created.', 'image-squeeze' ) );
    }
    
    // Get WebP file size and calculate savings
    $webp_size = filesize( $dest_path );
    $saved_bytes = $original_size - $webp_size;
    
    // Return success with saved bytes information
    return array(
        'success' => true,
        'saved_bytes' => max( 0, $saved_bytes ), // Ensure no negative values
        'original_size' => $original_size,
        'webp_size' => $webp_size
    );
} 