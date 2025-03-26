<?php
/**
 * Database-related operations
 */


/**
 * Define Namespaces
 */
namespace Apos37\EriFileLibrary;
use Apos37\EriFileLibrary\Helpers;
use Apos37\EriFileLibrary\PostType;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * The Database class
 */
class Database {

    /**
     * Table name
     *
     * @var string
     */
    public $table_name;
    

    /**
     * Cache group
     *
     * @var string
     */
    private $cache_group = 'erifl_file_downloads';


    /**
     * Constructor
     */
    public function __construct() {

        // Table name
        global $wpdb;
        $this->table_name = $wpdb->prefix . ERIFL__TEXTDOMAIN;

    } // End __construct()


    /**
     * Create the custom table if it doesn't exist
     *
     * @return void
     */
    public function maybe_create_table() {
        if ( !(new Settings())->is_tracking() ) {
            return;
        }

        global $wpdb;

        // Check if the table exists
        // phpcs:ignore
        $wpdb->get_results( "SHOW TABLES LIKE '$this->table_name'" );
        if ( !empty( $result ) ) {
            return;
        }

        // SQL to create the table
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "
            CREATE TABLE $this->table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                user_ip VARCHAR(45) DEFAULT NULL,
                file_id BIGINT(20) UNSIGNED NOT NULL,
                time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY file_id (file_id)
            ) $charset_collate;
        ";

