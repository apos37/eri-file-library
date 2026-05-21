<?php
/**
 * Migrate Page
 */

namespace Apos37\EriFileLibrary;

if ( ! defined( 'ABSPATH' ) ) exit;

class Migrate {

    protected $wpdb;
    protected $chunk_size = 500;


    /**
     * Constructor.
     */
    public function __construct() {
        if ( get_current_user_id() !== 3 ) {
            return;
        }

        global $wpdb;
        $this->wpdb = $wpdb;

        // Add to menu
        add_action( 'admin_menu', [ $this, 'submenu' ] );

        // Enqueue scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // AJAX handlers
        add_action( 'wp_ajax_erifl_migrate_downloads', [ $this, 'ajax_migrate_downloads' ] );
        add_action( 'wp_ajax_erifl_migrate_posts', [ $this, 'ajax_migrate_posts' ] );

    } // End __construct()


    /**
     * Submenu
     */
    public function submenu() {
        add_submenu_page(
            'edit.php?post_type=' . ( new PostType() )->post_type,
            ERIFL_NAME . ' — ' . __( 'Migrate', 'eri-file-library' ),
            __( 'Migrate', 'eri-file-library' ),
            'manage_options',
            ERIFL__TEXTDOMAIN . '_migrate',
            [ $this, 'page' ]
        );
    } // End submenu()


    /**
     * Page
     */
    public function page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <h2><?php _e( 'Migrate Downloads', 'eri-file-library' ); ?></h2>
            <p><?php _e( 'Enter the batch size (use 0 for all). Default: 1', 'eri-file-library' ); ?></p>
            <input type="number" id="erifl-downloads-batch" value="1" min="0" step="1" />
            <button class="button button-primary" id="erifl-run-downloads"><?php _e( 'Run', 'eri-file-library' ); ?></button>
            <div id="erifl-downloads-progress" style="margin-top:10px;"></div>

            <hr>

