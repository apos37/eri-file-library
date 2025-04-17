<?php
/**
 * Shortcodes
 */


/**
 * Define Namespaces
 */
namespace Apos37\EriFileLibrary;
use Apos37\EriFileLibrary\Settings;
use Apos37\EriFileLibrary\Helpers;
use Apos37\EriFileLibrary\PostType;
use Apos37\EriFileLibrary\Taxonomies;
use Apos37\EriFileLibrary\Database;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Initiate the class
 */
new Shortcodes();


/**
 * Class description
 */
class Shortcodes {

    /**
     * Ajax action
     *
     * @var string
     */
    private $ajax_action = 'erifl_file';


    /**
     * Nonce
     *
     * @var string
     */
    private $nonce = 'erifl_nonce';


    /**
     * Constructor
     */
    public function __construct() {
        
		// File shortcode
        add_shortcode( (new Settings())->shortcode_tag(), [ $this, 'file' ] );

        // Ajax download count
        add_action( 'wp_ajax_' . $this->ajax_action, [ $this, 'ajax' ] );
        add_action( 'wp_ajax_nopriv_' . $this->ajax_action, [ $this, 'ajax' ] );

        // Enque JavaScript on front-end only
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // User download history
        add_shortcode( 'erifl_user_download_history', [ $this, 'user_download_history' ] );

        // Top downloads
        add_shortcode( 'erifl_top_downloads', [ $this, 'top_downloads' ] );

        // File lists
        add_shortcode( 'erifl_file_list', [ $this, 'file_list' ] );

    } // End __construct()


