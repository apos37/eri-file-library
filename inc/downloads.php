<?php
/**
 * Downloads
 */


/**
 * Define Namespaces
 */
namespace Apos37\EriFileLibrary;
use Apos37\EriFileLibrary\Settings;
use Apos37\EriFileLibrary\PostType;
use Apos37\EriFileLibrary\Taxonomies;
use Apos37\EriFileLibrary\ListTable;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class if we are tracking
 */
add_action( 'init', function() {
    if ( (new Settings())->is_tracking() ) {
        (new Downloads())->init();
    }
} );


/**
 * The class
 */
class Downloads {

	/**
     * Nonce
     *
     * @var string
     */
    private $nonce = 'erifl_nonce';


    /**
     * Option keys
     *
     * @var string
     */
    public $option_key_per_page = 'erifl_per_page';


	/**
	 * Load on init
	 *
	 * @return void
	 */
	public function init() {
        
		// Submenu
        add_action( 'admin_menu', [ $this, 'submenu' ] );

        // Enqueue scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End init()


	/**
     * Submenu
     *
     * @return void
     */
    public function submenu() {
        add_submenu_page(
            'edit.php?post_type='.(new PostType())->post_type,
            __( 'File Library — Downloads', 'eri-file-library' ),
            __( 'Downloads', 'eri-file-library' ),
            'manage_options',
            ERIFL__TEXTDOMAIN . '_downloads',
            [ $this, 'page' ]
        );
    } // End submenu()


	/**
     * The page
     *
     * @return void
     */
    public function page() {
        global $current_screen;
        if ( $current_screen->id != ERIFL_DOWNLOADS_SCREEN_ID ) {
            return;
        }

        // Possible filters (not including taxonomies)
        $filters = [
            'user'       => __( 'User ID', 'eri-file-library' ),
            'file'       => __( 'File', 'eri-file-library' ),
            'start_date' => __( 'Start Date', 'eri-file-library' ),
            'end_date'   => __( 'End Date', 'eri-file-library' ),
        ];        

        // Vars
        $user_id = null;
        $user_ip = null;
        $file_id = null;
        $start_date = null;
        $end_date = null;
        $per_page = get_option( $this->option_key_per_page, 25 );
        $paged = 1;

        // Store taxonomies here
        $selected_terms = [];

        // If we have a nonce, let's check for the rest
        if ( isset( $_GET[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET[ '_wpnonce' ] ) ), $this->nonce ) ) {

            // Check for filters
            foreach ( $filters as $key => $label ) {
                
                if ( isset( $_GET[ $key ] ) ) {
                    $unslashed_key = wp_unslash( $_GET[ $key ] ); // phpcs:ignore

                    if ( $unslashed_key != '' ) {
                        if ( $key == 'file' ) {
                            $file_id = absint( $unslashed_key );
                        } elseif ( $key === 'start_date' ) {
                            $start_date = sanitize_text_field( $unslashed_key );
                        } elseif ( $key === 'end_date' ) {
                            $end_date = sanitize_text_field( $unslashed_key );
                        } else {
                            $user_search = sanitize_text_field( $unslashed_key );

                            if ( filter_var( $user_search, FILTER_VALIDATE_IP ) !== false ) {
                                $user_ip = $user_search;
                            } elseif ( is_numeric( $user_search ) || $user_search === 0 ) {
                                $user_id = absint( $user_search );
                            } else {
                                $user = get_user_by( 'email', $user_search );
                                if ( !$user ) {
                                    $user = get_user_by( 'login', $user_search );
                                }
                                $user_id = $user ? $user->ID : null;
                            }
                        }
                    }
                }
            }

            foreach ( get_taxonomies( [ 'object_type' => [ ( new PostType() )->post_type ] ], 'names' ) as $tax ) {
                if ( isset( $_GET[ $tax ] ) && !empty( sanitize_key( wp_unslash( $_GET[ $tax ] ) ) ) ) {
                    $selected_terms[ $tax ] = sanitize_key( wp_unslash( $_GET[ $tax ] ) );
                }
            }
        }

        // Paging
        $per_page = isset( $_GET[ 'per_page' ] ) ? intval( wp_unslash( $_GET[ 'per_page' ] ) ) : $per_page;
        $paged = isset( $_GET[ 'paged' ] ) ? max( 1, intval( wp_unslash( $_GET[ 'paged' ] ) ) ) : $paged;

        // Let's get the records
        $records = (new Database())->get( $user_id, $user_ip, $file_id, $selected_terms, $start_date, $end_date, 'time' );

        // Total
        $total = count( $records );

        // Ensure the current page is not out of range
        if ( $paged > $total ) {
            $paged = $total;
        }

        // Calculate the offset
        $offset = ( $paged - 1 ) * $per_page;

        // Slice the forms array
        $records_to_display = array_slice( $records, $offset, $per_page );

        // Define list table columns
        $columns = [
            'date'    => __( 'Date', 'eri-file-library' ),
            'title'   => __( 'File', 'eri-file-library' ),
            'user'    => __( 'User', 'eri-file-library' )
        ];

        // Instantiate classes
        $TAXONOMIES = new Taxonomies();
        $POSTTYPE = new PostType();

        // Get all the taxonomies
        $stock_taxes = [ 
            $TAXONOMIES->taxonomy_resource_types,
            $TAXONOMIES->taxonomy_target_audiences
        ];
        $taxes = array_merge( $stock_taxes, $TAXONOMIES->get_addt_taxonomies() );

        // Add taxonomies to columns
        foreach ( $taxes as $taxonomy ) {
            $taxonomy_obj = get_taxonomy( $taxonomy );
            if ( $taxonomy_obj ) {
                $columns[ $taxonomy ] = $taxonomy_obj->labels->singular_name;
            }
        }

        // Collect data
        $data = [];

        // The IP path
        $ip_address_link = sanitize_text_field( apply_filters( 'erifl_ip_lookup_path', 'https://www.criminalip.io/asset/report/{ip}' ) );

        // Iter
        foreach ( $records_to_display as $record ) {
            $title = get_the_title( $record->file_id );
            if ( $title ) {
                $title = '<a href="' . get_edit_post_link( $record->file_id ) . '">' . $title . '</a>';
            } else {
                $title = __( 'Unknown File ID: ', 'eri-file-library' ) . $record->file_id;
            }

            $tax_results = [];
            foreach ( $taxes as $tax ) {
                $terms = get_the_terms( $record->file_id, $tax );
                $result = '';
                if ( !empty( $terms ) ) {
                    $family = [];
                    foreach ( $terms as $term ) {
                        $bold_parent = $term->parent == 0 ? 'bold' : 'normal';
                        $link = sprintf( '<a href="%s" style="font-weight: %s">%s</a>',
                            esc_url( add_query_arg( [ 'post_type' => $POSTTYPE->post_type, $tax => $term->slug ], 'edit.php' ) ),
                            esc_attr( $bold_parent ),
                            esc_html( sanitize_term_field( 'name', $term->name, $term->term_id, $tax, 'display' ) )
                        );
                        if ( $term->parent == 0 ) {
                            $family[ $term->term_id ][ 'parent' ] = $link;
                        } else {
                            $family[ $term->parent ][ $term->term_id ] = $link;
                        }
                    }
                    foreach ( $family as $member ) {
                        $children = [];
                        foreach ( $member as $key => $child ) {
                            if ( $key != 'parent' ) {
                                $children[] = $child;
                            }
                        }
                        if ( !empty( $children ) ) {
                            $incl_children = '<br>(<em>' . implode( ', ', $children ) . '</em>)<br><br>';
                        } else {
                            $incl_children = '';
                        }
                        $result .= $member[ 'parent' ] . $incl_children;
                    }
                } else {
                    $result = '--';
                }
                $tax_results[ $tax ] = $result;
            }            

            // Guests
            if ( $record->user_ip ) {
                $ip_address_link = sanitize_url( str_replace( '{ip}', $record->user_ip, $ip_address_link ) );
                $display_name = __( 'Guest', 'eri-file-library' ) . ' (<a href="' . $ip_address_link . '" target="_blank">' . $record->user_ip . '</a>)';

            // Logged-in members or guests that we didn't get an IP address for
            } else {
                $user = get_userdata( $record->user_id );
                if ( $user ) {
                    $display_name = '<a href="' . get_edit_profile_url( $record->user_id ) . '">' . $user->display_name . '</a>' . ' (ID: ' . $record->user_id . ')';
                } elseif ( $record->user_id == 0 ) {
                    $display_name = __( 'Guest (No IP Found)', 'eri-file-library' );
                } else {
                    $display_name = __( 'Unknown User ID: ', 'eri-file-library' ) . $record->user_id;
                }
            }

            $data_args = [
                'date'   => gmdate( 'F j, Y \a\t g:i A', strtotime( $record->time ) ),
                'title'  => $title,
                'user'   => $display_name,
            ];
            foreach ( $taxes as $tax ) {
                $data_args[ $tax ] = $tax_results[ $tax ];
            }

            $data[] = $data_args;
        }
        ?>

		<div class="wrap">
			<h1><?php echo esc_attr( get_admin_page_title() ) ?></h1>
			<p><?php echo esc_html__( 'A list of all user downloads.', 'eri-file-library' ); ?></p>

            <div id="userfile-downloads">
                <br><br>
                <?php $this->wp_list_table( $columns, $data, $total ); ?>
            </div>
		</div>
        <?php
    } // End page()


