<?php
/**
 * Settings
 */


/**
 * Define Namespaces
 */
namespace Apos37\EriFileLibrary;
use Apos37\EriFileLibrary\PostType;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Initiate the class
 */
add_action( 'init', function() {
	(new Settings())->init();
} );


/**
 * The class
 */
class Settings {

	/**
	 * Post Type
	 *
	 * @var string
	 */
	public $post_type;


    /**
     * Site options
     *
     * @var string
     */
    public $option_add_taxonomies = 'erifl_add_taxonomies';
    public $option_admin_menu_label = 'erifl_admin_menu_label';
    public $option_no_access_msg = 'erifl_no_access_msg';
    public $option_example = 'erifl_example';
    public $option_pre_title = 'erifl_pre_title';
    public $option_post_title = 'erifl_post_title';
    public $option_admin_param = 'erifl_admin_param';
    public $option_btn_hide_format = 'erifl_btn_hide_format';
    public $option_icon_type = 'erifl_icon_type';
    public $option_folder = 'erifl_folder';
    public $option_include_urls = 'erifl_include_urls';
    public $option_tracking = 'erifl_tracking';
    public $option_delete_table = 'erifl_delete_table';


    /**
     * Constructor
     */
    public function __construct() {

		// Post type
		$this->post_type = (new PostType())->post_type;
        
    } // End __construct()