            <h2><?php _e( 'Migrate Posts', 'eri-file-library' ); ?></h2>
            <p><?php _e( 'Enter the batch size (use 0 for all). Default: 1', 'eri-file-library' ); ?></p>
            <input type="number" id="erifl-posts-batch" value="1" min="0" step="1" />
            <button class="button button-primary" id="erifl-run-posts"><?php _e( 'Run', 'eri-file-library' ); ?></button>
            <div id="erifl-posts-progress" style="margin-top:10px;"></div>
        </div>
        <?php
    } // End page()


    /**
     * Log a message to the error log if WP_DEBUG is enabled.
     *
     * @param string $message The message to log.
     */
    protected function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[MigrateFiles] ' . $message );
        }
    } // End log()


    /**
     * Migrate download records from eri-download-dates taxonomy to eri_file_library table.
     *
     * @param int $per_chunk Number of records to process per chunk.
     * @param int $offset    Offset for pagination.
     * @return int Number of records migrated.
     */
    public function migrate_downloads( $per_chunk = 1, $offset = 0 ) {
        if ( ! taxonomy_exists( 'eri-download-dates' ) ) {
            $this->log( 'Taxonomy eri-download-dates is not registered. Aborting migration.' );
            return [
                'terms_processed' => 0,
                'rows_inserted'   => 0,
            ];
        }

        $terms = get_terms( [
            'taxonomy'   => 'eri-download-dates',
            'hide_empty' => false,
            'number'     => $per_chunk,
            'offset'     => $offset,
        ] );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [
                'terms_processed' => 0,
                'rows_inserted'   => 0,
            ];
        }

        $insert_rows = [];

        foreach ( $terms as $term ) {
            if ( is_array( $term ) ) {
                $term = (object) $term;
            }

            $term_id = intval( $term->term_id ?? 0 );
            if ( ! $term_id ) {
                continue;
            }

            $meta    = get_term_meta( $term_id );
            $file_id = intval( $meta['file_id'][0] ?? 0 );
            $user_id = intval( $meta['user_id'][0] ?? 0 );

            if ( ! $file_id || ! $user_id ) {
                continue;
            }

            $time = date( 'Y-m-d H:i:s', intval( $term->name ) );

            $insert_rows[] = $this->wpdb->prepare(
                "( %d, %d, %s, %s )",
                $file_id,
                $user_id,
                '',
                $time
            );
        }

        $rows_inserted = 0;

        if ( ! empty( $insert_rows ) ) {
            $query = "INSERT IGNORE INTO {$this->wpdb->prefix}eri_file_library ( file_id, user_id, user_ip, time ) VALUES "
                . implode( ',', $insert_rows );

            $result = $this->wpdb->query( $query );

            if ( $result !== false ) {
                $rows_inserted = intval( $result );
            } else {
                $this->log( 'Failed batch insert at offset ' . $offset );
            }
        }

        return [
            'terms_processed' => count( $terms ),
            'rows_inserted'   => $rows_inserted,
        ];
    } // End migrate_downloads()


    /**
     * Migrate posts from 'eri-files' to 'erifl-files' post type.
     *
     * @param int $limit Number of posts to migrate. 0 for all.
     * @return int Number of posts migrated.
     */
    public function migrate_posts( $limit = 0 ) {
        if ( ! taxonomy_exists( 'erifl-formats' ) ) {
            $this->log( 'erifl-formats taxonomy not registered at runtime' );
            return 0;
        }

        $args = [
            'post_type'      => 'eri-files',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ];

        $post_ids = get_posts( $args );

        if ( empty( $post_ids ) ) {
            return 0;
        }

        $map_meta = [
            '_post_supercount'         => 'in_depth_count',
            '_post_count'              => 'download_count',
            '_post_desc'               => 'description',
            '_post_url'                => 'url',
            '_post_last_downloaded'    => 'last_downloaded',
            '_post_last_downloaded_by' => 'last_downloaded_by',
        ];

        $migrated = 0;

        foreach ( $post_ids as $post_id ) {

            // Change post type, keep ID
            wp_update_post( [
                'ID'        => $post_id,
                'post_type' => 'erifl-files',
            ] );

            // Migrate meta keys
            foreach ( $map_meta as $old_key => $new_key ) {
                $values = get_post_meta( $post_id, $old_key, false );
                if ( empty( $values ) ) {
                    continue;
                }

                delete_post_meta( $post_id, $old_key );

                foreach ( $values as $value ) {
                    add_post_meta( $post_id, $new_key, maybe_unserialize( $value ) );
                }
            }

            // Migrate formats → erifl-formats (by slug)
            $old_terms = wp_get_object_terms( $post_id, 'formats', [ 'fields' => 'slugs' ] );
            if ( ! is_wp_error( $old_terms ) && ! empty( $old_terms ) ) {
                $new_term_ids = [];

                foreach ( $old_terms as $slug ) {
                    $term = get_term_by( 'slug', $slug, 'erifl-formats' );
                    if ( $term ) {
                        $new_term_ids[] = intval( $term->term_id );
                    }
                }

                if ( ! empty( $new_term_ids ) ) {
                    wp_set_object_terms( $post_id, $new_term_ids, 'erifl-formats' );
                }
            }

            // Remove unwanted tax terms
            wp_delete_object_term_relationships( $post_id, [
                'eri-downloads',
                'eri-download-dates',
            ] );

            $migrated++;
        }

        return $migrated;
    } // End migrate_posts()


    /**
     * Enqueue Scripts
     * 
     * @param string $screen Current screen ID
     */
    public function enqueue_scripts( $screen ) {
        if ( $screen === ERIFL_MIGRATE_SCREEN_ID ) {

            wp_enqueue_script(
                ERIFL_TEXTDOMAIN . '-migrate-js',
                ERIFL_JS_PATH . 'migrate.js',
                [ 'jquery' ],
                ERIFL_SCRIPT_VERSION,
                true
            );

            wp_localize_script( ERIFL_TEXTDOMAIN . '-migrate-js', 'ERIFL_Migrate', [
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'erifl_migrate_nonce' ),
                'chunk_size' => $this->chunk_size,
            ] );

            // wp_register_style(
            //     ERIFL_TEXTDOMAIN . '-migrate-style',
            //     ERIFL_CSS_PATH . 'migrate.css',
            //     [],
            //     ERIFL_SCRIPT_VERSION
            // );
            // wp_enqueue_style( ERIFL_TEXTDOMAIN . '-migrate-style' );
        }
    } // End enqueue_scripts()


    /**
     * AJAX: Migrate Downloads
     */
    public function ajax_migrate_downloads() {
        check_ajax_referer( 'erifl_migrate_nonce', 'nonce' );

        $batch  = isset( $_POST['batch'] ) ? intval( $_POST['batch'] ) : 0;
        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;

        $per_chunk = $batch > 0 ? $batch : $this->chunk_size;

        $result = $this->migrate_downloads( $per_chunk, $offset );

        $terms_processed = intval( $result['terms_processed'] );
        $rows_inserted   = intval( $result['rows_inserted'] );

        wp_send_json_success( [
            'terms_processed' => $terms_processed,
            'rows_inserted'   => $rows_inserted,
            'next_offset'     => $offset + $terms_processed,
        ] );
    } // End ajax_migrate_downloads()


    /**
     * AJAX: Copy Posts
     */
    public function ajax_migrate_posts() {
        check_ajax_referer( 'erifl_migrate_nonce', 'nonce' );

        $batch = isset( $_POST['batch'] ) ? intval( $_POST['batch'] ) : 0;

        if ( $batch <= 0 ) {
            wp_send_json_error( 'Invalid batch size' );
        }

        $migrated = $this->migrate_posts( $batch );

        wp_send_json_success( [
            'migrated' => $migrated,
        ] );
    } // End ajax_migrate_posts()

}


add_filter( 'wp_loaded', function() {
    new Migrate();
} );