    /**
     * Return the WP List Table
     *
     * @param array $columns
     * @param array $data
     * @param string $current_tab
     * @param array $qs             [ 'param' => $value, 'param' => $value ]
     * @return void
     */
    public function wp_list_table( $columns, $data, $total_count = -1, $checkbox_name = false, $checkbox_value = false ) {
        // Per page
        if ( isset( $_GET[ 'per_page' ] ) && isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce ) ) {
            $current_per_page = intval( $_GET[ 'per_page' ] );
            update_option( $this->option_key_per_page, $current_per_page );
        } else {
            $current_per_page = intval( get_option( $this->option_key_per_page, 25 ) );
        }
        
        ?>
        <form id="erifl-items-per-page" method="GET" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr( ERIFL__TEXTDOMAIN ); ?>_downloads">
            <input type="hidden" name="post_type" value="<?php echo esc_attr( (new PostType())->post_type ); ?>">
            <?php

            // Display the table
            $table = new ListTable( $columns, $data, $this->nonce, $current_per_page, $total_count, $checkbox_name, $checkbox_value );
            $table->prepare_items();
            $table->display();

            // Add per-page dropdown
            $per_page_options = [ 5, 10, 25, 50 ];
            if ( !in_array( $current_per_page, $per_page_options ) ) {
                $per_page_options[] = $current_per_page;
                sort( $per_page_options );
            }
            ?>
            
