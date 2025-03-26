<?php
/**
 * About
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
 * Instantiate the class
 */
add_action( 'init', function() {
    (new About())->init();
} );


/**
 * The class
 */
class About {

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
            ERIFL_NAME . ' — ' . __( 'About', 'eri-file-library' ),
            __( 'About', 'eri-file-library' ),
            'manage_options',
            ERIFL__TEXTDOMAIN . '_about',
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
        if ( $current_screen->id != ERIFL_ABOUT_SCREEN_ID ) {
            return;
        }
        ?>

        <div class="wrap">
            <h1><?php echo esc_attr( get_admin_page_title() ); ?></h1>

            <br><br>
            <h2><?php echo esc_html__( 'Plugin Support', 'eri-file-library' ); ?></h2>
            <br><img class="admin_helpbox_title" src="<?php echo esc_url( ERIFL_IMG_PATH ); ?>discord.png" width="auto" height="100">
            <p><?php echo esc_html__( 'If you need assistance with this plugin or have suggestions for improving it, please join the Discord server below.', 'eri-file-library' ); ?></p>
            <?php // translators: 1: Text for the button (default: Join Our Support Server)
            echo '<a class="button button-primary" href="'.esc_url( ERIFL_DISCORD_SUPPORT_URL ).'" target="_blank">'.esc_html__( 'Join Our Support Server', 'eri-file-library' ).' »</a><br>'; ?>

            <br>
            <p><?php echo esc_html__( 'Or if you would rather get support on WordPress.org, you can do so here:', 'eri-file-library' ); ?></p>
            <?php // translators: 1: Text for the button (default: WordPress.org Plugin Support Page)
            echo '<a class="button button-primary" href="https://wordpress.org/support/plugin/' . esc_attr( ERIFL_TEXTDOMAIN ) . '/" target="_blank">'.esc_html__( 'WordPress.org Plugin Support Page', 'eri-file-library' ).' »</a><br>'; ?>

            <br><br><br>
            <h2><?php echo esc_html__( 'Like This Plugin?', 'eri-file-library' ); ?></h2>
            <p><?php echo esc_html__( 'Please rate and review this plugin if you find it helpful. If you would give it fewer than 5 stars, please let me know how I can improve it.', 'eri-file-library' ); ?></p>
            <?php // translators: 1: Text for the button (default: Rate and Review on WordPress.org)
            echo '<a class="button button-primary" href="https://wordpress.org/support/plugin/' . esc_attr( ERIFL_TEXTDOMAIN ) . '/reviews/" target="_blank">'.esc_html( __( 'Rate and Review on WordPress.org', 'eri-file-library' ) ).' »</a><br>'; ?>

            <br><br><br>
            <h2><?php echo esc_html__( 'Try My Other Plugins', 'eri-file-library' ); ?></h2>
            <div class="wp-list-table widefat plugin-install">
                <div id="the-list">
                    <?php $this->plugin_card( 'broken-link-notifier' ); ?>
                    <?php $this->plugin_card( 'admin-help-docs' ); ?>
                    <?php $this->plugin_card( 'dev-debug-tools' ); ?>
                    <?php if ( is_plugin_active( 'gravityforms/gravityforms.php' ) ) { ?>
                        <?php $this->plugin_card( 'gf-tools' ); ?>
                        <?php $this->plugin_card( 'gf-discord' ); ?>
                        <?php $this->plugin_card( 'gf-msteams' ); ?>
                        <?php $this->plugin_card( 'gravity-zwr' ); ?>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php
    } // End page()


