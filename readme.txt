=== ERI File Library ===
Contributors: apos37
Tags: file manager, file sharing, download, tracking, links
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Easily upload, manage, and track downloads of your shared files

== Description ==
ERI File Library is a simple WordPress plugin designed for effortless file sharing and download tracking. Easily upload and manage your files with a straightforward interface for quick organization and access.

== Features ==
* **Flexible display options**: Customize link appearances with various HTML elements and attributes.
* **Shortcodes**: Easily integrate downloads into your site with shortcodes.
* **Custom access controls**: Restrict downloads by user role or meta key.
* **Detailed tracking**: Monitor download counts, track user interactions, and log download dates.

This plugin does not have the option to sell downloads, but it can be integrated with another payment platform by requiring a specific role or meta key to download specific files.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/eri-file-library` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Start uploading and managing your downloads!

== Frequently Asked Questions ==
= How do I track downloads? =
Download counts are automatically recorded for each file. If you want to additionally track user interactions and dates, you will need to enable it. Navigate to **ERI File Library** > **Settings**, and select the "**Enable User Tracking**" option. Doing so will create a new table in your database where each download is logged. "Downloads" and "Report" pages will appear under ERI File Library in the admin menu where you can see your collected data.

= Are there hooks available for Developers? =
Yes, there are plenty. The following hooks are available:
* `erifl_download_file_url` ( String $url, Array $file, Integer $user_id, String $user_ip ) — Filter the URL for the file when a user is downloading it. $file contains the file data, $user_id is the ID of the user if they are logged in, and $user_ip is the IP address of the user if they are logged out.
* `erifl_after_file_downloaded` ( Array $file, String $url, Integer $user_id, String $user_ip ) — Action fired after a file has been downloaded. $file contains the file data, $url is the download URL, $user_id is the ID of the user, and $user_ip is the IP address of the user.
* `erifl_user_meets_requirements` ( Boolean $meets_requirements, Integer $file_id, Integer $user_id, Array $required_roles, String $required_meta_key ) — Filter whether the user meets the requirements for accessing the file. This filter allows modifying the result of the access check based on the user’s roles, meta key values, and any additional custom logic.
* `erifl_classes` ( Array $classes, Array $file, String $type ) — Filter the classes used in the file download link element. $file contains an array of file data, and $type specifies the type of link being displayed.
* `erifl_download_icon` ( String $default_icon, String $icon_type, Array $file, String $ext ) — Filter the icon used for file downloads. $icon_type specifies the icon type (e.g., 'logo-full', 'logo-file', 'uni', 'fa', 'cs'), $file contains the file data, and $ext is the file extension.
* `erifl_downloaded_count_string` ( String $downloaded_string, Integer $count, Array $file ) — Filter the string used to display the download count in the "post" type links. $count is the number of times the file was downloaded, and $file contains the file data.
* `erifl_format_specific_prefixes` ( Array $format_prefixes, Array $file, String $type ) — Filter the prefixes added to the displayed title of the link based on the file format. $file contains the file data, and $type specifies the link type (e.g., 'mp3', 'mp4').
* `erifl_ip_lookup_path` ( String $ip_address_link ) — Filter the link used for looking up a logged-out user's IP address on the Downloads page.
* `erifl_where_used_post_types` ( Array $post_types ) — Filter the post types used to look for where the shortcode is used. This feature is located at the bottom of the file edit screen.
* `erifl_post_meta` ( Array $meta ) — Filter the "post" display type meta html.
* `erifl_custom_base_path` (String $base_path, Boolean $abspath) — Filter the base path. $base_path is the original base path (either file system or URL), and $abspath indicates whether the path is an absolute path (true) or a URL (false). You should return the appropriate path based on the value of $abspath.

== Screenshots ==
1. File Library admin list table
2. Adding/editing a file
3. Tracking downloads - must have user tracking enabled
4. Downloads report - must have user tracking enabled
5. Settings
6. Shortcodes with examples 1
7. Shortcodes with examples 2
8. Shortcodes with examples 3
9. Shortcodes with examples 4

== Changelog ==
= 1.0.4 =
* Update: Changed author name from Apos37 to WordPress Enhanced, new Author URI
* Fix: Optimizations

= 1.0.3 =
* Fix: Translations
* Fix: About page plugins not showing up on some sites

= 1.0.2 =
* Fix: Requirements from WP.org repo

= 1.0.1 =
* Release to WP.org repo on March 27, 2025
* Initial release and testing on October 15, 2024