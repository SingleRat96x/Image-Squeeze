=== Image Squeeze ===
Contributors: medshi8
Tags: image optimization, webp, image compression, compress images, performance
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Image Squeeze is a smart and modern WordPress image optimization plugin that compresses, converts, and serves WebP images to speed up your site and improve Core Web Vitals.

== Description ==

**Boost your website speed and SEO with Image Squeeze – the ultimate image optimization plugin for WordPress.**

Image Squeeze helps you automatically compress images and convert them to WebP format, reducing file sizes while maintaining high visual quality. With an intuitive dashboard, powerful bulk optimization tools, and support for modern browser formats, you can improve page speed and performance in just a few clicks.

= Highlights =

✅ Compress JPEG, PNG, and convert to WebP  
✅ Bulk optimize your entire WordPress media library  
✅ Serve WebP images to supported browsers automatically  
✅ Customize image quality and target output size  
✅ Retry failed images and clean up orphaned files  
✅ Logs and dashboard with real-time compression stats  

Whether you're running a WooCommerce store, a blog, or a portfolio site — Image Squeeze ensures faster loading times, better user experience, and improved SEO.

= Key Features =

* **Automatic Compression on Upload:** Reduce image size as you upload them to the Media Library.
* **Bulk Optimization:** Optimize existing images with a single click.
* **Smart WebP Conversion:** Automatically creates and serves WebP versions for supported browsers.
* **Lossy Compression Control:** Choose your ideal compression quality (e.g. 80%).
* **Size Targeting (KB):** Define a max output size for images to help you meet performance budgets.
* **Retry Failed Jobs:** Reprocess images that previously failed compression.
* **Detailed Logs:** View your optimization history and saved space over time.
* **Orphaned WebP Cleanup:** Find and remove unused .webp files with no matching source images.
* **Modern Dashboard:** Get a clear overview of total images, savings, and optimization health.

= Performance Benefits =

* Faster image load times
* Smaller page weight for better mobile UX
* Improved Core Web Vitals (LCP, CLS)
* SEO and Lighthouse score improvements

== Installation ==

1. Upload the `image-squeeze` plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the “Plugins” menu in WordPress
3. Navigate to **Image Squeeze** in your admin dashboard
4. Click **Optimize Images Now** to start optimizing your Media Library

== Frequently Asked Questions ==

= Will this plugin overwrite my original images? =  
No. By default, Image Squeeze keeps your original images. It generates WebP versions and serves them conditionally based on browser support. You can optionally delete originals via settings.

= Does it support CDN setups? =  
Yes. It works with most CDN providers. If needed, you can disable WebP delivery and let your CDN handle it.

= What happens if I uninstall the plugin? =  
The plugin removes its settings on uninstall. WebP files remain, and your site continues functioning normally.

= Does this plugin support WooCommerce? =  
Yes. Image Squeeze works seamlessly with WooCommerce product images and galleries.

== Screenshots ==

1. **Dashboard Overview** – See total image count, savings, and optimization stats at a glance.  
   (screenshot-1.png)

2. **Optimization Logs** – View history of image compression jobs and results with date, status, and size savings.  
   (screenshot-2.png)

3. **Settings Panel** – Customize compression quality and output size, and toggle WebP delivery.  
   (screenshot-3.png)

4. **Advanced Settings** – Enable auto-optimize on upload and automatic retry of failed images.  
   (screenshot-4.png)

5. **WebP Cleanup Tool** – Scan and clean orphaned WebP files created by optimization jobs.  
   (screenshot-5.png)

== Changelog ==

= 1.0.0 =
* Initial release
* Compression engine with WebP conversion
* Dashboard UI with real-time stats
* Retry failed images
* Upload hook integration
* Orphaned WebP cleanup tool

== Upgrade Notice ==

= 1.0.0 =
Initial release of Image Squeeze.
