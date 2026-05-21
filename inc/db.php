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
        if ( ! (new Settings())->is_tracking() ) {
            return;
        }

        global $wpdb;

        // Check if the table exists
        // phpcs:ignore
        $result = $wpdb->get_results( "SHOW TABLES LIKE '$this->table_name'" );
        if ( ! empty( $result ) ) {
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
                KEY file_id (file_id),
                UNIQUE KEY unique_download (file_id, user_id, time)
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
        if ( empty( $user_id ) && ! get_option( (new Settings())->option_logged_out_tracking ) ) {
            return;
        }

        $user_ip = ! $user_id ? (new Helpers())->get_user_ip() : null;

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
     * Get the top downloads within an optional date range or all counts
     *
     * @param int $qty
     * @param string|null $start_date
     * @param string|null $end_date
     * @param string $counts_type 'logged_in' or 'all'
     * @return array
     */
    public function get_top_downloads( $qty = 10, $start_date = null, $end_date = null, $counts_type = 'logged_in' ) {
        $cache_key = 'top_downloads_' . md5( serialize( [ $qty, $start_date, $end_date, $counts_type ] ) );
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        global $wpdb;
        $post_type = ( new PostType() )->post_type;
        $has_date_filter = ( $start_date && $end_date );

        // Use custom table if date filter is applied or counts_type is logged_in/logged_out
        if ( $counts_type === 'logged_in' || $counts_type === 'logged_out' || ( $counts_type === 'all' && $has_date_filter ) ) {

            $where = '';
            $params = [ $post_type ];

            if ( $has_date_filter ) {
                $where .= ' AND d.time BETWEEN %s AND %s';
                $params[] = $start_date . ' 00:00:00';
                $params[] = $end_date . ' 23:59:59';
            }

            if ( $counts_type === 'logged_in' ) {
                $where .= ' AND d.user_id > 0';
            } elseif ( $counts_type === 'logged_out' ) {
                $where .= ' AND d.user_id = 0';
            }

            $params[] = (int) $qty;

            $sql = "
                SELECT d.file_id, COUNT(*) AS downloads, p.post_title
                FROM {$this->table_name} d
                JOIN {$wpdb->posts} p ON d.file_id = p.ID
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                $where
                GROUP BY d.file_id
                ORDER BY downloads DESC
                LIMIT %d
            ";

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

        } else { // counts_type = 'all' and no date filter
            $sql = "
                SELECT p.ID AS file_id,
                    COALESCE( CAST( pm.meta_value AS UNSIGNED ), 0 ) AS downloads,
                    p.post_title
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm
                    ON p.ID = pm.post_id
                    AND pm.meta_key = 'download_count'
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                ORDER BY downloads DESC
                LIMIT %d
            ";

            $params = [ $post_type, (int) $qty ];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        }

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
     * Get download counts for specific file IDs within an optional date range
     *
     * @param array $file_ids
     * @param string|null $start_date
     * @param string|null $end_date
     * @return array
     */
    public function get_download_counts( $file_ids, $start_date = null, $end_date = null ) {
        if ( empty( $file_ids ) || ! is_array( $file_ids ) ) {
            return [];
        }

        $cache_key = 'download_counts_' . md5( implode( ',', $file_ids ) . '|' . $start_date . '|' . $end_date );
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        $file_ids = array_map( 'intval', $file_ids );
        $placeholders = implode( ', ', $file_ids );

        global $wpdb;

        $date_filter = '';
        if ( $start_date && $end_date ) {
            $date_filter = $wpdb->prepare( " AND d.time BETWEEN %s AND %s ", $start_date . ' 00:00:00', $end_date . ' 23:59:59' ); // phpcs:ignore
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results( "
            SELECT d.file_id, COUNT(*) AS count
            FROM {$this->table_name} d
            WHERE d.file_id IN ($placeholders)
            $date_filter
            GROUP BY d.file_id
            ORDER BY count DESC
        " );

        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

        return $results;
    } // End get_download_counts()


    /**
     * Get the top users downloading files
     *
     * @param string|null $start_date
     * @param string|null $end_date
     * @param string $counts_type 'logged_in' or 'all'
     * @return array
     */
    public function get_top_users( $start_date = null, $end_date = null, $counts_type = 'logged_in' ) {
        if ( $counts_type === 'logged_out' ) {
            return []; // Not applicable for logged-out counts
        }

        $cache_key = 'top_users_downloads_' . md5( serialize( [ $start_date, $end_date, $counts_type ] ) );
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        global $wpdb;

        $where = [];
        $params = [];

        if ( $start_date && $end_date ) {
            $where[] = 'd.time BETWEEN %s AND %s';
            $params[] = $start_date . ' 00:00:00';
            $params[] = $end_date . ' 23:59:59';
        }

        // Only include logged-in users
        $where[] = 'd.user_id > 0';

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $params[] = 10;

        $sql = "
            SELECT d.user_id, COUNT(*) AS downloads, u.display_name
            FROM {$this->table_name} d
            JOIN {$wpdb->users} u ON d.user_id = u.ID
            $where_sql
            GROUP BY d.user_id
            ORDER BY downloads DESC
            LIMIT %d
        ";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );
        return $results;
    } // End get_top_users()


    /**
     * Get the top downloads by taxonomy
     *
     * @param string $taxonomy
     * @param string|null $start_date
     * @param string|null $end_date
     * @param string $counts_type 'logged_in' or 'all'
     * @return array
     */
    public function get_top_downloads_by_taxonomy( $taxonomy, $start_date = null, $end_date = null, $counts_type = 'logged_in' ) {
        $cache_key = 'top_downloads_by_taxonomy_' . $taxonomy . '_' . md5( serialize( [ $start_date, $end_date, $counts_type ] ) );
        $cached_results = wp_cache_get( $cache_key, $this->cache_group );
        if ( $cached_results !== false ) {
            return $cached_results;
        }

        global $wpdb;
        $post_type = ( new PostType() )->post_type;
        $has_date_filter = ( $start_date && $end_date );

        // Use custom table if date filter is applied or counts_type is logged_in/logged_out
        if ( $counts_type === 'logged_in' || $counts_type === 'logged_out' || ( $counts_type === 'all' && $has_date_filter ) ) {

            $where = '';
            $params = [ $taxonomy, $post_type ];

            if ( $has_date_filter ) {
                $where .= ' AND d.time BETWEEN %s AND %s';
                $params[] = $start_date . ' 00:00:00';
                $params[] = $end_date . ' 23:59:59';
            }

            if ( $counts_type === 'logged_in' ) {
                $where .= ' AND d.user_id > 0';
            } elseif ( $counts_type === 'logged_out' ) {
                $where .= ' AND d.user_id = 0';
            }

            $sql = "
                SELECT tt.term_id, t.name AS term_name, COUNT( d.file_id ) AS downloads
                FROM {$this->table_name} d
                JOIN {$wpdb->term_relationships} tr ON d.file_id = tr.object_id
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                JOIN {$wpdb->posts} p ON d.file_id = p.ID
                WHERE tt.taxonomy = %s
                AND p.post_type = %s
                AND p.post_status = 'publish'
                $where
                GROUP BY tt.term_id
                ORDER BY downloads DESC
                LIMIT 10
            ";

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

        } else { // counts_type = 'all' and no date filter
            $sql = "
                SELECT tt.term_id, t.name AS term_name,
                    SUM( COALESCE( CAST( pm.meta_value AS UNSIGNED ), 0 ) ) AS downloads
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->posts} p ON p.ID = tr.object_id
                LEFT JOIN {$wpdb->postmeta} pm
                    ON p.ID = pm.post_id
                    AND pm.meta_key = 'download_count'
                WHERE tt.taxonomy = %s
                AND p.post_type = %s
                AND p.post_status = 'publish'
                GROUP BY tt.term_id
                ORDER BY downloads DESC
                LIMIT 10
            ";

            $params = [ $taxonomy, $post_type ];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        }

        wp_cache_set( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );
        return $results;
    } // End get_top_downloads_by_taxonomy()


    /**
     * Get total downloads within an optional date range and counts type
     *
     * @param string|null $start_date
     * @param string|null $end_date
     * @param string $counts_type 'logged_in' or 'all'
     * @return int
     */
    public function get_total_downloads( $start_date = null, $end_date = null, $counts_type = 'all', $omit_admins = false, $in_depth_count_only = false ) {
        global $wpdb;
        $post_type = ( new PostType() )->post_type;
        $has_date_filter = ( $start_date && $end_date );

        if ( $counts_type !== 'all' || $has_date_filter ) {
            $where = [];
            $exclude_admins = '';

            if ( $counts_type === 'logged_in' ) {
                $where[] = 'd.user_id > 0';
                if ( $omit_admins ) {
                    $exclude_admins = "
                        AND d.user_id NOT IN (
                            SELECT user_id
                            FROM {$wpdb->usermeta}
                            WHERE meta_key = '{$wpdb->prefix}capabilities'
                            AND (
                                meta_value LIKE '%administrator%'
                                OR meta_value LIKE '%ntactc_admin%'
                            )
                        )
                    ";
                }
            } elseif ( $counts_type === 'logged_out' ) {
                $where[] = 'd.user_id = 0';
            } elseif ( $counts_type === 'all' ) {
                // Include both logged-in and logged-out users
                // no user_id filter needed
            }

            if ( $has_date_filter ) {
                $where[] = $wpdb->prepare(
                    'd.time BETWEEN %s AND %s',
                    $start_date . ' 00:00:00',
                    $end_date . ' 23:59:59'
                );
            }

            $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

            // Add in_depth_count filter
            $in_depth_join = '';
            $in_depth_where = '';
            if ( $in_depth_count_only ) {
                $in_depth_join = "JOIN {$wpdb->postmeta} pm_in_depth ON pm_in_depth.post_id = d.file_id AND pm_in_depth.meta_key = 'in_depth_count'";
                $in_depth_where = "AND pm_in_depth.meta_value = '1'";
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            return (int) $wpdb->get_var( "
                SELECT COUNT(*)
                FROM {$this->table_name} d
                $in_depth_join
                $where_sql
                $exclude_admins
                $in_depth_where
            " );

        } else { // counts_type = 'all' and no date filter
            $in_depth_join = '';
            $in_depth_where = '';
            if ( $in_depth_count_only ) {
                $in_depth_join = "JOIN {$wpdb->postmeta} pm_in_depth ON pm_in_depth.post_id = p.ID AND pm_in_depth.meta_key = 'in_depth_count'";
                $in_depth_where = "AND pm_in_depth.meta_value = '1'";
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            return (int) $wpdb->get_var( $wpdb->prepare( "
                SELECT SUM( COALESCE( CAST(pm.meta_value AS UNSIGNED), 0 ) )
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'download_count'
                $in_depth_join
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                $in_depth_where
            ", $post_type ) );
        }
    } // End get_total_downloads()
    

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