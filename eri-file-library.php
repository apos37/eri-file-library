<?php
/**
 * Plugin Name:         ERI File Library
 * Plugin URI:          https://github.com/apos37/eri-file-library
 * Description:         Easily upload, manage, and track downloads of your shared files
 * Version:             1.0.4
 * Requires at least:   6.0
 * Tested up to:        6.7
 * Requires PHP:        7.4
 * Author:              WordPress Enhanced
 * Author URI:          https://wordpressenhanced.com/
 * Support URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         eri-file-library
 * License:             GPLv2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 * Created on:          October 15, 2024
 */

 
/**
 * Define Namespaces
 */
namespace Apos37\EriFileLibrary;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Defines
 */
$plugin_data = get_file_data( __FILE__, [
    'name'         => 'Plugin Name',
    'description'  => 'Description',
    'version'      => 'Version',
    'plugin_uri'   => 'Plugin URI',
    'requires_php' => 'Requires PHP',
    'textdomain'   => 'Text Domain',
    'author'       => 'Author',
    'author_uri'   => 'Author URI',
    'support_uri'  => 'Support URI',
] );

// Versions
define( 'ERIFL_VERSION', $plugin_data[ 'version' ] );
define( 'ERIFL_SCRIPT_VERSION', ERIFL_VERSION );                                                // REPLACE WITH time() DURING TESTING
define( 'ERIFL_MIN_PHP_VERSION', $plugin_data[ 'requires_php' ] );

// Names
define( 'ERIFL_NAME', $plugin_data[ 'name' ] );
define( 'ERIFL_TEXTDOMAIN', $plugin_data[ 'textdomain' ] );
define( 'ERIFL__TEXTDOMAIN', str_replace( '-', '_', ERIFL_TEXTDOMAIN ) );
define( 'ERIFL_AUTHOR', $plugin_data[ 'author' ] );
define( 'ERIFL_AUTHOR_URI', $plugin_data[ 'author_uri' ] );
define( 'ERIFL_PLUGIN_URI', $plugin_data[ 'plugin_uri' ] );
define( 'ERIFL_DISCORD_SUPPORT_URL', $plugin_data[ 'support_uri' ] );

// Paths
define( 'ERIFL_BASENAME', plugin_basename( __FILE__ ) );                                        //: text-domain/text-domain.php
define( 'ERIFL_ABSPATH', plugin_dir_path( __FILE__ ) );                                         //: /home/.../public_html/wp-content/plugins/text-domain/
define( 'ERIFL_URL', plugin_dir_url( __FILE__ ) );                                              //: https://domain.com/wp-content/plugins/text-domain/
define( 'ERIFL_INCLUDES_ABSPATH', ERIFL_ABSPATH . 'inc/' );                                     //: /home/.../public_html/wp-content/plugins/text-domain/includes/
define( 'ERIFL_INCLUDES_DIR', ERIFL_URL . 'inc/' );                                             //: https://domain.com/wp-content/plugins/text-domain/includes/
define( 'ERIFL_IMG_PATH', ERIFL_INCLUDES_DIR . 'img/' );                                        //: https://domain.com/wp-content/plugins/text-domain/includes/img/
define( 'ERIFL_CSS_PATH', ERIFL_INCLUDES_DIR . 'css/' );                                        //: https://domain.com/wp-content/plugins/text-domain/includes/css/
define( 'ERIFL_JS_PATH', ERIFL_INCLUDES_DIR . 'js/' );                                          //: https://domain.com/wp-content/plugins/text-domain/includes/js/
define( 'ERIFL_LANG_PATH', ERIFL_INCLUDES_DIR . 'lang/' );                                      //: https://domain.com/wp-content/plugins/text-domain/includes/lang/
define( 'ERIFL_SETTINGS_PATH', admin_url( 'edit.php?post_type=erifl-files&page=settings' ) );   //: https://domain.com/wp-admin/?page=text-domain

// Screen IDs
define( 'ERIFL_SETTINGS_SCREEN_ID', 'erifl-files_page_' . ERIFL__TEXTDOMAIN . '_settings' );
define( 'ERIFL_DOWNLOADS_SCREEN_ID', 'erifl-files_page_' . ERIFL__TEXTDOMAIN . '_downloads' );
define( 'ERIFL_REPORT_SCREEN_ID', 'erifl-files_page_' . ERIFL__TEXTDOMAIN . '_report' );
define( 'ERIFL_ABOUT_SCREEN_ID', 'erifl-files_page_' . ERIFL__TEXTDOMAIN . '_about' );


/**
 * Includes
 */
require_once ERIFL_INCLUDES_ABSPATH . 'db.php';
require_once ERIFL_INCLUDES_ABSPATH . 'common.php';
require_once ERIFL_INCLUDES_ABSPATH . 'helpers.php';
require_once ERIFL_INCLUDES_ABSPATH . 'downloads.php';
require_once ERIFL_INCLUDES_ABSPATH . 'wp-list-table.php';
require_once ERIFL_INCLUDES_ABSPATH . 'report.php';
require_once ERIFL_INCLUDES_ABSPATH . 'settings.php';
require_once ERIFL_INCLUDES_ABSPATH . 'post-type.php';
require_once ERIFL_INCLUDES_ABSPATH . 'taxonomies.php';
require_once ERIFL_INCLUDES_ABSPATH . 'shortcodes.php';
require_once ERIFL_INCLUDES_ABSPATH . 'about.php';