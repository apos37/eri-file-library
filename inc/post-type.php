<?php
/**
 * Post Type
 */


/**
 * Define Namespaces
 */
namespace Apos37\EriFileLibrary;
use Apos37\EriFileLibrary\Helpers;
use Apos37\EriFileLibrary\Database;
use Apos37\EriFileLibrary\Taxonomies;
use Apos37\EriFileLibrary\Settings;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Initiate the class
 */
add_action( 'init', function() {
	(new PostType())->init();
} );


/**
 * The class
 */
class PostType {

	/**
	 * Post type
	 *
	 * @var string
	 */
	public $post_type = 'erifl-files';


	/**
	 * Options
	 *
	 * @var string
	 */
	public $option_folder = 'erifl_folder';
    public $option_tracking = 'erifl_tracking';
    public $option_admin_param = 'erifl_admin_param';

    /**
     * Meta keys
     *
     * @var string
     */
    public $meta_key_url = 'url';
    public $meta_key_description = 'description';
    public $meta_key_download_count = 'download_count';
    public $meta_key_req_roles = 'required_roles';
    public $meta_key_req_meta_key = 'required_meta_key';
    public $meta_key_last_downloaded = 'last_downloaded';
    public $meta_key_last_downloaded_by = 'last_downloaded_by';
    public $meta_key_error = 'error';
    public $meta_key_error_msg = 'error_msg';


    /**
     * Nonces
     *
     * @var string
     */
    private $save_post_nonce = 'erifl_save_post_nonce';
    private $quick_edit_nonce = 'erifl_quick_edit_nonce';
    private $bulk_edit_nonce = 'erifl_bulk_edit_nonce';
    private $where_used_nonce = 'erifl_where_used_nonce';


    /**
     * Cache group
     *
     * @var string
     */
    private $cache_group = 'erifl_file_lists';
    

    /**
     * Constructor
     */
    public function __construct() {

        // Allow post type and meta keys to be updated
        $keys = [
            'post_type'                   => $this->post_type,
            'meta_key_url'                => $this->meta_key_url,
            'meta_key_description'        => $this->meta_key_description,
            'meta_key_download_count'     => $this->meta_key_download_count,
            'meta_key_req_roles'          => $this->meta_key_req_roles,
            'meta_key_req_meta_key'       => $this->meta_key_req_meta_key,
            'meta_key_last_downloaded'    => $this->meta_key_last_downloaded,
            'meta_key_last_downloaded_by' => $this->meta_key_last_downloaded_by,
            'meta_key_error'              => $this->meta_key_error,
            'meta_key_error_msg'          => $this->meta_key_error_msg,
        ];

        // Loop through each meta key and apply the filter and sanitize
        foreach ( $keys as $key => $value ) {
            $this->{$key} = sanitize_key( apply_filters( "erifl_{$key}", $value ) );
        }

    } // End __construct()


	/**
	 * Load on it
	 *
	 * @return void
	 */
	public function init() {
		
		// Register the post type
        $this->register_post_type();

		// Don't allow indexing
        add_filter( 'wp_robots', [ $this, 'noindex_nofollow' ] );

        // Redirect the file posts
        add_action( 'template_redirect', [ $this, 'redirect' ] );

		// Change the "Add Title" label
        add_filter( 'gettext', [ $this, 'title' ] );

        // Add the Date and Author under the title
        add_action( 'edit_form_after_title', [ $this, 'add_date_author' ] );

        // Add meta boxes
        add_action( 'admin_init', [ $this, 'add_meta_boxes' ] );

        // Save meta boxes
        add_action( 'save_post', [ $this, 'save_meta_boxes' ] );

        // Delete file from directory if post is deleted
        add_action( 'before_delete_post', [ $this, 'delete_file' ] );

        // Add the enctype to the post form
        add_action( 'post_edit_form_tag', [ $this, 'add_enctype' ] );

        // Allow style display attributes
        add_filter( 'safe_style_css', [ $this, 'safe_style_css' ] );

        // Admin columns
        add_filter( 'manage_'.$this->post_type.'_posts_columns', [ $this, 'admin_columns' ] );
        add_action( 'manage_'.$this->post_type.'_posts_custom_column', [ $this, 'admin_column_content' ], 10, 2 );
        add_filter( 'manage_edit-'.$this->post_type.'_sortable_columns', [ $this, 'sort_admin_columns' ] );
        add_action( 'pre_get_posts', [ $this, 'filter_admin_columns' ] );

        // Filter for admin list table
        add_action( 'restrict_manage_posts', [ $this, 'custom_admin_filter' ] );

        // Remove Views from action links
        add_filter( 'page_row_actions', [ $this, 'remove_views_action_link' ], 10, 2 );

        // The quick edit / bulk edit box
		add_action( 'bulk_edit_custom_box', [ $this, 'quick_edit_box' ], 10, 2 );
		add_action( 'quick_edit_custom_box', [ $this, 'quick_edit_box' ], 10, 2 );

        // Save bulk/quick edits
        add_action( 'save_post', [ $this, 'save_quick_edits' ] );

        // Bulk/quick edit ajax
        add_action( 'wp_ajax_erifl_save_bulk_edit', [ $this, 'ajax_bulk_edit' ] );

		// Remove meta boxes
        add_action( 'do_meta_boxes', [ $this, 'remove_meta_boxes' ], 1, 3 );
        if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
            add_action( 'admin_init', [ $this, 'remove_yoast' ] );
        }

        // Remove breadcrumbs from X Theme
        if ( is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
            add_filter( 'x_breadcrumbs_data', [ $this, 'remove_breadcrumbs' ], 10, 2 );
        }

