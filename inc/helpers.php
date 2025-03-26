<?php
/**
 * Helpers
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
 * The class
 */
class Helpers {

	/**
	 * Admin Error Notice
	 *
	 * @param string $msg
	 * @param boolean $include_pre
	 * @param boolean $br
	 * @param boolean $hide_error
	 * @return string
	 */
	public function admin_error( $msg, $include_pre = true, $br = true, $hide_error = false ) {
		if ( current_user_can( 'administrator' ) && !$hide_error ) {
			$display_br = $br ? '<br>' : '';
			$display_pre = $include_pre ? 'ADMIN ERROR: ' : '';
			return $display_br.'<span class="erifl-error-notice" style="background-color: red; color: white; padding: 10px 20px; border-radius: 5px; display: inline-block;">'.$display_pre.$msg.'</span>';
		} else {
			return '';
		}
	} // End admin_error()
	

	/**
	 * Check if the post can be saved based on various conditions.
	 *
	 * @param int    $post_id       The ID of the post being saved.
	 * @param mixed  $the_post_type The post type or array of post types to validate against.
	 * @param string $nonce_value   The nonce value to check for validity.
	 * @param string $nonce_action  The nonce action to verify.
     * @param boolean $quick_edit   Whether we are doing quick edit or not.
	 * 
	 * @return bool True if the post can be saved, false otherwise.
	 */
	public function can_save_post( $post_id, $the_post_type, $nonce_value, $nonce_action, $quick_edit = false ) {
		// Verify that the nonce is valid
		if ( !isset( $_POST[ $nonce_value ] ) || 
			 !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_value ] ) ), $nonce_action ) ) {
			return false;
		}
	
		// Validate post type
        if ( !$quick_edit ) {
            global $post_type;
            if ( ( is_array( $the_post_type ) && !in_array( $post_type, $the_post_type ) ) ||
                 ( !is_array( $the_post_type ) && $the_post_type !== $post_type ) ) {
                return false;
            }
        
            // Common checks to prevent saving
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
                defined( 'DOING_AJAX' ) && DOING_AJAX ||
                get_post_status( $post_id ) === 'auto-draft' ||
                get_post_status( $post_id ) === 'trash' ||
                wp_is_post_revision( $post_id ) ||
                isset( $_REQUEST[ 'bulk_edit' ] ) ) {
                return false;
            }
        }

		// Check user permissions
		if ( $the_post_type == 'page' ) {
			if ( !current_user_can( 'edit_page', $post_id ) ) {
				return false;
			}
		} else {
			if ( !current_user_can( 'edit_post', $post_id ) ) {
				return false;
			}
		}
	
		// All checks passed
		return true;
	} // End can_save_post()


	/**
     * Handling ajax if they are not logged in
     *
     * @return void
     */
    public function ajax_must_login() {
        // error_log( __( 'Attempt to use Ajax on nopriv.', 'eri-file-library' ) );
        $request_uri = isset( $_SERVER[ 'REQUEST_URI' ] ) ? filter_var( wp_unslash( $_SERVER[ 'REQUEST_URI' ] ), FILTER_SANITIZE_URL ) : '';
        $redirect_url = wp_login_url( $request_uri );

        wp_send_json_error( [
            'redirect' => $redirect_url
        ] );
    } // End ajax_must_login()


	/**
     * Get current URL with query string
     *
     * @param boolean $params
     * @param boolean $domain
     * @return string
     */
    public function get_current_url( $params = true, $domain = true ) {
        if ( $domain === true ) {
            // Check if HTTP_HOST is set before using it
            if ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) {
                $protocol = isset( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] !== 'off' ? 'https' : 'http';
                $domain_without_protocol = sanitize_text_field( wp_unslash( $_SERVER[ 'HTTP_HOST' ] ) );
                $domain = $protocol.'://'.$domain_without_protocol;
            } else {
                // Handle case where HTTP_HOST is not set
                $domain = 'http://localhost';
            }
    
        } elseif ( $domain === 'only' ) {
            // Check if HTTP_HOST is set before using it
            if ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) {
                $domain = sanitize_text_field( wp_unslash( $_SERVER[ 'HTTP_HOST' ] ) );
                return $domain;
            } else {
                return 'localhost';
            }
    
        } else {
            $domain = '';
        }
    
        $uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );
        $full_url = $domain.$uri;
    
        if ( !$params ) {
            return strtok( $full_url, '?' );
        } else {
            return $full_url;
        }
    } // End get_current_url()


	/**
     * Convert timezone
     * 
     * @param string $date
     * @param string $format
     * @param string $timezone
     * @return string
     */
    public function convert_timezone( $date = null, $format = 'F j, Y g:i A T', $timezone = null ) {
        if ( is_null( $date ) ) {
            $date = gmdate( 'Y-m-d H:i:s' );
        } elseif ( is_numeric( $date ) ) {
            $date = gmdate( $format, $date );
        }
        $date = new \DateTime( $date, new \DateTimeZone( 'UTC' ) );
        if ( !is_null( $timezone ) ) {
            $timezone_string = $timezone;
        } else {
            $timezone_string = wp_timezone_string();
        }
        $date->setTimezone( new \DateTimeZone( $timezone_string ) );
        $new_date = $date->format( $format );
        return $new_date;
    } // End convert_timezone()


	/**
	 * Include s
	 *
	 * @param int $count
	 * @return string
	 */
	public function include_s( $count ) {
        $s = $count == 1 ? '' : 's';
        return $s;
    } // End include_s()
	

	/**
     * Check if a string starts with something
     *
     * @param string $haystack
     * @param string $needle
     * @return boolean
     */
    public function str_starts_with( $haystack, $needle ) {
        return strpos( $haystack, $needle ) === 0;
    } // End str_starts_with()


    /**
     * Check if a string ends with something
     *
     * @param string $haystack
     * @param string $needle
     * @return boolean
     */
    public function str_ends_with( $haystack, $needle ) {
        return $needle !== '' && substr( $haystack, -strlen( $needle ) ) === (string)$needle;
    } // End str_ends_with()


	/**
     * Check if the current user has a role
     *
     * @param string|array $role
     * @return bool
     */
    public function has_role( $role, $user_id = null ) {
        if ( is_null( $user_id ) ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( $user && isset( $user->roles ) && is_array( $user->roles ) ) {
            $user_roles = $user->roles;
            if ( !is_array( $role ) ) {
                $role = [ $role ];
            }
            foreach ( $role as $r ) {
                if ( in_array( $r, $user_roles ) ) {
                    return true;
                }
            }
        }
        return false;    
    } // End has_role()


	/**
	 * Convert file bytes to KB, MB, etc.
	 *
	 * @param int|float $bytes
	 * @return string
	 */
	public function format_bytes( $bytes ) { 
		$bytes = floatval( $bytes );
		if ( $bytes >= 1073741824 ){
			$bytes = number_format( $bytes / 1073741824, 2 ) . ' GB';
		} elseif ( $bytes >= 1048576 ) {
			$bytes = number_format( $bytes / 1048576, 2 ) . ' MB';
		} elseif ( $bytes >= 1024 ) {
			$bytes = number_format( $bytes / 1024, 2 ) . ' KB';
		} elseif ( $bytes > 1 ) {
			$bytes = $bytes . ' bytes';
		} elseif ( $bytes == 1 ) {
			$bytes = $bytes . ' byte';
		} else {
			$bytes = '0 bytes';
		}

		return $bytes;
	} // End format_bytes()


	/**
     * Get the current user's IP address
     *
     * @return string
     */
    public function get_user_ip() {
        $keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxy
            'HTTP_X_REAL_IP',        // Nginx
            'HTTP_CLIENT_IP',        
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'            // Default
        ];
    
        foreach ( $keys as $key ) {
            if ( isset( $_SERVER[ $key ] ) ) {
                $raw_value = filter_var( wp_unslash( $_SERVER[ $key ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
                $ip_list = explode( ',', $raw_value );
    
                foreach ( $ip_list as $ip ) {
                    $ip = trim( $ip );
                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        return $ip;
                    }
                }
            }
        }
    
        return 'Unknown';
    } // End get_user_ip()

}