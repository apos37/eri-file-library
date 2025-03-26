<?php
/**
 * Taxonomies
 */


/**
 * Define Namespaces
 */
namespace Apos37\EriFileLibrary;
use Apos37\EriFileLibrary\PostType;
use Apos37\EriFileLibrary\Settings;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Initiate the class
 */
add_action( 'init', function() {
	(new Taxonomies())->init();
} );


/**
 * The class
 */
class Taxonomies {

	/**
	 * Post Type
	 *
	 * @var string
	 */
	public $post_type;


	/**
	 * Taxonomies
	 *
	 * @var string|array
	 */
	public $taxonomy_formats;
	public $taxonomy_resource_types;
	public $taxonomy_target_audiences;


	/**
	 * Nonce
	 *
	 * @var string
	 */
	private $nonce = 'erifl_taxonomy_nonce';


    /**
     * Constructor
     */
    public function __construct() {

		// Post type
		$this->post_type = (new PostType())->post_type;

		// Taxonomies
		$this->taxonomy_formats = sanitize_key( apply_filters( 'erifl_taxonomy_formats', 'erifl-formats' ) );
		$this->taxonomy_resource_types = sanitize_key( apply_filters( 'erifl_taxonomy_resource_types', 'erifl-resource-types' ) );
		$this->taxonomy_target_audiences = sanitize_key( apply_filters( 'erifl_taxonomy_target_audiences', 'erifl-target-audiences' ) );

    } // End __construct()


	/**
	 * Load on it
	 *
	 * @return void
	 */
	public function init() {

		// Register
		$this->register_formats();
		if ( $this->taxonomy_resource_types ) $this->register_resource_types();
		if ( $this->taxonomy_target_audiences ) $this->register_target_audiences();

		// Resource Types
		if ( $this->taxonomy_resource_types ) {
			add_action( $this->taxonomy_resource_types.'_add_form_fields', [ $this, 'custom_resource_type_fields_new' ] );
			add_action( $this->taxonomy_resource_types.'_edit_form_fields', [ $this, 'custom_resource_type_fields_edit' ], 10, 2 );
			add_action( 'created_'.$this->taxonomy_resource_types, [ $this, 'save_resource_type_fields' ] );
			add_action( 'edited_'.$this->taxonomy_resource_types, [ $this, 'save_resource_type_fields' ] );
			add_filter( 'manage_edit-'.$this->taxonomy_resource_types.'_columns', [ $this, 'resource_type_column' ] );
			add_filter( 'manage_'.$this->taxonomy_resource_types.'_custom_column', [ $this, 'resource_type_column_content' ], 10, 3 );
		}
		
	} // End init()


	/**
	 * Register Format Taxonomy
	 *
	 * @return void
	 */
	public function register_formats() {
		// Create the labels
		$labels = [
			'name'                  => _x( 'Formats', 'taxonomy general name', 'eri-file-library' ),
			'singular_name'         => _x( 'Format', 'taxonomy singular name', 'eri-file-library' ),
			'search_items'          => __( 'Search Formats', 'eri-file-library' ),
			'all_items'             => __( 'All Formats', 'eri-file-library' ),
			'parent_item'           => __( 'Parent Format', 'eri-file-library' ),
			'parent_item_colon'     => __( 'Parent Format:', 'eri-file-library' ),
			'edit_item'             => __( 'Edit Format', 'eri-file-library' ), 
			'update_item'           => __( 'Update Format', 'eri-file-library' ),
			'add_new_item'          => __( 'Add New Format', 'eri-file-library' ),
			'new_item_name'         => __( 'New Format Name', 'eri-file-library' ),
			'menu_name'             => __( 'Formats', 'eri-file-library' ),
		]; 	

		// Register it as a new taxonomy
		register_taxonomy( $this->taxonomy_formats, $this->post_type, [
			'hierarchical'          => true,
			'labels'                => $labels,
			'show_ui'               => true,
			'public'                => true,
			'show_in_rest'          => false,
			'show_admin_column'     => true,
			'show_in_quick_edit'    => true,
			'meta_box_cb'           => null,
			'query_var'             => true,
			'rewrite'               => [ 'slug' => $this->taxonomy_formats, 'with_front' => false ],
		] );
	} // End register_formats()


	/**
	 * Register Resource Type Taxonomy
	 *
	 * @return void
	 */
	public function register_resource_types() {
		// Create the labels
		$labels = [
			'name'                  => _x( 'Resource Types', 'taxonomy general name', 'eri-file-library' ),
			'singular_name'         => _x( 'Resource Type', 'taxonomy singular name', 'eri-file-library' ),
			'search_items'          => __( 'Search Resource Types', 'eri-file-library' ),
			'all_items'             => __( 'All Resource Types', 'eri-file-library' ),
			'parent_item'           => __( 'Parent Resource Type', 'eri-file-library' ),
			'parent_item_colon'     => __( 'Parent Resource Type:', 'eri-file-library' ),
			'edit_item'             => __( 'Edit Resource Type', 'eri-file-library' ), 
			'update_item'           => __( 'Update Resource Type', 'eri-file-library' ),
			'add_new_item'          => __( 'Add New Resource Type', 'eri-file-library' ),
			'new_item_name'         => __( 'New Resource Type Name', 'eri-file-library' ),
			'menu_name'             => __( 'Resource Types', 'eri-file-library' ),
		]; 	

		// Register it as a new taxonomy
		register_taxonomy( $this->taxonomy_resource_types, $this->post_type, [
			'hierarchical'          => true,
			'labels'                => $labels,
			'show_ui'               => true,
			'public'                => true,
			'show_in_rest'          => false,
			'show_admin_column'     => true,
			'show_in_quick_edit'    => true,
			'meta_box_cb'           => null,
			'query_var'             => true,
			'rewrite'               => [ 'slug' => $this->taxonomy_resource_types, 'with_front' => false ],
		] );
	} // End register_resource_types()