        // Enqueue scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		
	} // End init()


	/**
     * Register Custom Post Type
     */
    public function register_post_type() {
		$menu_label = (new Settings())->admin_menu_label();

        $labels = [
            'name'                     => _x( 'File Library', 'Post Type General Name', 'eri-file-library' ),
            'singular_name'            => _x( 'File', 'Post Type Singular Name', 'eri-file-library' ),
            'menu_name'                => $menu_label,
            'name_admin_bar'           => __( 'Files', 'eri-file-library' ),
            'archives'                 => __( 'File Archives', 'eri-file-library' ),
            'attributes'               => __( 'File Attributes', 'eri-file-library' ),
            'parent_item_colon'        => __( 'Parent File:', 'eri-file-library' ),
            'all_items'                => __( 'All Files', 'eri-file-library' ),
            'add_new_item'             => __( 'Add New File', 'eri-file-library' ),
            'add_new'                  => __( 'Add New', 'eri-file-library' ),
            'new_item'                 => __( 'New File', 'eri-file-library' ),
            'edit_item'                => __( 'Edit File', 'eri-file-library' ),
            'update_item'              => __( 'Update File', 'eri-file-library' ),
            'view_item'                => __( 'View File', 'eri-file-library' ),
            'view_items'               => __( 'View Files', 'eri-file-library' ),
            'search_items'             => __( 'Search Files', 'eri-file-library' ),
            'not_found'                => __( 'No files found', 'eri-file-library' ),
            'not_found_in_trash'       => __( 'No files found in Trash', 'eri-file-library' ),
            'featured_image'           => __( 'Featured Image', 'eri-file-library' ),
            'set_featured_image'       => __( 'Set featured image', 'eri-file-library' ),
            'remove_featured_image'    => __( 'Remove featured image', 'eri-file-library' ),
            'use_featured_image'       => __( 'Use as featured image', 'eri-file-library' ),
            'insert_into_item'         => __( 'Insert into file', 'eri-file-library' ),
            'uploaded_to_this_item'    => __( 'Uploaded to this file', 'eri-file-library' ),
            'items_list'               => __( 'Files list', 'eri-file-library' ),
            'items_list_navigation'    => __( 'Files list navigation', 'eri-file-library' ),
            'filter_items_list'        => __( 'Filter files list', 'eri-file-library' ),
            'filter_by_date'           => __( 'Filter by date', 'eri-file-library' ),
            'item_published'           => __( 'File published.', 'eri-file-library' ),
            'item_published_privately' => __( 'File published privately.', 'eri-file-library' ),
            'item_reverted_to_draft'   => __( 'File reverted to draft.', 'eri-file-library' ),
            'item_trashed'             => __( 'File trashed.', 'eri-file-library' ),
            'item_scheduled'           => __( 'File scheduled.', 'eri-file-library' ),
            'item_updated'             => __( 'File updated.', 'eri-file-library' ),
            'item_link'                => __( 'File Link', 'eri-file-library' ),
            'item_link_description'    => __( 'A link to a file.', 'eri-file-library' ),
        ];        

        $args = [
            'label'                 => $menu_label,
            'description'           => $menu_label,
            'labels'                => $labels,
            'supports'              => [ 'title', 'thumbnail' ],
            'taxonomies'            => (new Taxonomies())->get_addt_taxonomies(),
            'hierarchical'          => true,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_icon'             => 'dashicons-portfolio',
            'menu_position'         => 10,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => true,
            'publicly_queryable'    => true,
            'query_var'             => $this->post_type,
            'capability_type'       => 'page',
            'show_in_rest'          => true
        ];

        register_post_type( $this->post_type, $args );
    } // End register_post_type()


    /**
     * Get the folder name
     *
     * @return void
     */
    public function folder_name() {
        $folder_option = sanitize_key( get_option( $this->option_folder, $this->post_type ) );
        return ( $folder_option && $folder_option != '' ) ? $folder_option : $this->post_type;
    } // End folder_name()
    

	/**
     * The folder path
     *
     * @return void
     */
    public function folder_path( $abspath = false, $folder_name = null ) {
        $uploads_dir = wp_get_upload_dir();
        if ( is_null( $folder_name ) ) {
            $folder_name = $this->folder_name();
        }

        // Determine the base path (basedir or baseurl)
        $base_path = $abspath ? $uploads_dir[ 'basedir' ] : $uploads_dir[ 'baseurl' ];

        // Allow others to change the base path using a filter hook
        $base_path = sanitize_text_field( apply_filters( 'erifl_custom_base_path', $base_path, $abspath ) );

        // Return the final path
        return $base_path . '/' . sanitize_text_field( $folder_name ) . '/';
    } // End folder_path()


    /**
     * Change the upload directory
     *
     * @param array $dirs
     * @return array
     */
    public function upload_dir( $dirs ) {
        // Get the folder name
        $folder_name = $this->folder_name();

        // Filter the base path before proceeding
        $base_path = apply_filters( 'erifl_custom_base_path', $dirs[ 'basedir' ], true ); // Absolute path filter
        $base_url = apply_filters( 'erifl_custom_base_path', $dirs[ 'baseurl' ], false ); // URL path filter

        // Update directory paths
        $dirs[ 'subdir' ] = '/' . $folder_name;
        $dirs[ 'path' ] = $base_path . '/' . $folder_name;
        $dirs[ 'url' ] = $base_url . '/' . $folder_name;

        return $dirs;
    } // End upload_dir()

    
    /**
     * Get the file's direct link
     *
     * @param int $file_id
     * @return string|false
     */
    public function file_url( $file_id = null, $abspath = false ) {
        $file_id = is_null( $file_id ) ? get_the_ID() : $file_id;
        $file_name = sanitize_text_field( get_post_meta( $file_id, $this->meta_key_url, true ) );
        return $file_name ? $this->folder_path( $abspath ).$file_name : false;
    } // End file_url()


	/**
     * Prevent files from being indexed in search engines
     * Also add rel="nofollow" to links
     *
     * @param array $robots
     * @return array
     */
    public function noindex_nofollow( $robots ) {
        if ( is_singular( $this->post_type ) ) {
          	$robots[ 'noindex' ]  = true;
          	$robots[ 'nofollow' ] = true;
        }
        return $robots;
    } // End noindex_nofollow()


	/**
     * Redirect the file posts to the edit page
     *
     * @return void
     */
    public function redirect() {
        if ( is_singular( $this->post_type ) ) {
            wp_redirect( $this->file_url(), 301 ); exit();
        }
    } // End redirect()


	/**
	 * Change the "Add Title" label
	 *
	 * @param string $input
	 * @return string
	 */
    public function title( $input ) {
        global $post_type;
        if ( is_admin() && 'Add title' == $input && $this->post_type == $post_type ) {
            return __( 'Add Link Text', 'eri-file-library' );
        }
        return $input;
    } // End title()


	/**
     * Add the Date and Author under the title
     *
     * @return void
     */
    public function add_date_author() {
        global $post, $post_type;
        if ( is_admin() && $this->post_type == $post_type ) {
            $a_id = $post->post_author;
            $author = get_the_author_meta( 'display_name', $a_id );
    
            // translators: 1: The date the file was added, 2: The author who added the file.
            $output = sprintf( __( 'Added on %1$s by %2$s', 'eri-file-library' ),
                get_the_date(),
                $author
            );
    
            echo '<hr/><div id="access_id"><em>' . esc_html( $output ) . '</em></div>';
        }
    } // End add_date_author()
    

	/**
     * Add meta boxes
     *
     * @return void
     */
    public function add_meta_boxes() {
        $meta_boxes = [
            'url' => [
                'label'    => __( 'File URL', 'eri-file-library' ),
                'context'  => 'normal',
                'priority' => 'high'
            ],
            'description' => [
                'label'    => __( 'File Description', 'eri-file-library' ),
                'context'  => 'normal',
                'priority' => 'default'
            ],
            'instructions' => [
                'label'    => __( 'Instructions', 'eri-file-library' ),
                'context'  => 'normal',
                'priority' => 'low'
            ],
            'requirements' => [
                'label'    => __( 'Download Requirements', 'eri-file-library' ),
                'context'  => 'normal',
                'priority' => 'default'
            ],
            'where_used' => [
                'label'    => __( 'Where Used', 'eri-file-library' ),
                'context'  => 'normal',
                'priority' => 'low'
            ],
            'download_count' => [
                'label'    => __( 'Download Count', 'eri-file-library' ),
                'context'  => 'normal',
                'priority' => 'default'
            ],
        ];

        foreach ( $meta_boxes as $key => $meta_box ) {
            $callback = 'meta_box_'.$key;
            add_meta_box(
                'erifl_'.$key,
                $meta_box[ 'label' ],
                [ $this, $callback ],
                $this->post_type,
                $meta_box[ 'context' ],
                $meta_box[ 'priority' ]
            );
        }
    } // End add_meta_boxes()


    /**
     * Save file
     *
     * @param int $post_id
     * @return void
     */
    public function save_meta_boxes( $file_id ) {
        // Validation, including checking nonce
        if ( !(new Helpers())->can_save_post( $file_id, $this->post_type, $this->save_post_nonce, $this->save_post_nonce ) ) {
            return;
        }

        // Get the old file name if it exists
        $old_file = $this->file_url( $file_id, true ) ?? false;

        // Set the file name
        $file_name = isset( $_FILES[ $this->meta_key_url ][ 'name' ] ) ? sanitize_file_name( $_FILES[ $this->meta_key_url ][ 'name' ] ) : ''; // phpcs:ignore 
        if ( $file_name != '' ) {

            $uploadedfile = filter_var_array( $_FILES[ $this->meta_key_url ], FILTER_SANITIZE_FULL_SPECIAL_CHARS ); // phpcs:ignore 
            $upload_overrides = [ 'test_form' => false ];

            // Prevent duplicate filenames
            if ( !$this->link_exists( $file_id, $file_name ) ) {
                if ( $old_file ) {
                    wp_delete_file( $old_file );
                }

                add_filter( 'upload_dir', [ $this, 'upload_dir' ] );
                $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
                remove_filter( 'upload_dir', [ $this, 'upload_dir' ] );
                
                if ( $movefile && !isset( $movefile[ 'error' ] ) ) {
                    update_post_meta( $file_id, $this->meta_key_url, sanitize_text_field( $file_name ) );

                    // Extract file format and assign taxonomy term
                    $file_ext = pathinfo( $file_name, PATHINFO_EXTENSION );
                    if ( !empty( $file_ext ) ) {
                        $taxonomy = (new Taxonomies())->taxonomy_formats;

                        // Check if term exists, otherwise create it
                        $term = term_exists( $file_ext, $taxonomy );
                        if ( !$term ) {
                            $term = wp_insert_term( $file_ext, $taxonomy );
                        }

                        // Assign term to post
                        if ( !is_wp_error( $term ) ) {
                            wp_set_object_terms( $file_id, $file_ext, $taxonomy );
                        }
                    }

                } else {
                    update_post_meta( $file_id, 'error_msg', $movefile[ 'error' ] );
                }

            // Unique filename
            } else {
                
                $other_file_name = $this->link_exists( $file_id, $file_name, 'path' );
                update_post_meta( $file_id, 'error', $other_file_name );
            }
        }

        // Description
        if ( isset( $_POST[ $this->meta_key_description ] ) ) { // phpcs:ignore 
            update_post_meta( $file_id, $this->meta_key_description, sanitize_text_field( wp_unslash( $_POST[ $this->meta_key_description ] ) ) ); // phpcs:ignore 
        }

        // Download count
        if ( isset( $_POST[ $this->meta_key_download_count ] ) ) { // phpcs:ignore 
            update_post_meta( $file_id, $this->meta_key_download_count, absint( wp_unslash( $_POST[ $this->meta_key_download_count ] ) ) ); // phpcs:ignore 
        }

        // Roles Required to Download
        if ( isset( $_POST[ $this->meta_key_req_roles ] ) ) { // phpcs:ignore 
            if ( is_array( $_POST[ $this->meta_key_req_roles ] ) ) { // phpcs:ignore 
                $roles_array = array_map( 'sanitize_text_field', wp_unslash( $_POST[ $this->meta_key_req_roles ] ) ); // phpcs:ignore 
                $roles_string = implode( ',', $roles_array );
            } else {
                $roles_string = '';
            }
            update_post_meta( $file_id, $this->meta_key_req_roles, $roles_string );
        } else {
            update_post_meta( $file_id, $this->meta_key_req_roles, '' );
        } 
        
        // Meta Key Required to Download
        if ( isset( $_POST[ $this->meta_key_req_meta_key ] ) ) { // phpcs:ignore 
            update_post_meta( $file_id, $this->meta_key_req_meta_key, sanitize_key( wp_unslash( $_POST[ $this->meta_key_req_meta_key ] ) ) ); // phpcs:ignore 
        }
    } // End save_meta_boxes()


    /**
     * Check if link/url already exists
     *
     * @param [type] $current_post
     * @param [type] $url
     * @param boolean $type
     * @return string|false
     */
    public function link_exists( $current_post, $url, $type = false ) {
        $args = [
            'post_type'  => $this->post_type,
            'meta_query' =>[ // phpcs:ignore 
                [
                    'key' => $this->meta_key_url,
                    'value' => $url,
                ]
            ],
            'fields' => 'ids'
        ];
        $query = new \WP_Query( $args );
        $ids = $query->posts;

        $results = false;
        if ( !empty( $ids ) ) {
            if ( $ids[0] == $current_post ) {
                return false;
            }

            if ( $type == 'message' ) {

                // translators: %s is the file name of the uploaded file.
                $message = sprintf( __( 'Error! Another file already exists with the same file name "%s". Please try uploading a different file or trash this post if you don\'t need it.', 'eri-file-library' ),
                    basename( $url )
                );

                $edit_link = add_query_arg( [
                    'post'   => $ids[0],
                    'action' => 'edit'
                ], admin_url( 'post.php' ) );

                $results .= '<div style="margin-top: 10px; background-color: red; padding: 15px 15px 10px 15px; border-radius: 4px; color: #fff;">
                    '.$message.'
                    <ul>
                        <li style="list-style-type: disc; margin-left: 16px;"><a href="' . $edit_link . '" style="color: #fff; font-weight: bold;">'.get_the_title( $ids[0] ).'</a></li>
                    </ul>
                </div>';

            } elseif ( $type == 'path' ) {
                $results .= get_post_meta( $ids[0], $this->meta_key_url, true );
            } else {
                $results = true;
            }
        }

        return $results;
    } // End link_exists()


    /**
     * Delete the file if the post is deleted
     *
     * @param int $postid
     * @return void
     */
    public function delete_file( $file_id ) {
        global $post_type;
        if ( $this->post_type !== $post_type ) {
            return;
        }
        
        if ( $file = $this->file_url( $file_id, true ) ) {
            wp_delete_file( $file );
            (new Database())->delete_file_records( $file_id );
        }
    } // End delete_file()


    /**
     * Add the enctype to the post form
     *
     * @return void
     */
    public function add_enctype() {
        echo ' enctype="multipart/form-data"';
    } // End add_enctype()


    /**
     * URL meta box
     *
     * @return string
     */
    public function meta_box_url( $post ) {
        // Add a nonce field so we can check for it later.
        wp_nonce_field( $this->save_post_nonce, $this->save_post_nonce );

        // Get the current file urls
        $filename = get_post_field( $this->meta_key_url, $post->ID );
        $full_url = $this->file_url( $post->ID );
        $abspath_url = $this->file_url( $post->ID, true );

        // Check that the file exists
        $file = false;
        if ( $abspath_url && is_readable( $abspath_url ) && filesize( $abspath_url ) > 0 ) {
            $file = $abspath_url;
        }
        
        // Error
        $error = get_post_field( $this->meta_key_error, $post->ID );
        $error_msg = get_post_field( $this->meta_key_error_msg, $post->ID );

        // If the file exists, warn
        if ( $file ) {
            $size = (new Helpers())->format_bytes( filesize( $file ) );

            if ( filesize( $file ) == 0 ) {
                $warn_size = ' style="font-weight: bold; color: red;"';
                $warn_text = ' <span style="font-weight: bold; color: red;">&larr; ' . __( 'Oh dear! something went wrong with your upload.', 'eri-file-library' ) . '</span>';
            } else {
                $warn_size = '';
                $warn_text = '';
            }
            $filesize = ' (<span'.$warn_size.'>'.$size.'</span>)'.$warn_text;
        } else {
            $filesize = '';
        }
        
        // Display any errors
        if ( $error && $error != '' ) {
            echo wp_kses_post( $this->link_exists( $post->ID, $error, 'message' ) );
            delete_post_meta( $post->ID, $this->meta_key_error );
        }

        // Check for an upload error
        if ( $error_msg && $error_msg != '' ) {
            $incl_upload_error_msg = '<span id="upload_error" class="error_msg" style="display: block;">'.$error_msg.'</span>';
            delete_post_meta( $post->ID, $this->meta_key_error_msg );
            delete_post_meta( $post->ID, $this->meta_key_url );
            $full_url = $this->folder_path();
        } else {
            $incl_upload_error_msg = '';
        }

        // Add the info to the page
        if ( $filename != '' ) {
            if ( !$file ) {
                echo '<span id="upload_error" class="error_msg" style="display: block;">' . esc_html__( 'Uh oh! The file does not exist. Possible reasons: there was an issue uploading it or it was deleted from the directory. You may want to try again. If the issue persists, try uploading a different file.', 'eri-file-library' ) . '</span>';
            }
            echo esc_html__( 'File Location:', 'eri-file-library' ) . ' <code><a href="'.esc_url( $full_url ).'">'.esc_url( $full_url ).'</a>'.wp_kses_post( $filesize ).'</code><br><br>';
        } else {
            echo esc_html__( 'Directory:', 'eri-file-library' ) . ' <code>'.esc_url( $this->folder_path() ).'</code><br><br>';
        }

        // Add the upload field
        echo '<input name="'.esc_attr( $this->meta_key_url ).'" type="file" id="files-upload" class="text">';

        echo '<span id="special-characters-warning" class="error_msg" style="display: none;">' . 
            esc_html__( 'Special characters were found in the filename, which isn\'t allowed, so the filename will be updated to work properly.', 'eri-file-library' ) . 
        '</span>' . 
        wp_kses_post( $incl_upload_error_msg );

        // Add a filesize disclaimer
        $max_upload_size = size_format( wp_max_upload_size() );

        // translators: %s is the maximum upload file size.
        echo '<em>' . esc_html( sprintf( __( 'Maximum file size: %s', 'eri-file-library' ),
            $max_upload_size
        ) ) . '</em>';
    } // End meta_box_url()


    /**
     * Description meta box
     *
     * @return string
     */
    public function meta_box_description( $post ) {
        // Get the description
        $desc = get_post_field( $this->meta_key_description, $post->ID );

        echo '<textarea name="'.esc_attr( $this->meta_key_description ).'" id="files-desc" rows="5" style="width: 100%">'.esc_html( $desc ).'</textarea>';
    } // End meta_box_description()


    /**
     * Instructions meta box
     *
     * @return string
     */
    public function meta_box_instructions( $post ) {
        // Get the data
        $shortcode_tag = (new Settings())->shortcode_tag();

        // Add it
        echo '<div>
            <h3 style="font-size: 1.2rem;">' . esc_html__( 'File ID', 'eri-file-library' ) . ': ' . esc_attr( $post->ID ) . '</h3>
            <p>' . esc_html__( 'This file can be displayed a number of ways using a single shortcode with different values for the "type" parameter', 'eri-file-library' ) . ': <span style="font-weight: bold;">[' . esc_attr( $shortcode_tag ) . ' id="' . esc_attr( $post->ID ) . '"' . esc_html( $this->admin_param() ) . ']</span></p>
            <ul style="list-style: square !important; padding-left: 30px !important;">
                <li>' . esc_html__( 'No "type" parameter will default to "link" type.', 'eri-file-library' ) . '</li>
                <li>' . esc_html__( 'Available types', 'eri-file-library' ) . ': <code>"link"</code>, <code>"button"</code>, <code>"full"</code>, <code>"post"</code>, <code>"icon"</code>, <code>"title"</code>, <code>"description"</code>, <code>"count"</code></li>
                <li>' . esc_html__( 'Override the title', 'eri-file-library' ) . ': <code>title=""</code></li>
                <li>' . esc_html__( 'Remove the pre-text and post-text from the title', 'eri-file-library' ) . ': <code>ignore_pre_post="true"</code> - ' . esc_html__( 'You can also change or remove them site-wide in Settings.', 'eri-file-library' ) . '</li>
                <li>' . esc_html__( 'Override the description', 'eri-file-library' ) . ': <code>desc=""</code></li>
                <li>' . esc_html__( 'Override the formats', 'eri-file-library' ) . ': <code>formats=""</code> - ' . esc_html__( 'You can also remove them site-wide in Settings.', 'eri-file-library' ) . '</li>
                <li>' . esc_html__( 'Override the icon', 'eri-file-library' ) . ': <code>icon=""</code> - ' . esc_html__( 'You may include a URL to use a custom icon, or one of the following options ', 'eri-file-library' ) . ': <code>logo-full</code> (' . esc_html__( 'Logo Full', 'eri-file-library' ) . '), <code>logo-file</code> (' . esc_html__( 'Logo File Only', 'eri-file-library' ) . '), <code>fa</code> (Font Awesome), <code>uni</code> (' . esc_html__( 'Unicode', 'eri-file-library' ) . ')</li>
                <li>' . wp_kses( __( 'To dynamically retrieve the File ID from the current post, you can store the File ID in a custom meta key and add the meta key to the shortcode instead of using the <code>id=""</code> parameter', 'eri-file-library' ), [ 'code' => [] ] ) . ': <code>custom_field=""</code></li>
                <li>' . esc_html__( 'To start your count with something else other than 0, you can use the following parameter:', 'eri-file-library' ) . ' <code>start_count="100"</code></li>
                <li>' . esc_html__( 'Add additional classes to the link element separated by spaces', 'eri-file-library' ) . ': <code>classes=""</code></li>
                <li>' . esc_html__( 'Replace the default image for the "post" display when a featured image isn\'t available', 'eri-file-library' ) . ': <code>default_img=""</code></li>
                <li>' . esc_html__( 'Hide the download count in the "post" display type', 'eri-file-library' ) . ': <code>dlc="false"</code></li>
            </ul>
        </div>';
    } // End meta_box_instructions()


    /**
     * Get the param from options or setting default
     *
     * @return void
     */
    public function admin_param() {
        $choice = '';
        $admin_param = get_option( $this->option_admin_param );
        if ( $admin_param && $admin_param != '' && $admin_param != 'url' ) {
            $choice = ' type="'.$admin_param.'"';
        }
        return $choice;
    } // End admin_param()


    /**
     * Where Used meta box
     *
     * @return string
     */
    public function meta_box_where_used( $post ) {
        // Get the admin folder
        $admin_folder = str_replace( site_url( '/' ), '', rtrim( admin_url(), '/' ) );

        // Get the current URL
        $current_url = '/'.$admin_folder.'/post.php?post='.$post->ID.'&action=edit';

        // Check if we are fetching posts first
        if ( !isset( $_GET[ 'where_used' ] ) ) { // phpcs:ignore

            $current_url = add_query_arg( [
                'post'       => $post->ID,
                'action'     => 'edit',
                'where_used' => true,
                'attr'       => 'id',
                'attr_is'    => $post->ID,
                '_wpnonce'   => wp_create_nonce( $this->where_used_nonce )
            ], admin_url( 'post.php' ) );

            echo '<br><a href="'.esc_url( $current_url ).'" class="button">' . esc_html__( 'Fetch posts where this file is used', 'eri-file-library' ) . '</a>';
        }

        // Add the links if found
        echo wp_kses_post( $this->find_shortcode_attribute() );
    } // End meta_box_where_used()


    /**
     * Find posts or pages that a shortcode is on
     *
     * @param string $where_used
     * @param string $attr
     * @param int $attr_is
     * @return string|false
     */
    public function find_shortcode_attribute() {
        if ( !isset( $_REQUEST[ '_wpnonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), $this->where_used_nonce ) ) {
            return;
        }

        // Are we finding it?
        if ( !isset( $_GET[ 'where_used' ] ) || !filter_var( wp_unslash( $_GET[ 'where_used' ] ), FILTER_VALIDATE_BOOLEAN ) ) {
            return;
        } 

        // The shortcode
        $shortcode = (new Settings())->shortcode_tag();

        // Get the post types
        $post_types = get_post_types();

        // Exclude Post Types
        $exclude = [
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_block',
            'wp_template',
            'wp_template_part',
            'wp_global_styles',
            'wp_navigation',
            'cs_template',
            'cs_user_templates',
            'um_form',
            'um_directory',
            'cs_global_block',
            'x-portfolio'
        ];
        foreach ( $post_types as $key => $post_type ) {
            if ( in_array( $post_type, $exclude ) ) {
                unset( $post_types[ $key ] );
            }
        }

        $post_types = filter_var_array( apply_filters( 'erifl_where_used_post_types', $post_types ), FILTER_SANITIZE_SPECIAL_CHARS );

        // Let's get the posts
        $the_query = new \WP_Query( [ 
            'post_type'      => $post_types,
            'posts_per_page' => -1,
            'post_status'    => 'all'
        ] );
        if ( $the_query->have_posts() ) {

            // Let's build the full shortcode we are looking for
            $full_shortcode_prefix = '['.$shortcode;

            // Start a list
            $results = '<p>' . __( 'The following posts and pages are currently using this file shortcode:', 'eri-file-library' ) . '</p>
            <ul id="erifl-where-used-list">';

            // Count found
            $found = 0;
            $post_count = $the_query->post_count();

            // For each list item...
            while ( $the_query->have_posts() ) {

                // Get the post
                $the_query->the_post();

                // Get the post content once
                $content = get_the_content();
                // $content = apply_filters( 'the_content', get_the_content() ); // Alternative that can be used in hook
                $content = wp_kses_post( apply_filters( 'erifl_where_used_content', $content ) );

                // Check if the content has this shortcode
                if ( strpos( $content, $full_shortcode_prefix ) !== false ) {

                    // no posts found
                    $found++;
                    
                    // The return the post
                    $post_type =  ucfirst( get_post_type() );
                    $post_status = get_post_status();

                    if ( $post_status == 'publish' ) {
                        $current_status = 'Published';
                    } elseif ( $post_status == 'draft' ) {
                        $current_status = 'Draft';
                    } elseif ( $post_status == 'private' ) {
                        $current_status = 'Private';
                    } elseif ( $post_status == 'archive' ) {
                        $current_status = 'Archived';
                    }

                    $results .= '<li><a href="'.get_the_permalink().'" target="_blank">' . get_the_title() . '</a> ('.$post_type.' ID: ' . get_the_ID() . ' - ' . __( 'Status', 'eri-file-library' ) . ': '.$current_status.')</li>';
                }
            }

            // If none found
            if ( $found == 0 ) {

                // Restore original Post Data
                wp_reset_postdata();
                    
                // translators: the number of posts and pages searched
                return sprintf( __( 'Shortcode not found on %s posts and pages. Post types searched:', 'eri-file-library' ),
                    $post_count
                ) . '<br>' . implode( ', ', $post_types );
            }

            // End the list
            $results .= '</ul>';

        } else {

            // Restore original Post Data
            wp_reset_postdata();
            
            // no posts found
            return __( 'No posts found.', 'eri-file-library' );
        }

        // Restore original Post Data
        wp_reset_postdata();

        // Return the results
        return $results;
    } // End find_shortcode_attribute()


    /**
     * Download Count meta box
     *
     * @return string
     */
    public function meta_box_download_count( $post ) {
        // Get the count
        $count = absint( get_post_field( $this->meta_key_download_count, $post->ID ) );
            
        // Add the field
        echo '<input name="' . esc_attr( $this->meta_key_download_count ) . '" type="text" value="' . esc_attr( $count ) . '" id="files-count" style="width: 180px; padding: 3px 8px; font-size: 1.25em; line-height: 100%; height: 1.7em; outline: 0; margin: 0 0 3px; background-color: #fff;">';
    } // End meta_box_download_count()


    /**
     * In-Depth Count meta box
     *
     * @return string
     */
    public function meta_box_requirements( $post ) {
        // Get the data
        $roles = sanitize_text_field( get_post_field( $this->meta_key_req_roles, $post->ID ) );
        $meta_key = sanitize_key( get_post_field( $this->meta_key_req_meta_key, $post->ID ) );
    
        // Convert stored roles string into an array
        $selected_roles = array_map( 'trim', explode( ',', $roles ) );
    
        // Get all roles
        $wp_roles = wp_roles();
        $all_roles = $wp_roles->roles;
    
        // Description
        echo '<p><em>' . esc_html__( 'Optionally set requirements for your users to be able to download this file. If no roles or meta key are required, then everyone (even logged-out users) can download it.', 'eri-file-library' ) . '</em></p>';
    
        // Roles checkboxes
        echo '<p><strong>' . esc_html__( 'Required User Roles:', 'eri-file-library' ) . '</strong></p>';
        echo '<div>';
        foreach ( $all_roles as $role_key => $role ) {
            $checked = in_array( $role_key, $selected_roles ) ? 'checked' : '';
            echo '<label for="role-' . esc_attr( $role_key ) . '" style="display: block; margin-bottom: 5px;">';
            echo '<input id="role-' . esc_attr( $role_key ) . '" type="checkbox" name="' . esc_attr( $this->meta_key_req_roles ) . '[]" value="' . esc_attr( $role_key ) . '" ' . esc_attr( $checked ) . '> ';
            echo esc_html( $role[ 'name' ] );
            echo '</label>';
        }
        echo '</div>';
    
        // Meta key field
        echo '<p><strong>' . esc_html__( 'Required User Meta Key (value must be 1):', 'eri-file-library' ) . '</strong></p>';
        echo '<input type="text" name="' . esc_attr( $this->meta_key_req_meta_key ) . '" value="' . esc_attr( $meta_key ) . '" pattern="[a-z0-9_-]+" title="' . esc_attr__( 'Only lowercase letters are allowed, no spaces.', 'eri-file-library' ) . '">';
    } // End meta_box_requirements()
    

    /**
     * Remove Meta Boxes
     *
     * @return void
     */
    public function remove_meta_boxes( $post_type, $context, $post ) {
        // Comments
        remove_meta_box( 'commentstatusdiv', $this->post_type, $context );
        remove_meta_box( 'commentsdiv', $this->post_type, $context );

        // Formats (since they are automatic)
        remove_meta_box( (new Taxonomies())->taxonomy_formats . 'div', $this->post_type, $context );

        // Download count (for non-admins only)
        if ( !current_user_can( 'administrator' ) ) {
            remove_meta_box( 'post_metadata_download_count', $this->post_type, $context );
        }

        // Yoast
        if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
            remove_meta_box( 'wpseo_meta', $this->post_type, $context );
        }

        // Slider Revolution
        if ( is_plugin_active( 'revslider/revslider.php' ) ) {
            remove_meta_box( 'slider_revolution_metabox', $this->post_type, $context );
        }
    } // End remove_meta_boxes ()
    

    /**
     * Remove Yoast SEO from Edit File page and admin columns if active
     *
     * @return void
     */
    public function remove_yoast() {
        add_filter( 'manage_edit-' . $this->post_type . '_columns', [ $this, 'wpseo_remove_admin_columns' ], 10, 1 );
    } // End remove_yoast()


    /**
     * Remove Yoast SEO admin columns
     *
     * @return void
     */
    public function wpseo_remove_admin_columns( $columns ) {
        unset( $columns[ 'wpseo-score' ] );
        unset( $columns[ 'wpseo-score-readability' ] );
        unset( $columns[ 'wpseo-title' ] );
        unset( $columns[ 'wpseo-metadesc' ] );
        unset( $columns[ 'wpseo-focuskw' ] );
        unset( $columns[ 'wpseo-links' ] );
        unset( $columns[ 'wpseo-linked' ] );
        return $columns;
    } // End wpseo_remove_admin_columns()
    

    /**
     * Admin columns
     *
     * @param array $columns
     * @return array
     */
    public function admin_columns( $columns ) {
        $TAXONOMIES = new Taxonomies();
        $columns = array_merge( [
            'cb'                                   => $columns[ 'cb' ],
            'title'                                => __( 'Title', 'eri-file-library' ),
            'post_modified'                        => __( 'Modified Date', 'eri-file-library' ),
            'desc'                                 => __( 'Description', 'eri-file-library' ),
            'url'                                  => __( 'URL', 'eri-file-library' ),
            'shortcode'                            => __( 'Shortcode', 'eri-file-library' ),
            'count'                                => __( 'Downloads', 'eri-file-library' ),
            'filesize'                             => __( 'File Size', 'eri-file-library' ),
            'requirements'                         => __( 'Requirements', 'eri-file-library' ),
            $TAXONOMIES->taxonomy_resource_types   => __( 'Resource Types', 'eri-file-library' ),
            $TAXONOMIES->taxonomy_target_audiences => __( 'Target Audiences', 'eri-file-library' ),
            'thumb'                                => __( 'Image', 'eri-file-library' ),
        ] );
        
        return $columns;
    } // End admin_columns()


    /**
     * Allow style display in wp_kses in span elements
     * https://wordpress.stackexchange.com/questions/173526/why-is-wp-kses-not-keeping-style-attributes-as-expected
     *
     * @param array $styles
     * @return array
     */
    public function safe_style_css( $styles ) {
        $styles[] = 'display';
        return $styles;
    } // End safe_style_css()


    /**
     * Admin column content
     *
     * @param string $column_name
     * @param int $post_id
     * @return string
     */
    public function admin_column_content( $column_name, $post_id ) {
        $HELPERS = new Helpers();

        // Post Modified
        if ( $column_name === 'post_modified' ) {

            // Get the date and author
            $date = get_post_modified_time( 'F j, Y g:i A', false, $post_id );
            $author = absint( get_post_meta( $post_id, '_edit_last', true ) );
    
            // If author is found
            if ( $author ) {
                $last_user = get_userdata( $author );
                $name = apply_filters( 'the_modified_author', $last_user->display_name );
            } else {
                $name = 'Unknown';
            }

            // Add it
            echo esc_html( $date ).'<br><em>By '.esc_attr( $name ).'</em>';
        }

        // Description
        if ( $column_name === 'desc' ) {
            $desc = sanitize_text_field( get_post_meta( $post_id, $this->meta_key_description, true ) );
            echo esc_html( $desc );
        }

        // URL
        if ( $column_name === 'url' ) {
            $url = sanitize_text_field( get_post_meta( $post_id, $this->meta_key_url, true ) );
            echo '<a href="'.esc_url( $this->file_url( $post_id ) ).'" target="_blank">'.esc_attr( $url ).'</a>';
        }

        // Shortcode
        if ( $column_name === 'shortcode' ) {
            $shortcode_tag = (new Settings())->shortcode_tag();
            echo '<div><a href="#" class="click-to-copy" style="display: block;">[' . esc_attr( $shortcode_tag ) . ' id="' . esc_attr( $post_id ) . '"' . esc_attr( $this->admin_param() ) . ']</a><span class="click-to-copy-copied" style="display: none; background: yellow; font-weight: bold; padding: 3px 5px;">' . __( 'Copied', 'eri-file-library' ) . '</span></div>';
        }
        
        // Taxonomies
        $TAXONOMIES = new Taxonomies();
        $stock_taxes = [ 
            $TAXONOMIES->taxonomy_resource_types,
            $TAXONOMIES->taxonomy_target_audiences
        ];
        $taxes = array_merge( $stock_taxes, $TAXONOMIES->get_addt_taxonomies() );
        foreach ( $taxes as $tax ) {
            if ( $column_name === $tax ) {
                $terms = get_the_terms( $post_id, $tax );
                if ( !empty( $terms ) ) {
                    $terms_output = [];
                    $family = [];
                    foreach ( $terms as $term ) {
                        $bold_parent = $term->parent == 0 ? 'bold' : 'normal';
                        $link = sprintf( '<a href="%s" style="font-weight: %s">%s</a>',
                            esc_url( add_query_arg( [ 'post_type' => $this->post_type, $tax => $term->slug ], 'edit.php' ) ),
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
                        $terms_output[] = $member[ 'parent' ] . $incl_children;
                    }

                    echo wp_kses_post( implode( ',<br>', $terms_output ) );
                } else {
                    echo '--';
                }
            }
        }

        // Downloads
        if ( $column_name === 'count' ) {
            $count = absint( get_post_meta( $post_id, $this->meta_key_download_count, true ) );
            echo esc_attr( $count );
        }

        // File Size
        if ( $column_name === 'filesize' ) {
            $url = sanitize_text_field( get_post_meta( $post_id,  $this->meta_key_url, true ) );
            $abspath_url = $this->file_url( $post_id, true );

            // What we'll return
            $formatted_bytes = false;

            // Check if the file exists
            if ( is_readable( $abspath_url ) ) {

                // Condense
                $file = $abspath_url;

                // Get filesize
                $bytes = filesize( $file );
                if ( $bytes > 0 ) {

                    // Warn if too large
                    if ( $bytes >= 5242880 ) {
                        $style = ' style="font-weight: bold; color: red;"';
                    } elseif ( $bytes >= 1048576 ) {
                        $style = ' style="color: red;"';
                    } else {
                        $style = '';
                    }

                    // Put it together
                    $formatted_bytes = '<span' . $style . '>' . $HELPERS->format_bytes( $bytes ) . '</span>';
                } 
            }

            if ( !$formatted_bytes ) {
                $formatted_bytes = '<span class="no-file-found"><em>' . __( 'No File Found.', 'eri-file-library' ) . '</em></span>';
            } else {
                $style = '';
            }

            // Add it
            echo wp_kses_post( $formatted_bytes );
        }

        // Requirements
        if ( $column_name === 'requirements' ) {
            $roles = sanitize_text_field( get_post_meta( $post_id, $this->meta_key_req_roles, true ) );
            if ( $roles ) {
                $role_slugs = array_map( 'trim', explode( ',', $roles ) );
            
                $editable_roles = get_editable_roles();
                $role_labels = [];
            
                foreach ( $role_slugs as $slug ) {
                    if ( isset( $editable_roles[ $slug ] ) ) {
                        $role_labels[] = $editable_roles[ $slug ][ 'name' ];
                    }
                }
            
                $display_roles = $role_labels ? __( 'Roles Required:', 'eri-file-library' ) . '<br><span class="selected-roles" data-roles="' . implode( ', ', $role_slugs ) . '"><code>' . implode( '</code>, <code>', $role_labels ) . '</code></span>' : '';
            } else {
                $display_roles = '<span class="selected-roles" data-roles=""></span>';
            }

            $meta_key = sanitize_key( get_post_meta( $post_id, $this->meta_key_req_meta_key, true ) );
            if ( $meta_key ) {
                $display_meta_key = __( 'Meta Key Required:', 'eri-file-library' ) . '<br><span class="meta-key"><code>' . $meta_key . '</code></span>';
            } else {
                $display_meta_key = '<span class="meta-key"></span>';
            }

            if ( !$roles && !$meta_key ) {
                echo '--';
            } else {
                $br = $roles && $meta_key ? '<br><br>' : '';
                $allowed_html = [ 
                    'br'   => [], 
                    'code' => [], 
                    'span' => [
                        'class'      => [],
                        'data-roles' => []
                    ]
                ];
                echo wp_kses( $display_roles, $allowed_html ) . wp_kses( $br, [ 'br' => [] ] ) . wp_kses( $display_meta_key, $allowed_html );
            }
        }

        // Featured Image
        if ( $column_name === 'thumb' ) {
            if ( function_exists( 'the_post_thumbnail' ) ) {
                echo wp_kses_post( the_post_thumbnail() );
            }
        }
    } // End admin_column_content()


    /**
     * Sort admin columns
     *
     * @param array $columns
     * @return array
     */
    public function sort_admin_columns( $columns ) {
        $columns[ 'url' ] = 'url';
        $columns[ 'post_modified' ] = 'post_modified';
        $columns[ 'count' ] = 'count';
        return $columns;
    } // End sort_admin_columns()


    /**
     * Filter admin columns
     *
     * @param object $query
     * @return void
     */
    public function filter_admin_columns( $query ) {
        $POSTTYPE = new PostType();
        global $pagenow;
        if ( !is_admin() || $pagenow != 'edit.php' || $query->get( 'post_type' ) != $POSTTYPE->post_type ) {
            return;
        }
    
        // Get the search term
        if ( $query->is_search() ) {
            $search_term = $query->get( 's' );
    
            // Add a custom filter for the search query
            add_filter( 'posts_search', function( $search, $wp_query ) use ( $POSTTYPE, $search_term ) {
                global $wpdb;
    
                if ( !$search_term ) {
                    return $search;
                }
    
                // Modify the search to include title and the filename stored in custom meta field
                $search = " AND ( 
                    {$wpdb->posts}.post_title LIKE '%" . esc_sql( $wpdb->esc_like( $search_term ) ) . "%' 
                    OR {$wpdb->posts}.ID IN (
                        SELECT post_id 
                        FROM {$wpdb->postmeta} 
                        WHERE meta_key = '" . esc_sql( $POSTTYPE->meta_key_url ) . "' 
                        AND meta_value LIKE '%" . esc_sql( $wpdb->esc_like( $search_term ) ) . "%'
                    )
                )";
    
                return $search;
            }, 10, 2 );
    
        // Other filters
        } else {
            
            // Order and orderby
            $orderby = $query->get( 'orderby' );
        
            if ( 'count' == $orderby ) {
                $query->set( 'meta_key', $POSTTYPE->meta_key_download_count );
                $query->set( 'orderby', 'meta_value_num' );
    
            } elseif ( 'url' == $orderby ) {
                $query->set( 'meta_key', $POSTTYPE->meta_key_url );
                $query->set( 'orderby', 'meta_value' );
    
            } elseif ( 'title' != $orderby ) {
                $query->set( 'orderby', 'date' );
                $query->set( 'order', 'DESC' );
            }
    
            // Prepare tax_query
            $TAXONOMIES = new Taxonomies();
            $stock_taxes = [ 
                $TAXONOMIES->taxonomy_formats,
                $TAXONOMIES->taxonomy_resource_types,
                $TAXONOMIES->taxonomy_target_audiences
            ];
            $taxes = array_merge( $stock_taxes, $TAXONOMIES->get_addt_taxonomies() );
            if ( !empty( $taxes ) ) {
                $tax_query = [];
                foreach ( $taxes as $tax ) {
                    $term = isset( $_GET[ $tax ] ) ? sanitize_key( $_GET[ $tax ] ) : ''; // phpcs:ignore 
                    if ( $term ) {
                        $tax_query[] = [
                            'taxonomy' => $tax,
                            'field'    => 'slug',
                            'terms'    => $term,
                            'operator' => 'IN'
                        ];
                    }
                }
    
                if ( !empty( $tax_query ) ) {
                    $query->set( 'tax_query', $tax_query );
                } else {
                    $query->set( 'tax_query', [] );
                }
            }
        }
    
        // Testing
        // dpr( $query, null, true );
    } // End filter_admin_columns()


    /**
     * Custom admin filter for pages and self-studies
     *
     * @return void
     */
    public function custom_admin_filter() {
        global $pagenow, $typenow;

        // Make sure we're not on front end and we're only sorting files
        if ( !is_admin() || $pagenow != 'edit.php' || $typenow != $this->post_type ) {
            return;
        }
        ?>
        <?php

        $TAXONOMIES = new Taxonomies();
        $stock_taxes = [ 
            $TAXONOMIES->taxonomy_formats,
            $TAXONOMIES->taxonomy_resource_types,
            $TAXONOMIES->taxonomy_target_audiences
        ];
        $taxes = array_merge( $stock_taxes, $TAXONOMIES->get_addt_taxonomies() );
        if ( !empty( $taxes ) ) {
            foreach ( $taxes as $tax ) {
                $taxonomy = get_taxonomy( $tax );
                if ( $taxonomy ) {

                    $selected_term = isset( $_GET[ $tax ] ) ? sanitize_key( $_GET[ $tax ] ) : ''; // phpcs:ignore 

                    ?>
                    <select name="<?php echo esc_attr( $tax ); ?>">
                        <option value=""><?php echo esc_attr( __( 'All', 'eri-file-library' ) . ' ' . strtolower( $taxonomy->label ) ); ?></option>
                        <?php
                        $terms = get_terms( [
                            'taxonomy'   => $tax,
                            'hide_empty' => false,
                        ] );
                        if ( !is_wp_error( $terms ) && !empty( $terms ) ) {
                            foreach ( $terms as $term ) {
                                ?>
                                <option value="<?php echo esc_attr( $term->slug ); ?>"<?php selected( $selected_term, $term->slug ); ?>><?php echo wp_kses_post( $term->name ); ?></option>
                                <?php
                            }
                        }
                        ?>
                    </select>
                    <?php
                }
            }
        }
    } // End custom_admin_filter()


    /**
     * Remove "views" from action links
     *
     * @param array $actions
     * @param object $post
     * @return array
     */
    public function remove_views_action_link( $actions, $post ) {
        if ( $this->post_type == $post->post_type ) {
            unset( $actions[ 'view' ] );
        }
        return $actions;
    } // End remove_views_action_link()


    /**
     * Quick edit and bulk edit
     * Instructions: https://webberzone.com/add-custom-fields-to-quick-edit-and-bulk-edit/
     *
     * @param string $column_name
     * @return void
     */
    public function quick_edit_box( $column_name, $current_post_type ) {
        if ( $column_name == 'requirements' && $current_post_type == $this->post_type ) {

            // Create a nonce field
            if ( current_filter() === 'quick_edit_custom_box' ) {
                wp_nonce_field( $this->quick_edit_nonce, $this->quick_edit_nonce );
            } else {
                wp_nonce_field( $this->bulk_edit_nonce, $this->bulk_edit_nonce );
            }

            // Get the roles
            $wp_roles = wp_roles();
            $roles = $wp_roles->roles;

            // Add the fields
            ?>
            <fieldset class="inline-edit-col-left col-<?php echo esc_attr( $column_name ); ?>">
                <!-- Requirements: Role Selection -->
                <div class="inline-edit-col quick-edit-<?php echo esc_attr( $column_name ); ?> roles">
                    <div class="required-roles-label"><?php esc_html_e( 'Required Roles', 'eri-file-library' ); ?></div>
                    <?php foreach ( $roles as $key => $role ) : ?>
                        <div class="<?php echo esc_attr( $column_name ); ?>-selection">
                            <label class="erifl-labels">
                                <input type="checkbox" name="<?php echo esc_attr( $this->meta_key_req_roles ); ?>[]" value="<?php echo esc_attr( $key ); ?>">
                                <?php echo esc_html( $role[ 'name' ] ); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Requirements: Meta Key -->
                <div class="inline-edit-col quick-edit-<?php echo esc_attr( $column_name ); ?> option">
                    <label class="inline-edit-group">
                        <span><?php esc_html_e( 'Required Meta Key', 'eri-file-library' ); ?></span>
                        <input type="text" name="<?php echo esc_attr( $this->meta_key_req_meta_key ); ?>">
                    </label>
                </div>
            </fieldset>
            <?php
        }
    } // End quick_edit_box()


    /**
	 * Save the bulk edits
	 *
	 * @param int $file_id
	 * @return void
	 */
	public function save_quick_edits( $file_id ) {
        // Run checks, including verifying nonce
        if ( !(new Helpers())->can_save_post( $file_id, $this->post_type, $this->quick_edit_nonce, $this->quick_edit_nonce, true ) ) {
            return;
        }

		/* OK, it's safe for us to save the data now. */
     
        // Option
        if ( !isset( $_POST[ $this->meta_key_req_roles ] ) && !isset( $_POST[ $this->meta_key_req_meta_key ] ) ) { // phpcs:ignore 
            return;
        }

        // Roles Required to Download
        if ( isset( $_POST[ $this->meta_key_req_roles ] ) ) { // phpcs:ignore 
            if ( is_array( $_POST[ $this->meta_key_req_roles ] ) ) { // phpcs:ignore 
                $roles_array = array_map( 'sanitize_text_field', wp_unslash( $_POST[ $this->meta_key_req_roles ] ) ); // phpcs:ignore 
                $roles_string = implode( ',', $roles_array );
            } else {
                $roles_string = '';
            }
            update_post_meta( $file_id, $this->meta_key_req_roles, $roles_string );
        } else {
            update_post_meta( $file_id, $this->meta_key_req_roles, '' );
        }
        
        // Meta Key Required to Download
        if ( isset( $_POST[ $this->meta_key_req_meta_key ] ) && $_POST[ $this->meta_key_req_meta_key ] !== '' ) { // phpcs:ignore 
            update_post_meta( $file_id, $this->meta_key_req_meta_key, sanitize_key( wp_unslash( $_POST[ $this->meta_key_req_meta_key ] ) ) ); // phpcs:ignore 
        }
	} // End save_quick_edits()


    /**
	 * Ajax for bulk edit
	 *
	 * @return void
	 */
	public function ajax_bulk_edit() {
		// Security check.
		check_ajax_referer( $this->bulk_edit_nonce, 'nonce' );
	
		// Get the post IDs.
		$post_ids = isset( $_POST[ 'post_ids'] ) ? wp_parse_id_list( wp_unslash( $_POST[ 'post_ids' ] ) ) : [];
        if ( empty( $post_ids ) ) {
            wp_send_json_success();
        }
		
		// Roles
		if ( isset( $_POST[ $this->meta_key_req_roles ] ) ) {
            $roles_array = array_map( 'sanitize_text_field', wp_unslash( $_POST[ $this->meta_key_req_roles ] ) );
            $roles_string = implode( ',', $roles_array );

            if ( $roles_string ) {
                foreach ( $post_ids as $post_id ) {
                    if ( !current_user_can( 'edit_post', $post_id ) ) {
                        continue;
                    }
                    update_post_meta( $post_id, $this->meta_key_req_roles, $roles_string );
                }
            }
		}

        // Meta Key
        if ( isset( $_POST[ $this->meta_key_req_meta_key ] ) ) {

            $meta_key = sanitize_key( wp_unslash( $_POST[ $this->meta_key_req_meta_key ] ) );
			
            if ( $meta_key ) {
                foreach ( $post_ids as $post_id ) {
                    if ( !current_user_can( 'edit_post', $post_id ) ) {
                        continue;
                    }
                    update_post_meta( $post_id, $this->meta_key_req_meta_key, $meta_key );
                }
            }
		}
	
		// Send success response
		wp_send_json_success();
	} // End ajax_bulk_edit()


    /**
     * Get the file array
     *
     * @param int $file_id
     * @return array|false
     */
    public function get_file( $file_id ) {
        if ( $file = get_post( $file_id ) ) {

            // Convert the file object to an array
            $file_array = (array) $file;

            // Get the custom meta
            $sanitized_meta = [];
            $sanitized_meta[ $this->meta_key_url ] = sanitize_text_field( get_post_meta( $file_id, $this->meta_key_url, true ) );
            $sanitized_meta[ $this->meta_key_description ] = sanitize_text_field( get_post_meta( $file_id, $this->meta_key_description, true ) );
            $sanitized_meta[ $this->meta_key_download_count ] = absint( get_post_meta( $file_id, $this->meta_key_download_count, true ) );
            $sanitized_meta[ $this->meta_key_req_roles ] = sanitize_text_field( get_post_meta( $file_id, $this->meta_key_req_roles, true ) );
            $sanitized_meta[ $this->meta_key_req_meta_key ] = sanitize_text_field( get_post_meta( $file_id, $this->meta_key_req_meta_key, true ) );
            $sanitized_meta[ $this->meta_key_last_downloaded ] = sanitize_text_field( get_post_meta( $file_id, $this->meta_key_last_downloaded, true ) );
            $sanitized_meta[ $this->meta_key_last_downloaded_by ] = absint( get_post_meta( $file_id, $this->meta_key_last_downloaded_by, true ) );
    
            // Merge the sanitized meta into the file array
            return array_merge( $file_array, $sanitized_meta );
        }
    
        return false;
    } // End get_file()


    /**
     * Get a list of files
     *
     * @return array
     */
    public function get_files( $file_ids = [], $resource_types = [], $target_audiences = [], $formats = [], $required_roles = [], $required_meta_keys = [], $order = 'ASC', $orderby = 'post_title', $per_page = 10, $offset = 0 ) {
        // Try to get cached results first
        $cache_key = 'search_results_' . md5( wp_json_encode( [
            'file_ids'           => $file_ids,
            'resource_types'     => $resource_types,
            'target_audiences'   => $target_audiences,
            'formats'            => $formats,
            'required_roles'     => $required_roles,
            'required_meta_keys' => $required_meta_keys,
            'order'              => $order,
            'orderby'            => $orderby,
            'per_page'           => $per_page,
            'offset'             => $offset
        ] ) );
    
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }
    
        // Get the post type
        $POSTTYPE = new PostType();
        $post_type = $POSTTYPE->post_type;
    
        // Get the file ids if we are not including them already
        if ( empty( $file_ids ) ) {
            // Get the top files ordered by custom meta key ($this->meta_key_count)
            $args = [
                'post_type'      => $post_type,
                'posts_per_page' => $per_page,
                'post_status'    => 'publish',
                'orderby'        => $orderby,
                'order'          => $order,
                'offset'         => $offset,
                'fields'         => 'ids'
            ];
    
            // ADD TAXONOMIES
            $TAXONOMIES = new Taxonomies();
            $tax_queries = [];
    
            if ( !empty( $resource_types ) ) {
                $tax_queries[] = [
                    'taxonomy' => $TAXONOMIES->taxonomy_resource_types,
                    'field'    => 'slug',
                    'terms'    => $resource_types,
                ];
            }
    
            if ( !empty( $target_audiences ) ) {
                $tax_queries[] = [
                    'taxonomy' => $TAXONOMIES->taxonomy_target_audiences,
                    'field'    => 'slug',
                    'terms'    => $target_audiences,
                ];
            }
    
            if ( !empty( $formats ) ) {
                $tax_queries[] = [
                    'taxonomy' => $TAXONOMIES->taxonomy_formats,
                    'field'    => 'slug',
                    'terms'    => $formats,
                ];
            }
    
            if ( !empty( $tax_queries ) ) {
                $args[ 'tax_query' ] = [  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                    'relation' => 'AND',
                    $tax_queries
                ];
            }

            // ADD REQUIRED ROLES
            if ( !empty( $required_roles ) ) {
                foreach ( $required_roles as $role ) {
                    $args[ 'meta_query' ][] = [
                        'key'     => $this->meta_key_req_roles,
                        'value'   => $role,
                        'compare' => 'LIKE'
                    ];
                }
            }

            // ADD REQUIRED META KEYS
            if ( !empty( $required_meta_keys ) ) {
                foreach ( $required_meta_keys as $meta_key ) {
                    $args[ 'meta_query' ][] = [
                        'key'     => $this->meta_key_req_meta_key,
                        'value'   => $meta_key,
                        'compare' => 'LIKE',
                    ];
                }
            }
    
            // Get the files using WP_Query
            $query = new \WP_Query( $args );

            // Get the total number of files
            $total_files = $query->found_posts;

            // Get the file ids
            $file_ids = $query->posts;
        
        } else {

            // Get the total number of files
            $total_files = count( $file_ids );

            // Slice the file ids
            $file_ids = array_slice( $file_ids, $offset, $per_page );            
        }
    
        // Prepare the results
        $results = [];

        if ( !empty( $file_ids ) ) {
            foreach ( $file_ids as $file_id ) {            
                $results[ $file_id ] = $this->get_file( $file_id );
            }
        }

        // The data to cache and send back
        $data = [
            'files' => $results,
            'count' => $total_files
        ];
    
        // Cache the results for future use
        wp_cache_set( $cache_key, $data, $this->cache_group, HOUR_IN_SECONDS );
    
        // Return both results and total count
        return $data;
    } // End get_files()    


    /**
     * Check if the user meets requirements
     *
     * @param int $file_id
     * @param int $user_id
     * @return boolean
     */
    public function user_meets_requirements( $file_id, $user_id = null ) {
        // Get the stored requirements
        $required_roles = sanitize_text_field( get_post_meta( $file_id, $this->meta_key_req_roles, true ) );
        $required_meta_key = sanitize_text_field( get_post_meta( $file_id, $this->meta_key_req_meta_key, true ) );

        // If no roles and no meta key are required, allow access to everyone (even logged-out users)
        if ( empty( $required_roles ) && empty( $required_meta_key ) ) {
            return true;
        }

        // Ensure we have a user ID (must be logged in if requirements exist)
        if ( !$user_id ) {
            $user_id = get_current_user_id();
        }
        if ( !$user_id ) {
            return false;
        }
    
        // Convert roles string into an array
        $required_roles = $required_roles ? array_map( 'trim', explode( ',', $required_roles ) ) : [];
    
        // Check user roles
        $user = get_userdata( $user_id );
        $user_roles = !empty( $user ) ? $user->roles : [];
    
        $meets_role_requirement = empty( $required_roles ) || array_intersect( $required_roles, $user_roles );
    
        // Check meta key requirement
        $meets_meta_requirement = true;
        if ( !empty( $required_meta_key ) ) {
            $meets_meta_requirement = filter_var( get_user_meta( $user_id, $required_meta_key, true ), FILTER_VALIDATE_BOOLEAN );
        }

        // Combine the role and meta key checks
        $meets_requirements = $meets_role_requirement && $meets_meta_requirement;
        $meets_requirements = filter_var( apply_filters( 'erifl_user_meets_requirements', $meets_requirements, $file_id, $user_id, $required_roles, $required_meta_key ), FILTER_VALIDATE_BOOLEAN );

        // Sanitize final result
        return (bool) $meets_requirements;
    } // End user_meets_requirements()


    /**
     * Remove Files from Shared Breadcrumb Category Pages
     */
    public function remove_breadcrumbs( $crumbs, $args ) {
        if ( is_category() ) {
            foreach ( $crumbs as $i => $crumb ) {
                if ( strtolower( $crumb[ 'label' ] ) === 'files' && is_category() ) {

                    // Remove the breadcrumb
                    unset( $crumbs[ $i ] );
                }
            }
        }
        return $crumbs;
    } // End remove_breadcrumbs()


    /**
     * Enqueue scripts
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        $current_screen = get_current_screen();
        
        // Admin List Table
        if ( $current_screen->id == 'edit-' . $this->post_type ) {

            // Jquery
            $admin_list_js_handle = ERIFL_TEXTDOMAIN.'-admin-list-script';
            wp_enqueue_script( 'jquery' );
            wp_register_script( $admin_list_js_handle, ERIFL_JS_PATH . 'admin-list.js', [ 'jquery' ], ERIFL_SCRIPT_VERSION, true );
            wp_localize_script( $admin_list_js_handle, 'erifl_quick_bulk_edit', [
                'nonce' => wp_create_nonce( $this->bulk_edit_nonce )
            ] );
            wp_enqueue_script( $admin_list_js_handle );

            // CSS
            $admin_list_css_handle = ERIFL_TEXTDOMAIN . '-admin-list-style';
            wp_register_style( $admin_list_css_handle, ERIFL_CSS_PATH . 'admin-list.css', [], ERIFL_SCRIPT_VERSION );
            wp_enqueue_style( $admin_list_css_handle );

        // Edit Screen
        } elseif ( $current_screen->base == 'post' && $current_screen->id == $this->post_type ) {

            // Jquery
            $edit_js_handle = ERIFL_TEXTDOMAIN.'-edit-script';
            wp_enqueue_script( 'jquery' );
            wp_register_script( $edit_js_handle, ERIFL_JS_PATH . 'edit.js', [ 'jquery' ], ERIFL_SCRIPT_VERSION ); // phpcs:ignore 
            wp_enqueue_script( $edit_js_handle );

            // CSS
            $edit_css_handle = ERIFL_TEXTDOMAIN . '-edit-style';
            wp_register_style( $edit_css_handle, ERIFL_CSS_PATH . 'edit.css', [], ERIFL_SCRIPT_VERSION );
            wp_enqueue_style( $edit_css_handle );
        }
    } // End enqueue_scripts()

}