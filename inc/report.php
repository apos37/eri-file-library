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

        // Fetch data for reports
        $DB = new Database();
        $TAXONOMIES = new Taxonomies();

        $counts = [
            'Top Downloads'                   => $DB->get_top_downloads(),
            'Top Formats Downloaded'          => $DB->get_top_downloads_by_taxonomy( $TAXONOMIES->taxonomy_formats ),
            'Top Resource Types Downloaded'   => $DB->get_top_downloads_by_taxonomy( $TAXONOMIES->taxonomy_resource_types ),
            'Top Target Audiences Downloaded' => $DB->get_top_downloads_by_taxonomy( $TAXONOMIES->taxonomy_target_audiences ),
            'Top Users Downloading Files'     => $DB->get_top_users(),
        ];
        ?>

        <div class="wrap">
            <h1><?php echo esc_attr( get_admin_page_title() ); ?></h1>
            <p><?php echo esc_html__( 'Insights on downloads.', 'eri-file-library' ); ?></p>

            <form id="erifl-export-counts-btn" method="post" action="">
                <?php wp_nonce_field( $this->nonce ); ?>
                <input type="hidden" name="export" value="1">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'Export Counts', 'eri-file-library' ); ?>
                </button>
            </form>

            <div id="erifl-report">
                <?php foreach ( $counts as $title => $data ) : ?>
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
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    } // End page()


    /**
     * Export the counts
     *
     * @return void
     */
    public function export_counts_to_csv() {
        // Only on the report page
        if ( isset( $_GET[ 'post_type' ] ) && sanitize_key( $_GET[ 'post_type' ] ) == (new PostType())->post_type && // phpcs:ignore 
             isset( $_GET[ 'page' ] ) && sanitize_key( $_GET[ 'page' ] ) == ERIFL__TEXTDOMAIN . '_report' ) { // phpcs:ignore 

            // Check if the export action is triggered and the nonce is valid
            if ( isset( $_POST[ 'export' ] ) && intval( $_POST[ 'export' ] ) === 1 ) { // phpcs:ignore 

                // Check nonce for security
                if ( !isset( $_POST[ '_wpnonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ '_wpnonce' ] ) ), $this->nonce ) ) {
                    wp_die( esc_html__( 'Nonce verification failed!', 'eri-file-library' ) );
                }
        
                // Ensure the user has the right permissions
                if ( !current_user_can( 'administrator' ) ) {
                    wp_die( esc_html__( 'You do not have permission to export download counts.', 'eri-file-library' ) );
                }

                // Filename
                $host = wp_parse_url( get_site_url(), PHP_URL_HOST );
                $domain = pathinfo( $host, PATHINFO_FILENAME );
                $filename = $domain . '_download_counts.csv';

                // Open the output stream and send the appropriate headers for CSV download
                header( 'Content-Type: text/csv' );
                header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
                $output = fopen( 'php://output', 'w' );
        
                // Output the column headers
                $header = [ 
                    __( 'File ID', 'eri-file-library' ), 
                    __( 'File Title', 'eri-file-library' ), 
                    __( 'Downloads', 'eri-file-library' )
                ];
                fputcsv( $output, $header );
        
                // Output the rows
                $DB = new Database();
                $sorted_file_ids = $DB->get_sorted_file_ids();
                $limit = 10000;

                for ( $i = 0; $i < count( $sorted_file_ids ); $i += $limit ) {
                    $file_ids_chunk = array_slice( $sorted_file_ids, $i, $limit );
                    $downloads = $DB->get_download_counts( $file_ids_chunk );

                    foreach ( $downloads as $download ) {
                        $row = [ 
                            $download->file_id, 
                            get_the_title( $download->file_id ),
                            $download->count 
                        ];
                        fputcsv( $output, $row );
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
		wp_enqueue_style( ERIFL_TEXTDOMAIN . '-styles', ERIFL_CSS_PATH . 'report.css', [], ERIFL_SCRIPT_VERSION );
    } // End enqueue_scripts()
    
}