    /**
     * Add a WP Plugin Info Card
     *
     * @param string $slug
     * @return string
     */
    public function plugin_card( $slug ) {
        // Set the args
        $args = [ 
            'slug'                => $slug, 
            'fields'              => [
                'last_updated'    => true,
                'tested'          => true,
                'active_installs' => true
            ]
        ];
        
        // Fetch the plugin info from the wp repository
        $response = wp_remote_post(
            'http://api.wordpress.org/plugins/info/1.0/',
            [
                'body' => [
                    'action' => 'plugin_information',
                    'request' => serialize( (object)$args )
                ]
            ]
        );
        
        // If there is no error, continue
        if ( !is_wp_error( $response ) ) {

             // Unserialize
            $returned_object = unserialize( wp_remote_retrieve_body( $response ) );   
            if ( $returned_object ) {

                // Last Updated
                $last_updated = $returned_object->last_updated;
                $last_updated = $this->time_elapsed_string( $last_updated );

                // Compatibility
                $compatibility = $returned_object->tested;

                // Add incompatibility class
                global $wp_version;
                if ( $compatibility == $wp_version ) {
                    $is_compatible = '<span class="compatibility-compatible">' . __( '<strong>Compatible</strong> with your version of WordPress', 'eri-file-library' ) . '</span>';
                } else {
                    $is_compatible = '<span class="compatibility-untested">' . __( 'Untested with your version of WordPress', 'eri-file-library' ) . '</span>';
                }

                // Get all the installed plugins
                $plugins = get_plugins();

                // Check if this plugin is installed
                $is_installed = false;
                foreach ( $plugins as $key => $plugin ) {
                    if ( $plugin[ 'TextDomain' ] == $slug ) {
                        $is_installed = $key;
                    }
                }

                // Check if it is also active
                $is_active = false;
                if ( $is_installed && is_plugin_active( $is_installed ) ) {
                    $is_active = true;
                }

                // Check if the plugin is already active
                if ( $is_active ) {
                    $install_link = 'role="link" aria-disabled="true"';
                    $php_notice = '';
                    $install_text = __( 'Active', 'eri-file-library' );

                // Check if the plugin is installed but not active
                } elseif ( $is_installed ) {
                    $install_link = 'href="'.admin_url( 'plugins.php' ).'"';
                    $php_notice = '';
                    $install_text = __( 'Go to Activate', 'eri-file-library' );

                // Check for php requirement
                } elseif ( phpversion() < $returned_object->requires_php ) {
                    $install_link = 'role="link" aria-disabled="true"';

                    // translators: 1: Required PHP version, 2: Current PHP version
                    $php_notice = sprintf( __( 'Requires PHP Version %1$s — You are currently on Version %2$s', 'eri-file-library' ),
                        $returned_object->requires_php,
                        phpversion()
                    );

                    $php_notice = '<div class="php-incompatible"><em><strong>' . $php_notice . '</strong></em></div>';
                    $install_text = __( 'Incompatible', 'eri-file-library' );

                // If we're good to go, add the link
                } else {

                    // Get the admin url for the plugin install page
                    if ( is_multisite() ) {
                        $admin_url = network_admin_url( 'plugin-install.php' );
                    } else {
                        $admin_url = admin_url( 'plugin-install.php' );
                    }

                    // Vars
                    $install_link = 'href="'.$admin_url.'?s='.esc_attr( $returned_object->name ).'&tab=search&type=term"';
                    $php_notice = '';
                    $install_text = __( 'Get Now', 'eri-file-library' );
                }
                
                // Short Description
                $pos = strpos( $returned_object->sections[ 'description' ], '.');
                $desc = substr( $returned_object->sections[ 'description' ], 0, $pos + 1 );

                // Rating
                $rating = $this->get_five_point_rating( 
                    $returned_object->ratings[1], 
                    $returned_object->ratings[2], 
                    $returned_object->ratings[3], 
                    $returned_object->ratings[4], 
                    $returned_object->ratings[5] 
                );

                // Link guts
                $link_guts = sprintf(
                    'href="%s" target="_blank" aria-label="%s" data-title="%s"',
                    'https://wordpress.org/plugins/' . $slug . '/',
                    sprintf(
                        // translators: 1: Plugin name, 2: Plugin version
                        __( 'More information about %1$s %2$s', 'eri-file-library' ),
                        $returned_object->name,
                        $returned_object->version
                    ),
                    sprintf(
                        // translators: 1: Plugin name, 2: Plugin version
                        __( '%1$s %2$s', 'eri-file-library' ),
                        $returned_object->name,
                        $returned_object->version
                    )
                );
                ?>

                <div class="plugin-card plugin-card-<?php echo esc_attr( $slug ); ?>">
                    <div class="plugin-card-top">
                        <div class="name column-name">
                            <h3>
                                <a <?php echo wp_kses_post( $link_guts ); ?>>
                                    <?php echo esc_html( $returned_object->name ); ?>
                                    <img src="<?php echo esc_url( ERIFL_IMG_PATH ) . esc_attr( $slug ); ?>.png" class="plugin-icon" alt="<?php echo esc_attr( sprintf(
                                        // translators: %s: Plugin name
                                        __( '%s Thumbnail', 'eri-file-library' ), 
                                        $returned_object->name 
                                    ) ); ?>">
                                </a>
                            </h3>
                        </div>
                        <div class="action-links">
                            <ul class="plugin-action-buttons">
                                <li>
                                    <a class="install-now button" data-slug="<?php echo esc_attr( $slug ); ?>" <?php echo wp_kses_post( $install_link ); ?> 
                                    aria-label="<?php echo esc_attr( $install_text ); ?>" 
                                    data-name="<?php echo esc_attr( sprintf( 
                                            // translators: 1: Plugin name, 2: Plugin version
                                            __( '%1$s %2$s', 'eri-file-library' ), 
                                            $returned_object->name, 
                                            $returned_object->version 
                                        ) ); ?>">
                                        <?php echo esc_html( $install_text ); ?>
                                    </a>
                                </li>
                                <li>
                                    <a <?php echo wp_kses_post( $link_guts ); ?>><?php echo esc_html__( 'More Details', 'eri-file-library' ); ?></a>
                                </li>
                            </ul>
                        </div>
                        <div class="desc column-description">
                            <p><?php echo wp_kses_post( $desc ); ?></p>
                            <p class="authors">
                                <cite><?php 
                                // translators: %s: Plugin author name
                                printf( esc_html__( 'By %s', 'eri-file-library' ), wp_kses_post( $returned_object->author ) ); ?></cite>
                            </p>
                        </div>
                    </div>
                    <div class="plugin-card-bottom">
                        <div class="vers column-rating">
                            <div class="star-rating">
                                <span class="screen-reader-text">
                                    <?php
                                    // translators: 1: Star rating, 2: Number of ratings
                                    printf( esc_attr__( '%1$s star rating based on %2$s ratings', 'eri-file-library' ), esc_attr( $rating ), esc_attr( $returned_object->num_ratings ) );
                                    ?>
                                </span>
                                <?php echo wp_kses_post( $this->convert_to_stars( $rating ) ); ?>
                            </div>					
                            <span class="num-ratings" aria-hidden="true">(<?php echo esc_attr( $returned_object->num_ratings ); ?>)</span>
                        </div>
                        <div class="column-updated">
                            <strong><?php esc_html_e( 'Last Updated:', 'eri-file-library' ); ?></strong> <?php echo esc_html( $last_updated ); ?>
                        </div>
                        <div class="column-downloaded" data-downloads="<?php echo esc_attr( number_format( isset( $returned_object->downloaded ) ? $returned_object->downloaded : 0 ) ); ?>">
                            <?php
                            // translators: %s: Number of active installs
                            printf( esc_html__( '%s+ Active Installs', 'eri-file-library' ), esc_attr( number_format( $returned_object->active_installs ) ) );
                            ?>
                        </div>
                        <div class="column-compatibility">
                            <?php echo wp_kses_post( $is_compatible ); ?>				
                        </div>
                    </div>
                    <?php echo wp_kses_post( $php_notice ); ?>
                </div>

                <?php
            }
        }
    } // End plugin_card()


    /**
     * Convert time to elapsed string
     *
     * @param [type] $datetime
     * @return string
     */
    public function time_elapsed_string( $datetime ) {
        $timestamp = strtotime( $datetime );
        if ( !$timestamp ) {
            return 'just now';
        }

        $time_diff = human_time_diff( $timestamp );

        return $time_diff . ' ago';
    } // End time_elapsed_string()


    /**
     * Convert 5-point rating to plugin card stars
     *
     * @param int|float $r
     * @return string
     */
    public function convert_to_stars( $r ) {
        $f = '<div class="star star-full" aria-hidden="true"></div>';
        $h = '<div class="star star-half" aria-hidden="true"></div>';
        $e = '<div class="star star-empty" aria-hidden="true"></div>';
        
        $stars = $e.$e.$e.$e.$e;
        if ( $r > 4.74 ) {
            $stars = $f.$f.$f.$f.$f;
        } elseif ( $r > 4.24 && $r < 4.75 ) {
            $stars = $f.$f.$f.$f.$h;
        } elseif ( $r > 3.74 && $r < 4.25 ) {
            $stars = $f.$f.$f.$f.$e;
        } elseif ( $r > 3.24 && $r < 3.75 ) {
            $stars = $f.$f.$f.$h.$e;
        } elseif ( $r > 2.74 && $r < 3.25 ) {
            $stars = $f.$f.$f.$e.$e;
        } elseif ( $r > 2.24 && $r < 2.75 ) {
            $stars = $f.$f.$h.$e.$e;
        } elseif ( $r > 1.74 && $r < 2.25 ) {
            $stars = $f.$f.$e.$e.$e;
        } elseif ( $r > 1.24 && $r < 1.75 ) {
            $stars = $f.$h.$e.$e.$e;
        } elseif ( $r > 0.74 && $r < 1.25 ) {
            $stars = $f.$e.$e.$e.$e;
        } elseif ( $r > 0.24 && $r < 0.75 ) {
            $stars = $h.$e.$e.$e.$e;
        } else {
            $stars = $stars;
        }

        return '<div class="ws_stars">'.$stars.'</div>';
    } // End convert_to_stars()


    /**
     * Get 5-point rating from 5 values
     *
     * @param int|float $r1
     * @param int|float $r2
     * @param int|float $r3
     * @param int|float $r4
     * @param int|float $r5
     * @return float
     */
    public function get_five_point_rating ( $r1, $r2, $r3, $r4, $r5 ) {
        // Calculate them on a 5-point rating system
        $r5b = round( $r5 * 5, 0 );
        $r4b = round( $r4 * 4, 0 );
        $r3b = round( $r3 * 3, 0 );
        $r2b = round( $r2 * 2, 0 );
        $r1b = $r1;
        
        $total = round( $r1 + $r2 + $r3 + $r4 + $r5, 0 );
        if ( $total == 0 ) {
            $r = 0;
        } else {
            $r = round( ( $r1b + $r2b + $r3b + $r4b + $r5b ) / $total, 2 );
        }

        return $r;
    } // End get_five_point_rating()


    /**
     * Enqueue scripts
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        if ( ( $screen == ERIFL_ABOUT_SCREEN_ID ) ) {

            // CSS
            $css_handle = ERIFL_TEXTDOMAIN . '-about-style';
            wp_register_style( $css_handle, ERIFL_CSS_PATH . 'about.css', [], ERIFL_SCRIPT_VERSION );
            wp_enqueue_style( $css_handle );
        }
    } // End enqueue_scripts()
    
}