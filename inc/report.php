<?php
/**
 * Report
 */


/**
 * Define Namespaces
 */
namespace Apos37\EriFileLibrary;
use Apos37\EriFileLibrary\Settings;
use Apos37\EriFileLibrary\PostType;
use Apos37\EriFileLibrary\Taxonomies;
use Apos37\EriFileLibrary\Database;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
add_action( 'init', function() {
    if ( (new Settings())->is_tracking() ) {
        (new Report())->init();
    }
} );


/**
 * The class
 */
class Report {

    /**
     * Nonce
     *
     * @var string
     */
    private $nonce = 'erifl-export-nonce';
    

	/**
	 * Load on init
	 *
	 * @return void
	 */
	public function init() {
        
		// Submenu
        add_action( 'admin_menu', [ $this, 'submenu' ] );

        // Export download counts
        add_action( 'admin_init', [ $this, 'export_counts_to_csv' ] );

		// Enqueue scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // AJAX for filtering reports
        add_action( 'wp_ajax_erifl_filter_reports', [ $this, 'ajax_filter_reports' ] );

    } // End init()


	/**
     * Submenu
     *
     * @return void
     */
    public function submenu() {
        add_submenu_page(
            'edit.php?post_type='.(new PostType())->post_type,
            __( 'File Library — Report', 'eri-file-library' ),
            __( 'Report', 'eri-file-library' ),
            'manage_options',
            ERIFL__TEXTDOMAIN . '_report',
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
        if ( $current_screen->id != ERIFL_REPORT_SCREEN_ID ) {
            return;
        }

        // Are we tracking logged-out users?
        $tracking_logged_out_users = get_option( ( new Settings() )->option_logged_out_tracking ) ? true : false;

        // Do we have saved options
        $saved_options = get_option( 'erifl_report_options', [] );
        if ( empty( $saved_options ) ) {
            $saved_options = [
                'start_date'  => null,
                'end_date'    => null,
                'counts_type' => $tracking_logged_out_users ? 'all' : 'logged_in',
                'last_ran'    => null,
                'last_ran_by' => null,
            ];
        }
        
        // Determine if date filters should be disabled based on counts type and logged-out tracking setting
        $disable_date_filters = ! $tracking_logged_out_users && $saved_options[ 'counts_type' ] === 'all';

        // Failsafe, don't allow filtering by date if we're showing all counts but not tracking logged-out users, since the date filters won't work for logged-out users and would be confusing
        if ( $disable_date_filters && ( ! empty( $saved_options[ 'start_date' ] ) || ! empty( $saved_options[ 'end_date' ] ) ) ) {
            $saved_options[ 'start_date' ] = null;
            $saved_options[ 'end_date' ] = null;
            update_option( 'erifl_report_options', $saved_options );
        }

        // Fetch data for reports
        $DB = new Database();
        $TAXONOMIES = new Taxonomies();

        $counts = [
            'Top Downloads'                   => $DB->get_top_downloads( 10, $saved_options[ 'start_date' ], $saved_options[ 'end_date' ], $saved_options[ 'counts_type' ] ),
            'Top Formats Downloaded'          => $DB->get_top_downloads_by_taxonomy( $TAXONOMIES->taxonomy_formats , $saved_options[ 'start_date' ], $saved_options[ 'end_date' ], $saved_options[ 'counts_type' ] ),
            'Top Users Downloading Files'     => $DB->get_top_users( $saved_options[ 'start_date' ], $saved_options[ 'end_date' ], $saved_options[ 'counts_type' ] ),
        ];

        if ( ! get_option( (new Settings())->option_disable_resource_types ) ) {
            $counts[ 'Top Resource Types Downloaded' ] = $DB->get_top_downloads_by_taxonomy( $TAXONOMIES->taxonomy_resource_types , $saved_options[ 'start_date' ], $saved_options[ 'end_date' ], $saved_options[ 'counts_type' ] );
        }
        if ( ! get_option( (new Settings())->option_disable_target_audiences ) ) {
            $counts[ 'Top Target Audiences Downloaded' ] = $DB->get_top_downloads_by_taxonomy( $TAXONOMIES->taxonomy_target_audiences , $saved_options[ 'start_date' ], $saved_options[ 'end_date' ], $saved_options[ 'counts_type' ] );
        }

        $total_downloads = $DB->get_total_downloads( $saved_options[ 'start_date' ], $saved_options[ 'end_date' ], $saved_options[ 'counts_type' ] );
        ?>

        <div class="wrap">
            <h1><?php echo esc_attr( get_admin_page_title() ); ?></h1>

            <?php if ( ! empty( $saved_options[ 'last_ran' ] ) && ! empty( $saved_options[ 'last_ran_by' ] ) ) : ?>
                <p id="erifl-last-ran-info">
                    <?php
                    /* translators: %s: date/time of last run, %s: display name of user who ran it */
                    printf( esc_html__( 'Last ran on %1$s by %2$s', 'eri-file-library' ),
                        '<span class="erifl-last-ran-datetime">' . esc_html( wp_date( 'F j, Y \a\t g:i a T', $saved_options[ 'last_ran' ] ) ) . '</span>',
                        '<span class="erifl-last-ran-user">' . esc_html( get_userdata( $saved_options[ 'last_ran_by' ] )->display_name ) . '</span>'
                    );
                    ?>
                </p>
            <?php endif; ?>


            <div id="erifl-report-filters">
                <div class="counts-filter">
                    <select id="erifl-counts-type">
                        <option value="logged_in" <?php selected( $saved_options[ 'counts_type' ], 'logged_in' ); ?>>
                            <?php esc_html_e( 'Logged-In Users', 'eri-file-library' ); ?>
                        </option>
                        <?php if ( $tracking_logged_out_users ) : ?>
                            <option value="logged_out" <?php selected( $saved_options[ 'counts_type' ], 'logged_out' ); ?>>
                                <?php esc_html_e( 'Logged-Out Users', 'eri-file-library' ); ?>
                            </option>
                            <option value="all" <?php selected( $saved_options[ 'counts_type' ], 'all' ); ?>>
                                <?php esc_html_e( 'All Counts', 'eri-file-library' ); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <button type="button" class="button" id="erifl-counts-filter-apply"><?php esc_html_e( 'Filter', 'eri-file-library' ); ?></button>
                </div>

                <div class="date-filter">
                    <input
                        type="date"
                        id="erifl-start-date"
                        value="<?php echo esc_attr( $saved_options[ 'start_date' ] ?? '' ); ?>"
                        <?php disabled( $disable_date_filters ); ?>
                        aria-label="<?php esc_attr_e( 'Start date', 'eri-file-library' ); ?>"
                    >
                    <input
                        type="date"
                        id="erifl-end-date"
                        value="<?php echo esc_attr( $saved_options[ 'end_date' ] ?? '' ); ?>"
                        <?php disabled( $disable_date_filters ); ?>
                        aria-label="<?php esc_attr_e( 'End date', 'eri-file-library' ); ?>"
                    >
                    <button type="button" class="button" id="erifl-apply-date-filter" <?php disabled( $disable_date_filters ); ?>><?php esc_html_e( 'Filter', 'eri-file-library' ); ?></button>
                </div>

                <div class="range-filter">
                    <select id="erifl-quarter-range" <?php disabled( $disable_date_filters ); ?>>
                        <option value=""><?php esc_html_e( 'Select Date Range', 'eri-file-library' ); ?></option>

                        <?php foreach ( $this->get_quarter_ranges() as $range ) : 
                            $is_selected =
                                ! empty( $saved_options[ 'start_date' ] ) &&
                                ! empty( $saved_options[ 'end_date' ] ) &&
                                $saved_options[ 'start_date' ] === $range[ 'start' ] &&
                                $saved_options[ 'end_date' ] === $range[ 'end' ];
                        ?>
                            <option
                                value="<?php echo esc_attr( $range[ 'start' ] . '|' . $range[ 'end' ] ); ?>"
                                <?php selected( $is_selected, true ); ?>
                            >
                                <?php echo esc_html( $range[ 'label' ] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" id="erifl-apply-range-filter" <?php disabled( $disable_date_filters ); ?>>
                        <?php esc_html_e( 'Filter', 'eri-file-library' ); ?>
                    </button>
                </div>
                
                <button type="button" class="button" id="erifl-clear-filters"><?php esc_html_e( 'Clear Filters', 'eri-file-library' ); ?></button>
            </div>

            <div id="erifl-report-notice"><?php do_action( 'erifl_report_before_tables' ); ?></div>

            <form id="erifl-export-counts-btn" method="post" action="">
                <?php wp_nonce_field( $this->nonce ); ?>
                <input type="hidden" name="export" value="1">
                <input type="hidden" name="counts_type" id="erifl-export-counts-type" value="<?php echo esc_attr( $saved_options[ 'counts_type' ] ); ?>">
                <input type="hidden" name="start_date" id="erifl-export-start-date" value="<?php echo esc_attr( $saved_options[ 'start_date' ] ); ?>">
                <input type="hidden" name="end_date" id="erifl-export-end-date" value="<?php echo esc_attr( $saved_options[ 'end_date' ] ); ?>">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Export Counts', 'eri-file-library' ); ?></button>
            </form>

            <div id="erifl-report">
                <?php $this->render_tables( $counts, $total_downloads ); ?>
            </div>
        </div>
        <?php
    } // End page()


    /**
     * Get the last 5 quarter ranges
     *
     * @return array
     */
    private function get_quarter_ranges() {
        $ranges = [];
        $tz = wp_timezone();
        $now = new \DateTimeImmutable( 'now', $tz );

        for ( $i = 0; $i < 5; $i++ ) {
            $date = $now->modify( '-' . ( $i * 3 ) . ' months' );

            $year = (int) $date->format( 'Y' );
            $month = (int) $date->format( 'n' );

            $quarter = (int) ceil( $month / 3 );
            $start_month = ( ( $quarter - 1 ) * 3 ) + 1;

            $start = new \DateTimeImmutable(
                sprintf( '%d-%02d-01 00:00:00', $year, $start_month ),
                $tz
            );

            $end = $start->modify( '+3 months' )->modify( '-1 day' );

            $ranges[] = [
                'start' => $start->format( 'Y-m-d' ),
                'end'   => $end->format( 'Y-m-d' ),
                'label' => $start->format( 'n/j/Y' ) . ' – ' . $end->format( 'n/j/Y' ),
            ];
        }

        return $ranges;
    } // End get_quarter_ranges()


    /**
     * Export the counts
     *
     * @return void
     */
    public function export_counts_to_csv() {
        if ( isset( $_GET[ 'post_type' ] ) && sanitize_key( $_GET[ 'post_type' ] ) === ( new PostType() )->post_type && // phpcs:ignore
            isset( $_GET[ 'page' ] ) && sanitize_key( $_GET[ 'page' ] ) === ERIFL__TEXTDOMAIN . '_report' ) { // phpcs:ignore

            if ( isset( $_POST[ 'export' ] ) && intval( $_POST[ 'export' ] ) === 1 ) { // phpcs:ignore

                if ( ! isset( $_POST[ '_wpnonce' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ '_wpnonce' ] ) ), $this->nonce ) ) {
                    wp_die( esc_html__( 'Nonce verification failed!', 'eri-file-library' ) );
                }

                if ( ! current_user_can( 'administrator' ) ) {
                    wp_die( esc_html__( 'You do not have permission to export download counts.', 'eri-file-library' ) );
                }

                $tracking_logged_out_users = get_option( ( new Settings() )->option_logged_out_tracking ) ? true : false;
                $default_counts_type = $tracking_logged_out_users ? 'all' : 'logged_in';

                $counts_type = isset( $_POST[ 'counts_type' ] ) ? sanitize_key( wp_unslash( $_POST[ 'counts_type' ] ) ) : $default_counts_type;
                if ( ! in_array( $counts_type, [ 'logged_in', 'logged_out', 'all' ], true ) ) {
                    $counts_type = $default_counts_type;
                }

                $start_date = ! empty( $_POST[ 'start_date' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'start_date' ] ) ) : null;
                $end_date   = ! empty( $_POST[ 'end_date' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'end_date' ] ) ) : null;

                $use_posts_only = ( $counts_type === 'all' && empty( $start_date ) && empty( $end_date ) );

                $host = wp_parse_url( get_site_url(), PHP_URL_HOST );
                $domain = pathinfo( $host, PATHINFO_FILENAME );
                $filename = $domain . '_download_counts.csv';

                header( 'Content-Type: text/csv' );
                header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

                $output = fopen( 'php://output', 'w' );
                fputcsv( $output, [
                    __( 'File ID', 'eri-file-library' ),
                    __( 'File Name', 'eri-file-library' ),
                    __( 'File Path', 'eri-file-library' ),
                    __( 'Last Downloaded', 'eri-file-library' ),
                    __( 'Downloads', 'eri-file-library' )
                ] );

                $DB = new Database();
                global $wpdb;

                if ( $use_posts_only ) {

                    $sql = "
                        SELECT p.ID AS file_id, COALESCE( CAST( pm_count.meta_value AS UNSIGNED ), 0 ) AS count,
                            p.post_title, COALESCE( pm_url.meta_value, '' ) AS url, COALESCE( pm_last.meta_value, '' ) AS last_downloaded
                        FROM {$wpdb->posts} p
                        LEFT JOIN {$wpdb->postmeta} pm_count ON pm_count.post_id = p.ID AND pm_count.meta_key = 'download_count'
                        LEFT JOIN {$wpdb->postmeta} pm_url ON pm_url.post_id = p.ID AND pm_url.meta_key = 'url'
                        LEFT JOIN {$wpdb->postmeta} pm_last ON pm_last.post_id = p.ID AND pm_last.meta_key = 'last_downloaded'
                        WHERE p.post_type = %s
                        AND p.post_status = 'publish'
                        ORDER BY count DESC
                    ";

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $downloads = $wpdb->get_results( $wpdb->prepare( $sql, ( new PostType() )->post_type ) );

                    foreach ( $downloads as $download ) {
                        fputcsv( $output, [
                            $download->file_id,
                            $download->post_title,
                            $download->url,
                            $download->last_downloaded,
                            number_format( (float) $download->count )
                        ] );
                    }

                    fclose( $output ); // phpcs:ignore
                    exit();
                }

                $sorted_file_ids = $DB->get_sorted_file_ids();
                $limit = 10000;

                for ( $i = 0; $i < count( $sorted_file_ids ); $i += $limit ) {

                    $file_ids_chunk = array_slice( $sorted_file_ids, $i, $limit );
                    $placeholders = implode( ', ', array_map( 'intval', $file_ids_chunk ) );

                    $date_filter = '';
                    if ( $start_date && $end_date ) {
                        $date_filter = $wpdb->prepare( " AND d.time BETWEEN %s AND %s ", $start_date . ' 00:00:00', $end_date . ' 23:59:59' ); // phpcs:ignore
                    }

                    $user_filter = '';
                    if ( $counts_type === 'logged_in' ) {
                        $user_filter = ' AND d.user_id > 0 ';
                    } elseif ( $counts_type === 'logged_out' ) {
                        $user_filter = ' AND d.user_id = 0 ';
                    }

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $downloads = $wpdb->get_results( "
                        SELECT d.file_id, COUNT(*) AS count, p.post_title, pm_url.meta_value AS url, pm_last.meta_value AS last_downloaded
                        FROM {$DB->table_name} d
                        JOIN {$wpdb->posts} p ON d.file_id = p.ID
                        LEFT JOIN {$wpdb->postmeta} pm_url ON pm_url.post_id = p.ID AND pm_url.meta_key = 'url'
                        LEFT JOIN {$wpdb->postmeta} pm_last ON pm_last.post_id = p.ID AND pm_last.meta_key = 'last_downloaded'
                        WHERE d.file_id IN ( $placeholders )
                        $user_filter
                        $date_filter
                        GROUP BY d.file_id
                        ORDER BY count DESC
                    " );

                    foreach ( $downloads as $download ) {
                        fputcsv( $output, [
                            $download->file_id,
                            $download->post_title,
                            $download->url,
                            $download->last_downloaded,
                            number_format( (float) $download->count )
                        ] );
                    }

                    ob_flush();
                    flush();
                }

                fclose( $output ); // phpcs:ignore
                exit();
            }
        }
    } // End export_counts_to_csv()
    

	/**
     * Enqueue scripts
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== ERIFL_REPORT_SCREEN_ID ) {
            return;
        }

		// Register and enqueue your CSS
		wp_enqueue_style( 
            ERIFL_TEXTDOMAIN . '-report', 
            ERIFL_CSS_PATH . 'report.css', 
            [], 
            ERIFL_SCRIPT_VERSION 
        );

        // Register and enqueue your JS
        wp_enqueue_script(
            ERIFL_TEXTDOMAIN . '-report',
            ERIFL_JS_PATH . 'report.js',
            [ 'jquery' ],
            ERIFL_SCRIPT_VERSION,
            true
        );

        wp_localize_script( ERIFL_TEXTDOMAIN . '-report', 'eriflReport', [
            'ajaxUrl'                   => admin_url( 'admin-ajax.php' ),
            'nonce'                     => wp_create_nonce( 'erifl-report-filter' ),
            'tracking_logged_out_users' => get_option( ( new Settings() )->option_logged_out_tracking ) ? true : false,
        ] );
    } // End enqueue_scripts()


    /**
     * Render the report tables
     *
     * @param array $counts
     * @return void
     */
    private function render_tables( $counts, $total_downloads ) {
        ?>
        <div class="erifl-total-downloads">
            <strong><?php echo esc_html__( 'Total Downloads:', 'eri-file-library' ); ?></strong>
            <?php echo esc_html( number_format_i18n( $total_downloads ) ); ?>
        </div>
        <?php
        foreach ( $counts as $title => $data ) {
            ?>
            <div class="report-section">
                <h2><?php echo esc_html( $title ); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <?php if ( isset( $data[0]->term_id ) ) : ?>
                                <th class="id"><?php echo esc_html__( 'Term ID', 'eri-file-library' ); ?></th>
                                <th class="item"><?php echo esc_html__( 'Term Name', 'eri-file-library' ); ?></th>
                            <?php elseif ( isset( $data[0]->user_id ) ) : ?>
                                <th class="id"><?php echo esc_html__( 'User ID', 'eri-file-library' ); ?></th>
                                <th class="item"><?php echo esc_html__( 'Display Name', 'eri-file-library' ); ?></th>
                            <?php else : ?>
                                <th class="id"><?php echo esc_html__( 'File ID', 'eri-file-library' ); ?></th>
                                <th class="item"><?php echo esc_html__( 'Title', 'eri-file-library' ); ?></th>
                            <?php endif; ?>
                            <th class="count"><?php echo esc_html__( 'Number of Downloads', 'eri-file-library' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $data as $row ) : ?>
                            <tr>
                                <?php if ( isset( $row->term_id ) ) : ?>
                                    <td><?php echo esc_html( $row->term_id ); ?></td>
                                    <td><?php echo esc_html( $row->term_name ); ?></td>
                                <?php elseif ( isset( $row->user_id ) ) : ?>
                                    <td><?php echo esc_html( $row->user_id ); ?></td>
                                    <td><a href="<?php echo esc_url( get_edit_profile_url( $row->user_id ) ); ?>"><?php echo esc_html( $row->display_name ); ?></a></td>
                                <?php else : ?>
                                    <td><?php echo esc_html( $row->file_id ); ?></td>
                                    <td><a href="<?php echo esc_url( get_edit_post_link( $row->file_id ) ); ?>"><?php echo esc_html( get_the_title( $row->file_id ) ); ?></a></td>
                                <?php endif; ?>
                                <td><?php echo esc_html( $row->downloads ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    } // End render_tables()


    /**
     * AJAX handler for filtering reports
     *
     * @return void
     */
    public function ajax_filter_reports() {
        check_ajax_referer( 'erifl-report-filter', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }

        $start_date  = isset( $_POST[ 'start_date' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'start_date' ] ) ) : null;
        $end_date    = isset( $_POST[ 'end_date' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'end_date' ] ) ) : null;
        $counts_type = isset( $_POST[ 'counts_type' ] ) ? sanitize_key( wp_unslash( $_POST[ 'counts_type' ] ) ) : 'all';

        $current_user_id = get_current_user_id();
        $last_ran        = current_time( 'timestamp', true );

        $saved_options = [
            'start_date'  => $start_date,
            'end_date'    => $end_date,
            'counts_type' => $counts_type,
            'last_ran'    => $last_ran,
            'last_ran_by' => $current_user_id,
        ];
        update_option( 'erifl_report_options', $saved_options );

        $DB = new Database();
        $TAXONOMIES = new Taxonomies();

        $counts = [
            'Top Downloads'               => $DB->get_top_downloads( 10, $start_date, $end_date, $counts_type ),
            'Top Formats Downloaded'      => $DB->get_top_downloads_by_taxonomy( $TAXONOMIES->taxonomy_formats, $start_date, $end_date, $counts_type ),
            'Top Users Downloading Files' => $DB->get_top_users( $start_date, $end_date, $counts_type ),
        ];

        if ( ! get_option( ( new Settings() )->option_disable_resource_types ) ) {
            $counts[ 'Top Resource Types Downloaded' ] = $DB->get_top_downloads_by_taxonomy( $TAXONOMIES->taxonomy_resource_types, $start_date, $end_date, $counts_type );
        }
        if ( ! get_option( ( new Settings() )->option_disable_target_audiences ) ) {
            $counts[ 'Top Target Audiences Downloaded' ] = $DB->get_top_downloads_by_taxonomy( $TAXONOMIES->taxonomy_target_audiences, $start_date, $end_date, $counts_type );
        }

        $total_downloads = $DB->get_total_downloads( $start_date, $end_date, $counts_type );

        ob_start();
        $this->render_tables( $counts, $total_downloads );
        $html = ob_get_clean();

        $user_display_name = '';
        $current_user      = get_userdata( $current_user_id );
        if ( $current_user ) {
            $user_display_name = $current_user->display_name;
        }

        wp_send_json_success( [
            'html'              => $html,
            'start_date'        => $start_date,
            'end_date'          => $end_date,
            'counts_type'       => $counts_type,
            'last_ran_datetime' => wp_date( 'F j, Y \a\t g:i a T', $last_ran ),
            'last_ran_user'     =>  $user_display_name,
        ] );
    } // End ajax_filter_reports()
    
}