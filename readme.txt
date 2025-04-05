=== Image Squeeze ===
Contributors: medshi8
Tags: images, optimization, webp, compress, performance
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Optimize and compress images in your WordPress media library to improve site performance.

== Description ==

Image Squeeze is a powerful image optimization plugin that helps improve your website's performance by:

* Compressing JPEG and PNG images to reduce file size
* Converting images to WebP format for modern browsers
* Automatically serving WebP images to compatible browsers
* Managing optimization jobs through a user-friendly dashboard

This plugin works in the background to optimize your existing images and can optionally process new uploads automatically.

= Features =

* Bulk optimization of existing images
* WebP conversion and delivery
* Progress tracking and detailed logs
* Cleanup tools for orphaned WebP files
* Configurable compression quality
* Dashboard with optimization statistics

== Installation ==

1. Upload the `image-squeeze` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the 'Image Squeeze' menu in your WordPress admin panel
4. Click 'Optimize Images Now' to start optimizing your media library

== Frequently Asked Questions ==

= Does this plugin modify my original images? =

No, Image Squeeze keeps your original images intact. It creates WebP versions of your images and serves them only to browsers that support the format.

= Will this work with my CDN? =

Yes, in most cases. If you encounter issues, you can disable automatic WebP delivery in the settings and use your CDN's image optimization features instead.

= What happens if I uninstall the plugin? =

When uninstalled, Image Squeeze removes all of its settings and database entries. WebP images are kept in your media library, so your site will continue to function normally.

== Screenshots ==

1. Dashboard with optimization statistics
2. Settings page for configuring compression options
3. Log page showing optimization history

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release 