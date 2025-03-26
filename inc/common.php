<?php
/**
 * Version check, activation, deactivation, uninstallation, etc.
 */


/**
 * Define Namespaces
 */
namespace Apos37\EriFileLibrary;
use Apos37\EriFileLibrary\Database;
use Apos37\EriFileLibrary\Downloads;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Initiate the class
 */
new Common();


/**
 * Register hooks
 */
register_uninstall_hook( ERIFL_BASENAME, [ 'Apos37\EriFileLibrary\Common', 'uninstall_plugin' ] );


/**
 * The class
 */
class Common {

    /**
     * Constructor
     */
    public function __construct() {

		// PHP Version check
		$this->check_php_version();

		// Create the table
        add_action( 'init', [ new Database(), 'maybe_create_table' ] );

    } // End __construct()


	/**
	 * Prevent loading the plugin if PHP version is not minimum
	 *
	 * @return void
	 */
	public function check_php_version() {
		if ( version_compare( PHP_VERSION, ERIFL_MIN_PHP_VERSION, '<=' ) ) {
			add_action( 'admin_init', function() {
				deactivate_plugins( ERIFL_BASENAME );
			} );
			add_action( 'admin_notices', function() {
				/* translators: 1: Plugin name, 2: Required PHP version */
				$notice = sprintf( __( '%1$s requires PHP %2$s or newer.', 'eri-file-library' ),
					ERIFL_NAME,
					ERIFL_MIN_PHP_VERSION
				);
				echo wp_kses_post(
					'<div class="notice notice-error"><p>' . esc_html( $notice ) . '</p></div>'
				);
			} );
			return;
		}
	} // End check_php_version()	


    /**
     * Uninstall the plugin
     */
    public static function uninstall_plugin() {
        if ( filter_var( get_option( 'erifl_delete_table', false ), FILTER_VALIDATE_BOOLEAN ) ) {
            (new Database())->delete_table();
        }

		delete_option( (new Downloads())->option_key_per_page );
    } // End uninstall_plugin()
	
}