    /**
	 * Load on it
	 *
	 * @return void
	 */
	public function init() {

        // Settings page
        add_action( 'admin_menu', [ $this, 'settings_page_submenu' ] );

        // Settings page fields
        add_action( 'admin_init', [  $this, 'settings_fields' ] );

        // Enqueue scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End init()


    /**
     * Get the admin menu label
     *
     * @return string
     */
    public function admin_menu_label() {
        return sanitize_text_field( get_option( $this->option_admin_menu_label, __( 'Files', 'eri-file-library' ) ) );
    } // End admin_menu_label()


    /**
     * Get the shortcode tag prefix
     *
     * @return string
     */
    public function shortcode_tag() {
        $prefix = sanitize_key( apply_filters( 'erifl_shortcode_prefix', 'erifl_' ) );
        return $prefix . 'file';
    } // End shortcode_tag()


    /**
     * Are we tracking users and dates
     *
     * @return boolean
     */
    public function is_tracking() {
        return filter_var( get_option( $this->option_tracking ), FILTER_VALIDATE_BOOLEAN );
    } // End is_tracking()


	/**
     * Settings page
     *
     * @return void
     */
    public function settings_page_submenu() {
        add_submenu_page(
            'edit.php?post_type='.$this->post_type,
            __( 'File Library — Settings', 'eri-file-library' ),
            __( 'Settings', 'eri-file-library' ),
            'manage_options',
            ERIFL__TEXTDOMAIN . '_settings',
            [ $this, 'settings_page' ],
            null
        );
    } // End settings_page_submenu()

    
    /**
     * Settings page
     *
     * @return void
     */
    public function settings_page() {
        global $current_screen;
        if ( $current_screen->id != ERIFL_SETTINGS_SCREEN_ID ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'settings' );
                    do_settings_sections( 'settings' );
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    } // End settings_page()


    /**
     * Settings fields
     *
     * @return void
     */
    public function settings_fields() {
        // Slug
        $slug = 'settings';

        /**
         * Sections
         */
        $sections = [
            [ 'general', __( 'General', 'eri-file-library' ), '' ],
            [ 'format', __( 'Formatting', 'eri-file-library' ), '' ],
            [ 'structure', __( 'Structure', 'eri-file-library' ), '' ],
            [ 'tracking', __( 'Tracking', 'eri-file-library' ), '' ],
        ];

        // Iter the sections
        foreach ( $sections as $section ) {
            add_settings_section(
                $section[0],
                $section[1],
                $section[2],
                $slug
            );
        }

        /**
         * Fields
         */
        // Defaults for example
        $pre_title = sanitize_text_field( get_option( $this->option_pre_title, __( 'Download ', 'eri-file-library' ) ) );
        $post_title = sanitize_text_field( get_option( $this->option_post_title, '!' ) );
        $btn_hide_format = filter_var( get_option( $this->option_btn_hide_format ), FILTER_VALIDATE_BOOLEAN ) ? 'none' : 'inline-block' ;
        $admin_param = sanitize_key( get_option( $this->option_admin_param, 'link' ) );

        // Shortcode tag
        $shortcode_tag = $this->shortcode_tag();

        // Instantiate
        $POSTTYPE = new PostType();

        // Field data
        $fields = [
            [ 
                'key'       => $this->option_admin_menu_label, 
                'title'     => __( 'Admin Menu Label', 'eri-file-library' ), 
                'type'      => 'text', 
                'sanitize'  => 'sanitize_text_field', 
                'section'   => 'general', 
                'comments'  => '<br><em>' . __( 'You can change the admin menu label on the left side of the screen.', 'eri-file-library' ) . '</em>',
                'default'   => $this->admin_menu_label()
            ],
            [ 
                'key'       => $this->option_no_access_msg, 
                'title'     => __( 'No Access Message', 'eri-file-library' ), 
                'type'      => 'text', 
                'sanitize'  => 'wp_kses_post', 
                'section'   => 'general', 
                'comments'  => '<br><em>' . __( 'Requirements can be set up on files to require specific roles or a meta key to download them. If no requirements are set, everyone (including logged-out users) can download them. If requirements are set and the user does not meet the requirements, this message will display instead. HTML is allowed.', 'eri-file-library' ) . '</em>',
                'default'   => '<em>' . __( 'You do not have permission to access this file.', 'eri-file-library' ) . '</em>'
            ],
            [ 
                'key'       => $this->option_add_taxonomies, 
                'title'     => __( 'Additional Taxonomies', 'eri-file-library' ), 
                'type'      => 'text', 
                'sanitize'  => 'sanitize_text_field', 
                'section'   => 'general', 
                'comments'  => '<br><em>' . __( 'Optionally add existing taxonomies here by including their slugs in lowercase separated by commas (e.g. category, post_tag).', 'eri-file-library' ) . '</em>'
            ],
            [ 
                'key'       => $this->option_pre_title, 
                'title'     => __( 'Add Text Before Titles', 'eri-file-library' ), 
                'type'      => 'text', 
                'sanitize'  => 'sanitize_text_field', 
                'section'   => 'format', 
                'comments'  => '<br><em>' . __( 'Include text you want to add before the titles on links and buttons.', 'eri-file-library' ) . '</em>',
                'default'   => __( 'Download ', 'eri-file-library' ),
            ],
            [ 
                'key'       => $this->option_post_title, 
                'title'     => __( 'Add Text After Titles', 'eri-file-library' ), 
                'type'      => 'text', 
                'sanitize'  => 'sanitize_text_field', 
                'section'   => 'format', 
                'comments'  => '<br><em>' . __( 'Include text you want to add after the titles on links and buttons.', 'eri-file-library' ) . '</em>',
                'default'   => '!'
            ],
            [ 
                'key'       => $this->option_btn_hide_format, 
                'title'     => __( 'Remove File Format from Links & Buttons', 'eri-file-library' ), 
                'type'      => 'checkbox', 
                'sanitize'  => [ $this, 'sanitize_checkbox' ], 
                'section'   => 'format',
            ],
            [
                'key'       => $this->option_example,
                'title'     => __( 'Example Text', 'eri-file-library' ),
                'type'      => 'desc',
                'sanitize'  => '', 
                'section'   => 'format', 
                'comments'  => '<div id="example" style="font-decoration: underline"><span id="example-pre">' . $pre_title . '</span> <span id="example-title">' . __( 'Example File', 'eri-file-library' ) . '</span><span id="example-post">' . $post_title . '</span> <span id="example-format" class="' . $btn_hide_format . '">(pdf)</span></div>',
            ],
            [ 
                'key'       => $this->option_admin_param, 
                'title'     => __( 'Admin Page Shortcode Type', 'eri-file-library' ), 
                'type'      => 'select', 
                'sanitize'  => 'sanitize_text_field', 
                'section'   => 'format', 
                'comments'  => '<br><em>' . __( 'Change the shortcode "type" parameter on the admin list page for easy copy-and-paste.<br>Currently showing:', 'eri-file-library' ) . '</em> <code>[' . $shortcode_tag . ' id="###" type="<span id="shortcode-type">' . $admin_param . '</span>"]</code>',
                'options'   => [
                    'link'   => __( 'Link', 'eri-file-library' ),
                    'button' => __( 'Button', 'eri-file-library' ),
                    'icon'   => __( 'Icon', 'eri-file-library' ),
                    'url'    => __( 'URL', 'eri-file-library' ),
                    'full'   => __( 'Full', 'eri-file-library' ),
                ],
                'default'   => 'link'
            ],
            [ 
                'key'       => $this->option_icon_type, 
                'title'     => __( 'Icon Type', 'eri-file-library' ), 
                'type'      => 'select', 
                'sanitize'  => 'sanitize_text_field',
                'section'   => 'format',
                'options'   => [
                    'logo-full' => __( 'Logo Full', 'eri-file-library' ),
                    'logo-file' => __( 'Logo File Only', 'eri-file-library' ),
                    'fa'        => 'Font Awesome',
                    'uni'       => __( 'Unicode', 'eri-file-library' )
                ],
                'default'   => 'svg'
            ],
            [ 
                'key'       => $this->option_folder, 
                'title'     => __( 'Folder Name', 'eri-file-library' ), 
                'type'      => 'text', 
                'sanitize'  => [ $this, 'sanitize_and_rename_folder' ], 
                'section'   => 'structure', 
                // translators: 1: Default folder name (post type), 2: Example file path, 3: Shortcode tag.
                'comments'  => '<br><em>' . sprintf( __( 'If left blank, this will default to "%1$s".<br>Your ERI File Library files can be found here: </em> %2$s <em><br>⚠ Changing the folder name will also change the URL path of all the ERI File Library files. If you are displaying the files on the front end using the</em> %3$s <em>shortcode, then the links will be changed automatically.', 'eri-file-library' ),
                    $this->post_type,
                    '<code><strong>' . $POSTTYPE->folder_path( false, '<span id="erifl-example-folder-name">' . $POSTTYPE->folder_name() . '</span>' ) . '</strong></code>',
                    '<code>[' . $shortcode_tag . ']</code>'
                ) . '</em>',
                'default'   => $this->post_type,
                'revert'    => true
            ],
            [ 
                'key'       => $this->option_include_urls, 
                'title'     => __( 'Include URLS in Links', 'eri-file-library' ),
                'type'      => 'checkbox',
                'sanitize'  => [ $this, 'sanitize_checkbox' ],
                'section'   => 'structure',
                'comments'  => '<br><em>' . __( 'By default, links use <code>#</code> as the URL to conceal the file path. Users must click the links to download files, which ensures downloads are tracked. Enabling this setting will display the full file URL in the link, allowing direct file access. However, if users right-click and save the file, the download will not be tracked. This option is intended for those who use the plugin primarily for file organization rather than tracking.', 'eri-file-library' ) . '</em>'
            ],
            [ 
                'key'       => $this->option_tracking, 
                'title'     => __( 'Enable User Tracking', 'eri-file-library' ),
                'type'      => 'checkbox',
                'sanitize'  => [ $this, 'sanitize_checkbox' ],
                'section'   => 'tracking',
                'comments'  => '<em>' . __( 'Logs each download for you to see which users downloaded them with timestamps. Disabling this after already logging some data will not result in the data being lost.', 'eri-file-library' ) . '</em>'
            ],
            [ 
                'key'       => $this->option_delete_table, 
                'title'     => __( 'Delete User Tracking Database Table on Uninstall', 'eri-file-library' ),
                'type'      => 'checkbox',
                'sanitize'  => [ $this, 'sanitize_checkbox' ],
                'section'   => 'tracking',
                // translators: %s is the name of the user tracking database table.
                'comments'  => '<em>' . sprintf( __( 'By enabling tracking above, a table is created called <code>%s</code>. This setting will delete the entire table with all of the tracking data when you uninstall the plugin. It is recommended to keep this disabled until you are absolutely sure you do not want the user tracking data anymore. There might be a time where you need to temporarily uninstall the plugin to test issues on the site, so you certainly don\'t want to delete anything in the process.', 'eri-file-library' ), 
                    (new Database())->table_name
                ) . '</em>'
            ],
        ];       

        if ( is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
            $fields = array_map( function ( $field ) {
                if ( $field[ 'key' ] === $this->option_icon_type ) {
                    $field[ 'options' ][ 'cs' ] = __( 'Cornerstone', 'eri-file-library' );
                }
                return $field;
            }, $fields );
        }
        
        // Iter the fields
        foreach ( $fields as $field ) {
            $option_name = $field[ 'key' ];
            $callback = 'settings_field_'.$field[ 'type' ];
            $args = [
                'id'    => $option_name,
                'class' => $option_name,
                'name'  => $option_name,
            ];

            // Add comments
            if ( isset( $field[ 'comments' ] ) ) {
                $args[ 'comments' ] = $field[ 'comments' ];
            }
            
            // Add select options
            if ( $field[ 'type' ] == 'select' && isset( $field[ 'options' ] ) ) {
                $args[ 'options' ] = $field[ 'options' ];
            }

            // Add default
            if ( isset( $field[ 'default' ] ) ) {
                $args[ 'default' ] = $field[ 'default' ];
            }

            // Add revert
            if ( isset( $field[ 'revert' ] ) ) {
                $args[ 'revert' ] = $field[ 'revert' ];
            }

            // Validate sanitize callback
            $sanitize_callback = null;
            if ( isset( $field[ 'sanitize' ] ) && is_callable( $field[ 'sanitize' ] ) ) {
                $sanitize_callback = $field[ 'sanitize' ];
            } elseif ( isset( $field[ 'sanitize' ] ) && is_string( $field[ 'sanitize' ] ) && function_exists( $field[ 'sanitize' ] ) ) {
                $sanitize_callback = $field[ 'sanitize' ];
            }

            // Register the setting
            register_setting( $slug, $option_name, $sanitize_callback ); // phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic
            add_settings_field( $option_name, $field[ 'title' ], [ $this, $callback ], $slug, $field[ 'section' ], $args );
        }
    } // End settings_fields()


    /**
     * Custom callback function to print text field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_desc( $args ) {
        echo wp_kses_post( $args[ 'comments' ] );
    } // settings_field_text()
    
    
    /**
     * Custom callback function to print text field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_text( $args ) {
        $width = isset( $args[ 'width' ] ) ? $args[ 'width' ] : '30rem';
        $comments = isset( $args[ 'comments' ] ) ? $args[ 'comments' ] : '';
        $default = isset( $args[ 'default' ] )  ? $args[ 'default' ] : '';
        $value = get_option( $args[ 'name' ], $default );

        if ( isset( $args[ 'revert' ] ) && $args[ 'revert' ] == true && trim( $value ) == '' ) {
            $value = $default;
        }

        printf(
            '<input type="text" id="%s" name="%s" value="%s" style="width: %s;" />%s',
            esc_attr( $args[ 'id' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_html( $value ),
            esc_attr( $width ),
            wp_kses_post( $comments )
        );
    } // settings_field_text()


    /**
     * Custom callback function to print select field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_select( $args ) {
        $default = isset( $args[ 'default' ] ) ? $args[ 'default' ] : '';
        $value = get_option( $args[ 'name' ], $default );
        if ( isset( $args[ 'revert' ] ) && $args[ 'revert' ] == true && trim( $value ) == '' ) {
            $value = $default;
        }
        ?>
            <select id="<?php echo esc_attr( $args[ 'name' ] ); ?>" name="<?php echo esc_attr( $args[ 'name' ] ); ?>">
                <?php 
                if ( isset( $args[ 'options'] ) ) {
                    foreach ( $args[ 'options'] as $key => $option ) {
                        ?>
                        <option value="<?php echo esc_attr( $key ); ?>"<?php echo selected( $key, $value ); ?>><?php echo esc_attr( $option ); ?></option>
                        <?php 
                    }
                }
                ?>
            </select> <?php echo isset( $args[ 'comments' ] ) ? wp_kses_post( $args[ 'comments' ] ) : ''; ?>
        <?php
    } // settings_field_select()


    /**
     * Custom callback function to print checkbox field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_checkbox( $args ) {
        $value = get_option( $args[ 'name' ] );
        $comments = isset( $args[ 'comments' ] ) ? $args[ 'comments' ] : '';
        ?>
            <label>
                <input type="checkbox" id="<?php echo esc_attr( $args[ 'name' ] ); ?>" name="<?php echo esc_attr( $args[ 'name' ] ); ?>" <?php checked( $value, 1 ) ?> /> <?php echo isset( $args[ 'label' ] ) ? esc_html( $args[ 'label' ] ) : ''; ?> <?php echo wp_kses_post( $comments ); ?>
            </label>
        <?php
    } // End settings_field_checkbox()


    /**
     * Custom callback function to print textarea field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_textarea( $args ) {
        $value = get_option( $args[ 'name' ] );
        ?>
            <textarea id="<?php echo esc_attr( $args[ 'name' ] ); ?>" name="<?php echo esc_attr( $args[ 'name' ] ); ?>"><?php echo wp_kses_post( $value ); ?></textarea> <?php echo wp_kses_post( $args[ 'comments' ] ); ?>
        <?php
    } // settings_field_textarea()


    /**
     * Custom callback function to print number field
     *
     * @param [type] $args
     * @return void
     */
    public function settings_field_number( $args ) {
        printf(
            '<input type="number" id="%s" name="%s" value="%d" />',
            esc_attr( $args[ 'label_for' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_html( get_option( $args[ 'name' ] ) )
        );
    } // End settings_field_number()

    
    /**
     * Sanitize checkbox
     *
     * @param int $value
     * @return void
     */
    public function sanitize_checkbox( $value ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    } // End sanitize_checkbox()


    /**
     * Sanitize and rename files folder
     *
     * @param [type] $input
     * @return mixed
     */
    public function sanitize_and_rename_folder( $input ) {
        global $wp_filesystem;

        // Sanitize the text field
        $input = sanitize_text_field( $input );

        // Get the old folder name
        $folder = get_option( $this->option_folder );
        $old_folder_name = $folder ? sanitize_key( $folder ) : (new PostType())->post_type;

        // If it has changed in the settings, rename the folder
        if ( $input != $old_folder_name ) {
            $uploads_dir = wp_get_upload_dir();
            $old_folder_path = trailingslashit( $uploads_dir[ 'basedir' ] ) . $old_folder_name;
            $new_folder_path = trailingslashit( $uploads_dir[ 'basedir' ] ) . $input;

            // Initialize WP Filesystem API
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();

            // Move the folder
            if ( $wp_filesystem->exists( $old_folder_path ) ) {
                $wp_filesystem->move( $old_folder_path, $new_folder_path );
            }
        }
        
        return $input;
    } // End sanitize_and_rename_folder()


    /**
     * The icon to use
     *
     * @return string
     */
    public function icon( $icon_type = null, $file = null, $ext = null, $title = null ) {
        if ( is_null( $icon_type ) ) {
            $icon_type = sanitize_key( get_option( $this->option_icon_type, 'logo-full' ) );
        }

        // Initialize the title attribute
        $title_attr = !is_null( $title ) ? ' title="' . esc_attr( $title ) . '" aria-label="' . esc_attr( $title ) . '"' : '';

        // Custom image
        $types = [ 'logo-full', 'logo-file', 'uni', 'fa', 'cs' ];
        if ( !in_array( $icon_type, $types ) && pathinfo( $icon_type, PATHINFO_EXTENSION ) ) {
            $icon = '<img class="erifl-icon custom" src="' . sanitize_url( $icon_type ) . '"' . $title_attr . '>'; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage

        // Our options
        } else {
            switch ( $icon_type ) {
                case 'logo-file':
                    $icon = '<img class="erifl-icon logo-file" src="' . ERIFL_IMG_PATH . 'icon_file.png"' . $title_attr . '>'; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                    break;
                case 'uni':
                    $icon = '<span class="erifl-icon"' . $title_attr . '>&#x1F4E5;</span>';
                    break;
                case 'fa':
                    $icon = '<i class="erifl-icon fas fa-download"' . $title_attr . '></i>';
                    break;
                case 'cs':
                    $icon = '<i aria-hidden="true" class="erifl-icon x-icon" data-x-icon-s="&#xf019;"' . $title_attr . '></i>';
                    break;
                case 'logo-full':
                default:
                   $icon = '<img class="erifl-icon logo-full" src="' . ERIFL_IMG_PATH . 'icon.png"' . $title_attr . '>'; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
            }
        }
    
        // Return with filter
        return wp_kses_post( apply_filters( 'erifl_download_icon', $icon, $icon_type, $file, $ext ) );
    } // End icon() 


    /**
     * Enqueue scripts
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        if ( ( $screen == ERIFL_SETTINGS_SCREEN_ID ) ) {

            // Jquery
            $js_handle = ERIFL_TEXTDOMAIN.'-settings-script';
            wp_enqueue_script( 'jquery' );
            wp_register_script( $js_handle, ERIFL_JS_PATH . 'settings.js', [ 'jquery' ], ERIFL_SCRIPT_VERSION, true );
            wp_localize_script( $js_handle, 'erifl_settings', [
                'example_alert' => __( "This doesn't actually do anything. It's just an example of how a link would look with the formatting settings you choose.", 'eri-file-library' )
            ] );
            wp_enqueue_script( $js_handle );

            // CSS
            $css_handle = ERIFL_TEXTDOMAIN . '-settings-style';
            wp_register_style( $css_handle, ERIFL_CSS_PATH . 'settings.css', [], ERIFL_SCRIPT_VERSION );
            wp_enqueue_style( $css_handle );
        }
    } // End enqueue_scripts()

}