	/**
     * Get the download link
     * 
     * Usage: [erifl_file id="811" custom_field="file_audio" type="button"]
     * Takes all of the other params as well.
     *
     * @param array $atts
     * @return string
     */
    public function file( $atts ) {
        // Params
        $atts = shortcode_atts( [
            'id' 	          => '',
            'custom_field' 	  => '',
            'type'	          => 'link',
            'title'           => '',
            'desc'            => '',
            'start_count'     => '',
            'formats'         => '',
            'classes'         => '',
            'default_img'     => '',
            'dlc'             => 'true',
            'ignore_pre_post' => 'false',
            'icon'            => null
        ], $atts );

        $id = absint( $atts[ 'id' ] );
        $custom_field = sanitize_text_field( $atts[ 'custom_field' ] );
        $formats = sanitize_text_field( $atts[ 'formats' ] );
        $title = sanitize_text_field( $atts[ 'title' ] );
        $desc = sanitize_text_field( $atts[ 'desc' ] );
        $start_count = absint( $atts[ 'start_count' ] );
        $type = sanitize_key( $atts[ 'type' ] );
        $classes = sanitize_text_field( $atts[ 'classes' ] );
        $default_img = sanitize_url( $atts[ 'default_img' ] );
        $download_count = sanitize_key( $atts[ 'dlc' ] ) == 'true' ? true : false;
        $ignore_pre_post = sanitize_key( $atts[ 'ignore_pre_post' ] ) == 'true' ? true : false;
        $icon_type = !is_null( $atts[ 'icon' ] ) ? sanitize_text_field( $atts[ 'icon' ] ) : null;

        // Are we hiding the error? Let's allow devs to tap in
        $hide_error = false;
        $reasons_to_hide_error = filter_var_array( apply_filters( 'erifl_reasons_to_hide_error', [
            str_ends_with( $custom_field, 'file_audio' )
        ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        foreach ( $reasons_to_hide_error as $reason ) {
            if ( $reason ) {
                $hide_error = true;
                break;
            }
        }

		// Classes
		$HELPERS = new Helpers();
		$POST_TYPE = new PostType();
        $TAXONOMIES = new Taxonomies();
        $SETTINGS = new Settings();

        // Get the file id
        if ( $id == '' && $custom_field != '' ) {
            $file_id = absint( get_post_meta( get_the_ID(), $custom_field, true ) );
            if ( $file_id ) {
                return $HELPERS->admin_error( __( 'FILE ID NOT FOUND', 'eri-file-library' ), true, true, $hide_error );
            }
        } else {
            $file_id = $id;
        }
        if ( !$file_id ) {
            return $HELPERS->admin_error( __( 'FILE ID NOT FOUND', 'eri-file-library' ), true, true, $hide_error );
        }

        // Make sure the post exists and is published
        $file = $POST_TYPE->get_file( $file_id );
        if ( !$file || $file[ 'post_status' ] != 'publish' ) {
            $status = isset( $file[ 'post_status' ] ) ? $file[ 'post_status' ] : 'null';
            return $HELPERS->admin_error( '<span status=' . $status . '>' . __( 'FILE NOT PUBLISHED', 'eri-file-library' ) . '</span>', true, true, $hide_error );
        }

        // Check permissions
        if ( !$POST_TYPE->user_meets_requirements( $file_id ) ) {
            return wp_kses_post( get_option( $SETTINGS->option_no_access_msg, __( 'You do not have permission to access this file.', 'eri-file-library' ) ) );
        }

        // Filename
        $url = $POST_TYPE->file_url( $file_id );

        // Vars
        $display_formats = '';
        $formats_array = [];

        // Get the format(s) if necessary
        if ( !filter_var( get_option( $SETTINGS->option_btn_hide_format ), FILTER_VALIDATE_BOOLEAN ) ) {
            
            if ( $formats ) {
                $formats_array = preg_split( '/[\s,]+/', strtolower( $formats ), -1, PREG_SPLIT_NO_EMPTY );
                $display_formats = '('.$formats.')';

            } else {
                $formats = get_the_terms( $file_id, $TAXONOMIES->taxonomy_formats );
                if ( is_array( $formats ) && !empty( $formats ) ) {
                    foreach ( $formats as $format ) {
                        $formats_array[] = $format->name;
                    }
                    $display_formats = '('.implode( '|', $formats_array ).')';
                }
            }
        }
        $display_formats = '<span class="erifl-formats">' . $display_formats . '</span>';

        // Add a format-specific prefix such as an icon
        $format_prefixes = filter_var_array( apply_filters( 'erifl_format_specific_prefixes', [
            'mp3' => '<i class="fas fa-volume-up"></i>',
            'mp4' => '<i class="fas fa-volume-up"></i>',
        ], $file, $type ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        $add_format_specific_prefix = '';
        foreach ( $format_prefixes as $f => $pf ) {
            if ( in_array( $f, $formats_array ) ) {
                $add_format_specific_prefix = html_entity_decode( $pf ) . ' ';
            }
        }

        // Title
        $title_types = [ 'full', 'link', 'button', 'post', 'title', 'icon' ];
        if ( in_array( $type, $title_types ) ) {
            if ( !$ignore_pre_post ) {
                $pre_title = '<span class="erifl-pre-text">' . trim( sanitize_text_field( get_option( $SETTINGS->option_pre_title, '' ) ) ) . ' </span>';
                $post_title = '<span class="erifl-post-text">' . trim( sanitize_text_field( get_option( $SETTINGS->option_post_title, '' ) ) ) . '</span>';
            } else {
                $pre_title = '';
                $post_title = '';
            }
            
            $title = ( $pre_title ? $pre_title : '' ) . ( $title ? $title : get_the_title( $file_id ) ) . ( $post_title ? $post_title : '' );
            if ( $type == 'icon' ) {
                $title = wp_strip_all_tags( $title );
            }
        }
        
        // Description
        $desc_types = [ 'full', 'post', 'desc', 'description' ];
        if ( in_array( $type, $desc_types ) ) {
            $desc = $desc ? $desc : sanitize_text_field( get_post_meta( $file_id, $POST_TYPE->meta_key_description, true ) );
        }

        // Icon
        $icon_types = [ 'full', 'button', 'post', 'icon' ];
        if ( in_array( $type, $icon_types ) ) {
            $path_info = pathinfo( $url );
            $ext = $path_info[ 'extension' ];
            $icon_title = $type == 'icon' ? $title : null;
            $display_icon = $SETTINGS->icon( $icon_type, $file, $ext, $icon_title );
        }

        // Download count
        $count = absint( get_post_meta( $file_id, $POST_TYPE->meta_key_download_count, true ) );
        $count += $start_count;

        // Create the link
        $link_types = [ 'full', 'link', 'button', 'post', 'icon' ];
        if ( in_array( $type, $link_types ) ) {
            $href = filter_var( get_option( $SETTINGS->option_include_urls ), FILTER_VALIDATE_BOOLEAN ) ? $url : '#';
        }

        // Get all taxonomies for the file's post type
        $tax_types = [ 'full', 'link', 'button', 'post', 'icon' ];
        $term_classes = [];
        if ( in_array( $type, $tax_types ) ) {
            $taxonomies = get_object_taxonomies( get_post_type( $file_id ), 'names' );

            // Get all terms across all taxonomies
            $term_links = [];
            foreach ( $taxonomies as $taxonomy ) {
                if ( $taxonomy == (new Taxonomies())->taxonomy_formats ) {
                    continue;
                }
                $terms = get_the_terms( $file_id, $taxonomy );
                if ( is_array( $terms ) && !empty( $terms ) ) {
                    foreach ( $terms as $term ) {
                        $term_links[] = '<span class="' . $taxonomy . ' ' . $term->slug . '">' . esc_html( $term->name ) . '</span>';
                        $term_classes[] = $term->slug;
                    }
                }
            }
        }

        $classes = sanitize_text_field( apply_filters( 'erifl_classes', $classes, $file, $type ) );
        $params = 'class="erifl-file ' . $type . ' ' . implode( ' ', $formats_array ) . ' ' . implode( ' ', $term_classes ) . ' ' . $classes . '" data-file="' . $file_id . '" data-type="' . $type . '" downloads="' . $count . '" rel="noopener noreferrer nofollow"';

        // Output all
        if ( $type == 'full' ) {
            return '<div ' . $params . '><span class="erifl-icon" style="margin-right: 5px;">' . $display_icon . '</span> ' . $add_format_specific_prefix . '<a href="' . $href . '">' . $title . '</a> ' . $display_formats . '<span class="erifl-desc">'.$desc.'</span></div>';
        
        // Output just the link
        } elseif ( $type == 'link' ) {
            return '<div ' . $params . '>' . $add_format_specific_prefix . '<a href="' . $href . '">' . $title . '</a> ' . $display_formats . '</div>';

        // Output just the button
        } elseif ( $type == 'button' ) {
            return '<a href="' . $href . '" ' . $params . ' style="text-decoration: none;">
                <span class="erifl-icon" style="margin-right: 5px;">' . $display_icon . '</span>
                <span class="erifl-title">' . $title . ' ' . $display_formats.' </span>
            </a>';

        // Output just the title
        } elseif ( $type == 'title' ) {
            return $title;

        // Output just the description
        } elseif ( $type == 'description' || $type == 'desc' ) {
            return $desc;

        // Output just the download count
        } elseif ( $type == 'count' ) {
            return $count;

        // Output just the icon
        } elseif ( $type == 'icon' ) {
            return '<a href="'.$href.'" ' . $params . '>' . $display_icon . '</a>';

        // Output post article
        } elseif ( $type == 'post' ) {

            // Check file's featured image
            $feat_image = wp_get_attachment_image_src( get_post_thumbnail_id( $file_id ), 'entry-cropped' );
            if ( isset( $feat_image[0] ) && $feat_image[0] != '' ) {
                $bg_image = ' style="background-image: url(' . $feat_image[0] . ');"';
                $display_icon = '';

            // Other stuff
            } else {

                if ( $default_img != '' ) {
                    $bg_image = ' style="background-image: url(' . base64_decode( $default_img ) . ');"';
                    $display_icon = '';
    
                // Other stuff
                } else {

                    // Check for resource type featured image
                    $resource_types = get_the_terms( $file_id, $TAXONOMIES->taxonomy_resource_types );
                    
                    $bg_image = ''; // Default to empty
                    if ( is_array( $resource_types ) ) {
                        foreach ( $resource_types as $resource_type ) {
                            $resource_id = $resource_type->term_id ?? false;
                            if ( $resource_id && $resource_type_feat_image = get_term_meta( $resource_id, 'featured-image', true ) ) {
                                $bg_image = ' style="background-image: url(' . filter_var( $resource_type_feat_image, FILTER_SANITIZE_URL ) . ');"';
                                $display_icon = '';
                                break; // Use the first valid image found
                            }
                        }
                    }
                }
            }

            // Meta
            $meta = [ '<span class="erifl-date">' . get_the_date( 'F j, Y', $file_id ) . '</span>' ];

            // Include download count?
            if ( $download_count && $count > 0 ) {
                // translators: 1: The number of times the file was downloaded, 2: "time" or "times" depending on the count.
                $downloaded_string = sprintf( __( 'Downloaded %1$s %2$s', 'eri-file-library' ),
                    $count,
                    'time'.$HELPERS->include_s( $count )
                );
                $meta[] = '<span class="erifl-count">' . sanitize_text_field( apply_filters( 'erifl_downloaded_count_string', $downloaded_string, $count, $file ) ) . '</span>';
            }

            // Format entry-meta line
            $tax_terms = implode( ', ', $term_links );
            $meta[] = '<span class="erifl-tax-terms">' . $tax_terms.'</span>';

            // Hooker
            $meta = filter_var_array( apply_filters( 'erifl_post_meta', $meta, $file ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );

            // Return it
            return '<div ' . $params . '>
                ' . do_action( 'erifl_before_post', $file, $type ) . '
                <a href="' . $href . '"><span class="erifl-image"' . $bg_image . '>' . $display_icon . '</span></a>
                <div class="erifl-content">
                    <div class="erifl-title"><h4><a href="' . $href . '">' . $title . '</a> - ' . $display_formats . '</h4></div>
                    <div class="erifl-meta">
                        ' . html_entity_decode( implode( ' | ', $meta ) ) . '
                    </div>
                    <div class="erifl-desc">' . $desc . '</div>
                </div>
            </div>';
        }

        return false;
    } // End file()


    /**
     * Add the ajax function for the shortcode
     *
     * @return void
     */
    public function ajax() {
        // Check the nonce
        check_ajax_referer( $this->nonce, 'nonce' );

        // Log errors here
        $errors = [];

        // Classes
		$POST_TYPE = new PostType();
        $HELPERS = new Helpers();

        // Get the file
        $file_id = isset( $_REQUEST[ 'fileID' ] ) ? absint( $_REQUEST[ 'fileID' ] ) : '';
        if ( $file_id ) {
            if ( $file = $POST_TYPE->get_file( $file_id ) ) {

                // Get the current user
                $user_id = get_current_user_id();
                $user_ip = !$user_id ? $HELPERS->get_user_ip() : false;

                /**
                 * COUNT THE # OF DOWNLOADS ON POST META
                 */
                // Check if there are already counts on this post
                $count = absint( get_post_meta( $file_id, $POST_TYPE->meta_key_download_count, true ) );
                $count++;

                // Update the post meta
                update_post_meta( $file_id, $POST_TYPE->meta_key_download_count, $count );

                // Update the post with the last downloaded date/time
                update_post_meta( $file_id, $POST_TYPE->meta_key_last_downloaded, current_time( 'mysql' ) );
                update_post_meta( $file_id, $POST_TYPE->meta_key_last_downloaded_by, $user_id );

                /**
                 * ADD TRACKING
                 */
                if ( (new Settings())->is_tracking() ) {
                    $track = (new Database())->add_record( $user_id, $file_id );
                    if ( !$track ) {
                        error_log( sprintf( __( 'Failed to track download for File ID %d.', 'eri-file-library' ), intval( $file_id ) ) ); // phpcs:ignore 
                    }
                }

                /**
                 * ALLOW HOOKS
                 */
                $url = $POST_TYPE->file_url( $file_id );
                $url = apply_filters( 'erifl_download_file_url', $url, $file, $user_id, $user_ip );

                do_action( 'erifl_after_file_downloaded', $file, $url, $user_id, $user_ip );

                /**
                 * SEND BACK FILE URL
                 */
                wp_send_json_success( [
                    'type' => 'success',
                    'url'  => $url
                ] );

            // Errors
            } else {
                // translators: %s is the file ID that does not exist.
                $errors[] = sprintf( __( 'File ID %s does not exist.', 'eri-file-library' ), 
                    $file_id 
                );
            }
        } else {
            $errors[] = __( 'No file ID found.', 'eri-file-library' );
        }

        // Failure
        wp_send_json_error( ERIFL_NAME . ': ' . implode( ' | ', $errors ) );
    } // End ajax()
    
    
    /**
     * Enque the JavaScript
     *
     * @return void
     */
    public function enqueue_scripts() {
        if ( is_admin() ) {
            return;
        }

        // JS
        $js_handle = ERIFL__TEXTDOMAIN . '_script';
        wp_register_script( $js_handle, ERIFL_JS_PATH . 'front-end.js', [ 'jquery' ], ERIFL_SCRIPT_VERSION, true );
        wp_localize_script( $js_handle, ERIFL__TEXTDOMAIN, [
            'nonce'       => wp_create_nonce( $this->nonce ),
            'ajaxurl'     => admin_url( 'admin-ajax.php' ) 
        ] );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( $js_handle );

        // CSS
        $css_handle = ERIFL__TEXTDOMAIN . '_style';
        wp_register_style( $css_handle, ERIFL_CSS_PATH . 'front-end.css', [], ERIFL_SCRIPT_VERSION );
        wp_enqueue_style( $css_handle );
    } // End enqueue_scripts()


    /**
     * Get user download history
     * 
     * USAGE: [erifl_user_download_history user_id="" icons="true"]
     *
     * @param array $atts
     * @return string|void
     */
    public function user_download_history( $atts ) {
        // First let's make sure we're tracking
        $SETTINGS = new Settings();
        if ( !$SETTINGS->is_tracking() ) {
            return;
        }

        // Params
        $atts = shortcode_atts( [ 'user_id' => '', 'type' => 'link' ], $atts );
        $user_id = !empty( $atts[ 'user_id' ] ) ? absint( $atts[ 'user_id' ] ) : get_current_user_id();
        $type = sanitize_key( $atts[ 'type' ] );
        $types = [ 'link', 'button', 'full', 'post', 'title' ];
        if ( !in_array( $type, $types ) ) {
            return (new Helpers())->admin_error( __( '"type" shortcode parameter must be one of the following:', 'eri-file-library' ) . ' <code>' . implode( '</code>, <code>', $types ) . '</code>' );
        }

        // Get their downloads
        $downloads = (new Database())->get_user_download_history( $user_id );
        if ( !empty( $downloads ) ) {
            
            $shortcode_tag = (new Settings())->shortcode_tag();

            // Start building the list of downloads
            $output = '<ul class="erifl-user-download-history type-' . esc_attr( $type ) . '" data-user="' . esc_attr( $user_id ) . '">';
    
                foreach ( $downloads as $download ) {
                    $file_id = absint( $download->file_id );
                    $date = sanitize_text_field( $download->last_download_time );
                    $date = gmdate( 'F j, Y g:i A', strtotime( $date ) );

                    $output .= '<li data-file-id="' . esc_attr( $file_id ) . '" title="' . esc_html( __( 'Last Downloaded: ', 'eri-file-library' ) ) . ' ' . esc_html( $date ) . '">
                        ' . wp_kses_post( do_shortcode( '[' . $shortcode_tag . ' id="' . $file_id . '" type="' . $type . '" ignore_pre_post="true"]' ) ) . '
                    </li>';
                }
    
            $output .= '</ul>';

            return $output;
        }
        return;
    } // End user_download_history()


    /**
     * Get top downloads
     * 
     * USAGE: [erifl_top_downloads qty=""]
     *
     * @param array $atts
     * @return string|void
     */
    public function top_downloads( $atts ) {
        // Params
        $atts = shortcode_atts( [ 'qty' => 10, 'type' => 'link' ], $atts );
        $qty = absint( $atts[ 'qty' ] );
        $type = sanitize_key( $atts[ 'type' ] );
        $types = [ 'link', 'button', 'full', 'post', 'title' ];
        if ( !in_array( $type, $types ) ) {
            return (new Helpers())->admin_error( __( '"type" shortcode parameter must be one of the following:', 'eri-file-library' ) . ' <code>' . implode( '</code>, <code>', $types ) . '</code>' );
        }

        // Get their downloads
        $downloads = (new Database())->get_top_file_counts( $qty );
        if ( !empty( $downloads ) ) {
            
            $shortcode_tag = (new Settings())->shortcode_tag();

            // Start building the list of downloads
            $output = '<ul class="erifl-top-downloads type-' . esc_attr( $type ) . '">';
    
                foreach ( $downloads as $download ) {
                    $file_id = absint( $download[ 'file_id' ] );
                    
                    if ( $type != 'post' ) {
                        $downloads = absint( $download[ 'downloads' ] );
                        $incl_count = '<span class="erifl-downloads">' . __( 'Downloads:', 'eri-file-library' ) . ' <strong>' .$downloads . '</strong></span>';
                    } else {
                        $incl_count = '';
                    }

                    $output .= '<li data-file-id="' . esc_attr( $file_id ) . '">
                        ' . wp_kses_post( do_shortcode( '[' . $shortcode_tag . ' id="' . $file_id . '" type="' . $type . '" ignore_pre_post="true"]' ) ) . wp_kses_post( $incl_count ) .'
                    </li>';
                }
    
            $output .= '</ul>';

            return $output;
        }
        return;
    } // End top_downloads()


    /**
     * Get a file list
     * 
     * USAGE: [erifl_file_list type="link" file_ids="" resource_types="" target_audiences="" formats="" required_roles="" required_meta_keys="" order="DESC" orderby="title" per_page="25" unique_id=""]
     *
     * @param array $atts
     * @return string|void
     */
    public function file_list( $atts ) {
        // Params
        $atts = shortcode_atts( [ 
            'type'               => 'link' ,
            'file_ids'           => '',
            'resource_types'     => '',
            'target_audiences'   => '',
            'formats'            => '',
            'required_roles'     => '',
            'required_meta_keys' => '',
            'order'              => 'ASC',
            'orderby'            => 'title',
            'per_page'           => 10,
            'unique_id'          => ''
        ], $atts );

        $type = sanitize_key( $atts[ 'type' ] );
        $types = [ 'link', 'button', 'full', 'post', 'title' ];
        if ( !in_array( $type, $types ) ) {
            return (new Helpers())->admin_error( __( '"type" shortcode parameter must be one of the following:', 'eri-file-library' ) . ' <code>' . implode( '</code>, <code>', $types ) . '</code>' );
        }

        $file_ids = sanitize_text_field( $atts[ 'file_ids' ] );
        $file_ids = empty( $file_ids ) ? [] : array_map( 'trim', explode( ',', $file_ids ) );

        $resource_types = sanitize_text_field( $atts[ 'resource_types' ] );
        $resource_types = empty( $resource_types ) ? [] : array_map( 'trim', explode( ',', $resource_types ) );

        $target_audiences = sanitize_text_field( $atts[ 'target_audiences' ] );
        $target_audiences = empty( $target_audiences ) ? [] : array_map( 'trim', explode( ',', $target_audiences ) );

        $formats = sanitize_text_field( $atts[ 'formats' ] );
        $formats = empty( $formats ) ? [] : array_map( 'trim', explode( ',', $formats ) );

        $required_roles = sanitize_text_field( $atts[ 'required_roles' ] );
        $required_roles = empty( $required_roles ) ? [] : array_map( 'trim', explode( ',', $required_roles ) );

        $required_meta_keys = sanitize_text_field( $atts[ 'required_meta_keys' ] );
        $required_meta_keys = empty( $required_meta_keys ) ? [] : array_map( 'trim', explode( ',', $required_meta_keys ) );
        
        $order = strtoupper( sanitize_text_field( $atts[ 'order' ] ) );
        $orderby = sanitize_key( $atts[ 'orderby' ] );
        $per_page = absint( $atts[ 'per_page' ] );
        $unique_id = sanitize_key( $atts[ 'unique_id' ] );

        // Pagination
        $current_page = isset( $_GET[ 'erifl-page' ] ) ? absint( $_GET[ 'erifl-page' ] ) : 1; // phpcs:ignore 
        $offset = ( $current_page - 1 ) * $per_page;

        // Get the files        
        $file_data = (new PostType())->get_files( 
            $file_ids, 
            $resource_types, 
            $target_audiences, 
            $formats, 
            $required_roles,
            $required_meta_keys,
            $order, 
            $orderby, 
            $per_page, 
            $offset 
        );
        $files = $file_data[ 'files' ];
        
        if ( !empty( $files ) ) {

            // Page count
            $total_files = $file_data[ 'count' ];
            $total_pages = ceil( $total_files / $per_page );

            // The shortcode tag
            $shortcode_tag = (new Settings())->shortcode_tag();

            // ID
            $incl_id = $unique_id ? ' id="' . $unique_id . '"' : '';

            // Start building the list of downloads
            $output = '<ul' . wp_kses_post( $incl_id ) . ' class="erifl-file-list type-' . esc_attr( $type  ). '">';
    
                foreach ( $files as $file ) {
                    $file_id = absint( $file[ 'ID' ] );
                    
                    $output .= '<li data-file-id="' . esc_attr( $file_id ) . '">
                        ' . wp_kses_post( do_shortcode( '[' . $shortcode_tag . ' id="' . $file_id . '" type="' . $type . '" ignore_pre_post="true"]' ) ) . '
                    </li>';
                }
    
            $output .= '</ul>';

            // Add pagination if necessary
            if ( $total_files > $per_page ) {
                $pagination = '';
            
                // Add Previous Link
                if ( $current_page > 1 ) {
                    $pagination .= '<a href="' . esc_url( add_query_arg( 'erifl-page', $current_page - 1, get_permalink() ) ) . '" class="erifl-pagination-prev button" data-id="' . $unique_id . '">&laquo; ' . __( 'Previous', 'eri-file-library' ) . '</a>';
                }
            
                // Current Page / Total Pages
                $pagination .= ' <span class="erifl-pagination-current">' . __( 'Page', 'eri-file-library' ) . ' ' . $current_page . ' of ' . $total_pages . '</span> ';
            
                // Add Next Link
                if ( $current_page < $total_pages ) {
                    $pagination .= '<a href="' . esc_url( add_query_arg( 'erifl-page', $current_page + 1, get_permalink() ) ) . '" class="erifl-pagination-next button" data-id="' . $unique_id . '">' . __( 'Next', 'eri-file-library' ) . ' &raquo;</a>';
                }
            
                // Add pagination to the output
                $output .= '<div class="erifl-pagination">' . wp_kses_post( $pagination ) . '</div>';
            }
            

            return $output;
        }
        return;
    } // End file_list()

}