	/**
	 * Register Target Audience Taxonomy
	 *
	 * @return void
	 */
	public function register_target_audiences() {
		// Create the labels
		$labels = [
			'name'                  => _x( 'Target Audiences', 'taxonomy general name', 'eri-file-library' ),
			'singular_name'         => _x( 'Target Audience', 'taxonomy singular name', 'eri-file-library' ),
			'search_items'          => __( 'Search Target Audiences', 'eri-file-library' ),
			'all_items'             => __( 'All Target Audiences', 'eri-file-library' ),
			'parent_item'           => __( 'Parent Target Audience', 'eri-file-library' ),
			'parent_item_colon'     => __( 'Parent Target Audience:', 'eri-file-library' ),
			'edit_item'             => __( 'Edit Target Audience', 'eri-file-library' ), 
			'update_item'           => __( 'Update Target Audience', 'eri-file-library' ),
			'add_new_item'          => __( 'Add New Target Audience', 'eri-file-library' ),
			'new_item_name'         => __( 'New Target Audience Name', 'eri-file-library' ),
			'menu_name'             => __( 'Target Audiences', 'eri-file-library' ),
		]; 	

		// Register it as a new taxonomy
		register_taxonomy( $this->taxonomy_target_audiences, $this->post_type, [
			'hierarchical'          => true,
			'labels'                => $labels,
			'show_ui'               => true,
			'public'                => true,
			'show_in_rest'          => false,
			'show_admin_column'     => true,
			'show_in_quick_edit'    => true,
			'meta_box_cb'           => null,
			'query_var'             => true,
			'rewrite'               => [ 'slug' => $this->taxonomy_target_audiences, 'with_front' => false ],
		] );
	} // End register_target_audiences()


	/**
     * Add featured image to resource type ADD NEW screen
     *
     * @param object $taxonomy
     * @return void
     */
    public function custom_resource_type_fields_new( $taxonomy ) {
		wp_nonce_field( $this->nonce, $this->nonce );

        echo '<div class="form-field">
			<label for="featured-image">' . esc_html__( 'Featured Image URL', 'eri-file-library' ) . '</label>
			<input type="text" name="featured-image" id="featured-image" pattern="https://.*">
			<p class="description">' . esc_html__( 'Must include', 'eri-file-library' ) . ' https://</p>
		</div>';
    } // End custom_resource_type_fields_new()


    /**
     * Add featured image to resource type EDIT Screen
     *
     * @param object $term
     * @param object $taxonomy
     * @return void
     */
    public function custom_resource_type_fields_edit( $term, $taxonomy ) {
		wp_nonce_field( $this->nonce, $this->nonce );

        // Get the value
        $value = filter_var( get_term_meta( $term->term_id, 'featured-image', true ), FILTER_SANITIZE_URL );

        // Add the field
        echo '<tr class="form-field">
            <th><label for="featured-image">' . esc_html__( 'Featured Image URL', 'eri-file-library' ) . '</label></th>
            <td><input type="url" name="featured-image" id="featured-image" value="' . esc_url( $value ) . '" pattern="https://.*">
            <p class="description">' . esc_html__( 'Must include', 'eri-file-library' ) . ' https://</p></td>
        </tr>';
    } // End custom_resource_type_fields_edit()


    /**
     * Save the featured image for resource type
     *
     * @param int $term_id
     * @return void
     */
    public function save_resource_type_fields( $term_id ) {
		if ( isset( $_REQUEST[ $this->nonce ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ $this->nonce ] ) ), $this->nonce ) &&
			isset( $_POST[ 'featured-image' ] ) ) {
			update_term_meta( $term_id, 'featured-image', sanitize_url( wp_unslash( $_POST[ 'featured-image' ] ) ) );
		}
    } // End save_resource_type_fields()


    /**
     * Let's add a column to the resource type taxonomy
     *
     * @param array $columns
     * @return array
     */
    public function resource_type_column( $columns ) {
        // Add the column
        $columns[ 'featured-image' ] = __( 'Featured Image', 'eri-file-library' );
        
        // Return the columns
        return $columns;
    } // End resource_type_column()


    /**
     * Column content for resource type taxonomy
     *
     * @param string $content
     * @param string $column_name
     * @param int $term_id
     * @return string
     */
    public function resource_type_column_content( $content, $column_name, $term_id ) {
        // Add the content
        if ( $column_name == 'featured-image' ) {
			$value = get_term_meta( $term_id, 'featured-image', true );
			$value = filter_var( $value, FILTER_SANITIZE_URL );
	
			if ( $value && $value != '' ) {
				$attachment_id = attachment_url_to_postid( $value );
				if ( $attachment_id ) {
					$content .= wp_get_attachment_image( $attachment_id, [ 100, 100 ] );
				}
			}
		}

        // Return the content
        return $content;
    } // End resource_type_column_content()


	/**
	 * Get the target audience terms
	 *
	 * @return array
	 */
	public function get_target_audiences() {
		return get_terms( [
            'taxonomy'   => $this->taxonomy_target_audiences,
            'hide_empty' => false,
        ] );
	} // End get_target_audiences()


	/**
	 * Get any additional taxonomies from settings
	 *
	 * @return array
	 */
	public function get_addt_taxonomies() {
		$addt_taxes = sanitize_text_field( get_option( (new Settings())->option_add_taxonomies ) );
		$taxonomies = array_filter( array_map( 'trim', explode( ',', $addt_taxes ) ) );
		return $taxonomies;
	} // End get_addt_taxonomies()

}