            <?php wp_nonce_field( $this->nonce, '_wpnonce', false ); ?>
            <label for="per_page"><?php esc_html_e( 'Items per page:', 'eri-file-library' ); ?></label>
            <select name="per_page" id="per_page" onchange="this.form.submit()">
                <?php foreach ( $per_page_options as $option ) : ?>
                    <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $option, $current_per_page ); ?>>
                        <?php echo esc_html( $option ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php
    } // End wp_list_table()


    /**
     * Enqueue scripts
     *
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        if ( ( $screen == ERIFL_DOWNLOADS_SCREEN_ID ) ) {

            // Jquery
            $js_handle = ERIFL_TEXTDOMAIN.'-downloads-script';
            wp_enqueue_script( 'jquery' );
            wp_register_script( $js_handle, ERIFL_JS_PATH . 'downloads.js', [ 'jquery' ], ERIFL_SCRIPT_VERSION, true );
            wp_enqueue_script( $js_handle );

            // CSS
            $css_handle = ERIFL_TEXTDOMAIN . '-downloads-style';
            wp_register_style( $css_handle, ERIFL_CSS_PATH . 'downloads.css', [], ERIFL_SCRIPT_VERSION );
            wp_enqueue_style( $css_handle );
        }
    } // End enqueue_scripts()
    
}