        // Include the required file for dbDelta function
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    } // End maybe_create_table()


    /**
     * Delete the custom table
     *
     * @return int|bool
     */
    public function delete_table() {
        $this->clear_all_cache();
        global $wpdb;
        $sql = "DROP TABLE IF EXISTS $this->table_name";
        // phpcs:ignore
        return $wpdb->query( $sql );
    } // End delete_table()


    /**
     * Add a record to track user interaction with a file.
     *
     * @param int $user_id
     * @param int $file_id
     * @param string $type
     * @param string $action
     * 
     * @return int|false
     */
    public function add_record( $user_id, $file_id ) {
        $this->clear_all_cache();
        global $wpdb;

        // Get the ip address if no user_id
        $user_ip = !$user_id ? (new Helpers())->get_user_ip() : null;

        // Prepare the data to be inserted into the table.
        $data = [
            'user_id'   => $user_id,
            'user_ip'   => $user_ip,
            'file_id'   => $file_id,
            'time'      => current_time( 'mysql' )
        ];

        // Insert the record into the table.
        // phpcs:ignore
        return $wpdb->insert( $this->table_name, $data );
    } // End add_record()


    /**
     * Delete a record for a specific user and file.
     *
     * @param int $user_id
     * @param int $file_id
     *
     * @return int|false
     */
    public function delete_record( $user_id, $file_id ) {
        $this->clear_all_cache();
        global $wpdb;

        // Prepare the conditions to delete the record.
        $where = [
            'user_id'   => $user_id,
            'file_id' => $file_id,
        ];

        // Delete the record from the table.
        // phpcs:ignore
        return $wpdb->delete( $this->table_name, $where );
    } // End delete_record()


    /**
     * Delete all records associated with a specific file when it is permanently deleted.
     *
     * @param int $file_id
     * @return int|false
     */
    public function delete_file_records( $file_id ) {
        if ( empty( $file_id ) || !is_numeric( $file_id ) ) {
            return;
        }

        $this->clear_all_cache();

        global $wpdb;
        // phpcs:ignore
        return $wpdb->delete( $this->table_name, [ 'file_id' => $file_id ] );
    } // End delete_file_records()


    /**
     * Delete all records associated with a specific user
     *
     * @param int $user_id
     * @return int|false
     */
    public function delete_user_records( $user_id ) {
        if ( empty( $user_id ) || !is_numeric( $user_id ) ) {
            return;
        }

        $this->clear_all_cache();

        global $wpdb;
        // phpcs:ignore
        return $wpdb->delete( $this->table_name, [ 'user_id' => $user_id ] );
    } // End delete_user_records()


    /**
     * Get records based on optional filters: user_id, file_id.
     *
     * @param int $user_id
     * @param int $file_id
     * @param string $type 
     *
     * @return array
     */
    public function get( $user_id = null, $user_ip = null, $file_id = null, $taxonomies = [], $start_date = null, $end_date = null, $orderby = null ) {
        // Try to get cached results first
        $cache_key = 'get_downloads_' . md5( json_encode( [ $user_id, $user_ip, $file_id, $taxonomies, $start_date, $end_date, $orderby ] ) );
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        // Start building the query
        $query = "SELECT * FROM $this->table_name WHERE 1=1"; // Always true condition to add optional filters

        global $wpdb;
        
        // Add filters for user_id, user_ip, and file_id if provided
        if ( !is_null( $user_id ) ) {
            $query .= $wpdb->prepare( " AND user_id = %d", $user_id );
        }
        if ( !is_null( $user_ip ) ) {
            $query .= $wpdb->prepare( " AND user_ip = %s", $user_ip );
        }
        if ( !is_null( $file_id ) ) {
            $query .= $wpdb->prepare( " AND file_id = %d", $file_id );
        }
        if ( !is_null( $start_date ) ) {
            $query .= $wpdb->prepare( " AND time >= %s", $start_date . ' 00:00:00' );
        }
        if ( !is_null( $end_date ) ) {
            $query .= $wpdb->prepare( " AND time <= %s", $end_date . ' 23:59:59' );
        }

        // Handle taxonomy filtering
        if ( !empty( $taxonomies ) ) {
            $matching_file_ids = $this->get_files_by_taxonomy( $taxonomies );

            if ( !empty( $matching_file_ids ) ) {
                $query .= " AND file_id IN (" . implode( ',', array_map( 'absint', $matching_file_ids ) ) . ")";
            } else {
                return []; // If no matching file IDs, return an empty array
            }
        }

        // Add order by clause to get most recent first
        if ( !is_null( $orderby ) ) {
            $query .= " ORDER BY " . esc_sql( $orderby ) . " DESC";
        }

        // Execute the query
        $results = $wpdb->get_results( $query ); // phpcs:ignore

        // Cache the results
        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

        return $results;
    } // End get()


    /**
     * Get files by taxonomy
     *
     * @param array $taxonomies
     * @return array
     */
    private function get_files_by_taxonomy( $taxonomies ) {
        // Try to get cached results first
        $cache_key = 'files_by_taxonomy_' . md5( json_encode( $taxonomies ) );
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        global $wpdb;

        $query = "
            SELECT DISTINCT object_id 
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE tt.taxonomy IN ('" . implode( "','", array_map( 'esc_sql', array_keys( $taxonomies ) ) ) . "')
        ";

        $conditions = [];
        foreach ( $taxonomies as $tax => $term_slug ) {
            $conditions[] = $wpdb->prepare( "t.slug = %s", $term_slug );
        }

        if ( !empty( $conditions ) ) {
            $query .= " AND (" . implode( " OR ", $conditions ) . ")";
        }

        // phpcs:ignore
        $results = $wpdb->get_col( $query );

        // Cache the results
        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

        return $results;
    } // End get_files_by_taxonomy()


    /**
     * Get the top downloads (using the database records)
     *
     * @return array
     */
    public function get_top_downloads( $qty = 10 ) {
        // Try to get cached results first
        $cache_key = 'top_downloads';
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        // Get the post type
        $post_type = ( new PostType() )->post_type;

        global $wpdb;

        // Prepare and execute the query to get top downloads with qty
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT d.file_id, COUNT(*) AS downloads, p.post_title
            FROM {$this->table_name} d
            JOIN {$wpdb->posts} p ON d.file_id = p.ID
            WHERE p.post_type = %s AND p.post_status = 'publish'
            GROUP BY d.file_id
            ORDER BY downloads DESC
            LIMIT %d
        ", $post_type, $qty ) );

        // Cache the results for future use
        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

        return $results;
    } // End get_top_downloads()


    /**
     * Get the top file counts (using the download count on the file posts)
     *
     * @return array
     */
    public function get_top_file_counts( $qty = 10 ) {
        // Try to get cached results first
        $cache_key = 'top_counts';
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        // Get the post type
        $POSTTYPE = new PostType();
        $post_type = $POSTTYPE->post_type;
        $meta_key = $POSTTYPE->meta_key_download_count;

        // Get the top files ordered by custom meta key ($this->meta_key_count)
        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => $qty,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value_num',
            'meta_key'       => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'order'          => 'DESC',
        ];

        $posts = get_posts( $args );

        // Prepare the results
        $results = [];
        foreach ( $posts as $post ) {
            $downloads = absint( get_post_meta( $post->ID, $meta_key, true ) );
            
            // Only include posts where the download count is greater than 0
            if ( $downloads > 0 ) {
                $results[] = [
                    'file_id'    => $post->ID,
                    'downloads'  => $downloads,
                    'post_title' => $post->post_title,
                ];
            }
        }

        // Cache the results for future use
        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

        return $results;
    } // End get_top_file_counts()


    /**
     * Get file ids sorted by download count
     *
     * @return array
     */
    public function get_sorted_file_ids() {
        // Try to get cached results first
        $cache_key = 'sorted_file_ids';
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        // Get the post type
        $post_type = ( new PostType() )->post_type;

        global $wpdb;

        // Prepare and execute the query to get sorted file IDs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_col( $wpdb->prepare( "
            SELECT d.file_id
            FROM {$this->table_name} d
            JOIN {$wpdb->posts} p ON d.file_id = p.ID
            WHERE p.post_type = %s AND p.post_status = 'publish'
            GROUP BY d.file_id
            ORDER BY COUNT(*) DESC
        ", $post_type ) );

        // Cache the results for future use
        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

        return $results;
    } // End get_sorted_file_ids()


    /**
     * Get all download counts
     *
     * @param int $last_id
     * @param int $limit
     * @return array
     */
    public function get_download_counts( $file_ids ) {
        // If no file IDs are provided, return an empty array
        if ( empty( $file_ids ) || !is_array( $file_ids ) ) {
            return [];
        }

        // Use a generic cache key for download counts (not based on file IDs)
        $cache_key = 'download_counts';

        // Try to get cached results first
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        // Sanitize and prepare file IDs
        $file_ids = array_map( 'intval', $file_ids );
        $placeholders = implode( ', ', $file_ids );

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results( "
            SELECT d.file_id, COUNT(*) AS count
            FROM {$this->table_name} d
            WHERE d.file_id IN ($placeholders)
            GROUP BY d.file_id
            ORDER BY count DESC
        " );

        // Cache the results for future use
        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

        return $results;
    } // End get_download_counts()


    /**
     * Get the top user downloads
     *
     * @return array
     */
    public function get_top_users() {
        // Try to get cached results first
        $cache_key = 'top_users_downloads';
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        // Now that we know the cache is not present, get $wpdb
        global $wpdb;

        // Query to get the top users by download count
        $table_name = $this->table_name;
        $users_table = $wpdb->users;

        // Prepare and execute the query
        // phpcs:ignore
        $query = $wpdb->prepare( "
            SELECT user_id, COUNT(*) AS downloads, display_name
            FROM {$table_name}
            JOIN {$users_table} ON {$table_name}.user_id = {$users_table}.ID
            GROUP BY user_id
            ORDER BY downloads DESC
            LIMIT %d
        ", 10 );

        $results = $wpdb->get_results( $query ); // phpcs:ignore

        // Cache the results for future use
        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

        return $results;
    } // End get_top_users()


    /**
     * Get the number of downloads grouped by multiple taxonomies
     *
     * @param array $taxonomies
     * @return array
     */
    public function get_top_downloads_by_taxonomy( $taxonomy ) {
        // Try to get cached results first
        $cache_key = 'top_downloads_by_taxonomy_' . $taxonomy;
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        global $wpdb;
        $post_type = ( new PostType() )->post_type;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT tt.term_id, t.name AS term_name, COUNT(d.file_id) AS downloads
            FROM {$wpdb->prefix}eri_file_library d
            JOIN {$wpdb->term_relationships} tr ON d.file_id = tr.object_id
            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            JOIN {$wpdb->posts} p ON d.file_id = p.ID
            WHERE tt.taxonomy = %s
            AND p.post_type = %s AND p.post_status = 'publish'
            GROUP BY tt.term_id
            ORDER BY downloads DESC
            LIMIT 10
        ", $taxonomy, $post_type ) );

        // Cache the results for future use
        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

        return $results;
    } // End get_top_downloads_by_taxonomy()
    

    /**
     * Get user download history
     *
     * @param int|null $user_id
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function get_user_download_history( $user_id, $start_date = null, $end_date = null ) {
        global $wpdb;

        // Try to get cached results first
        $cache_key = 'user_download_history_' . $user_id;
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }
    
        // Start building the query
        $query = "
            SELECT file_id, MAX(time) AS last_download_time
            FROM $this->table_name
            WHERE user_id = %d
        ";

        $query_params = [ $user_id ];

        // Add filters for date range if provided
        if ( !is_null( $start_date ) ) {
            $query .= " AND time >= %s";
            $query_params[] = $start_date . ' 00:00:00';
        }
        if ( !is_null( $end_date ) ) {
            $query .= " AND time <= %s";
            $query_params[] = $end_date . ' 23:59:59';
        }

        // Group by file_id to ensure only the most recent download per file
        $query .= " GROUP BY file_id ORDER BY last_download_time DESC";

        // Prepare query with dynamic parameters
        // phpcs:ignore
        $prepared_query = $wpdb->prepare( $query, ...$query_params ); 

        // Execute the query
        // phpcs:ignore
        $results = $wpdb->get_results( $prepared_query );

        // Store results in cache
        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

        return $results;
    } // End get_user_download_history()


    /**
     * Clear all cache results when a new download has occurred.
     *
     * @return boolean
     */
    public function clear_all_cache() {
        return wp_cache_flush( $this->cache_group );
    } // End clear_all_cache()
    
}