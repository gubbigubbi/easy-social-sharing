<?php
/**
 * Handles storage and retrieval of social networks
 *
 * @class    ESS_Social_Networks
 * @version  1.0.0
 * @package  EasySocialSharing/Classes
 * @category Class
 * @author   ThemeGrill
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ESS_Social_Networks Class
 */
class ESS_Social_Networks {

	/**
	 * Get social networks from the database.
	 * @since  1.2.0
	 * @return array
	 */
	public static function get_networks() {
		global $wpdb;

		$raw_networks = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ess_social_networks order by network_order ASC;" );
		$networks     = array();

		foreach ( $raw_networks as $key => $raw_network ) {
			$networks[ $key ]                         = $raw_network;
			$networks[ $key ]->formatted_network_name = self::get_formatted_name( $raw_network->network_name );
		}

		return $networks;
	}

	/**
	 * Get array of social networks name.
	 * @since  1.2.0
	 * @return array of strings
	 */
	public static function get_network_names() {
		return wp_list_pluck( self::get_networks(), 'network_name' );
	}

	/**
	 * Get social network desc.
	 * @since  1.2.0
	 * @return array of strings
	 */
	public static function get_network_desc() {
		return wp_list_pluck( self::get_networks(), 'network_desc', 'network_name' );
	}

	/**
	 * Get social network count.
	 * @since  1.2.0
	 * @return array of strings
	 */
	public static function get_network_count() {
		return wp_list_pluck( self::get_networks(), 'network_count', 'network_name' );
	}

	/**
	 * Get array of social networks name.
	 * @since  1.2.0
	 * @return array of strings
	 */
	public static function get_allowed_networks() {
		if ( 'yes' === get_option( 'easy_social_sharing_api_support_networks_only' ) ) {
			return array_intersect( self::get_network_names(), ess_get_share_networks_with_api_support() );
		}

		return self::get_network_names();
	}

	/**
	 * Format social network name.
	 * @param  string $name
	 * @return string
	 */
	private static function get_formatted_name( $name ) {
		$supported_networks = ess_get_core_supported_social_networks();

		return $name ? $supported_networks[ $name ] : __( 'Facebook', 'easy-social-sharing' );
	}

	/**
	 * Prepare and format network data for DB insertion.
	 * @param  array $network_data
	 * @return array
	 */
	private static function prepare_network_data( $network_data ) {
		foreach ( $network_data as $key => $value ) {
			if ( method_exists( __CLASS__, 'format_' . $key ) ) {
				$network_data[ $key ] = call_user_func( array( __CLASS__, 'format_' . $key ), $value );
			}
		}

		return $network_data;
	}

	/**
	 * Format the social network name.
	 * @param  string $name
	 * @return string
	 */
	private static function format_network_name( $name ) {
		return strtolower( $name );
	}

	/**
	 * Format the social network description.
	 * @param  string $desc
	 * @return string
	 */
	private static function format_network_desc( $desc ) {
		return ess_clean( $desc );
	}

	/**
	 * Format the social network count.
	 * @param  int $count
	 * @return int
	 */
	private static function format_network_count( $count ) {
		return absint( $count );
	}

	/**
	 * Format the social network API support.
	 * @param  int $api_support
	 * @return int
	 */
	private static function format_is_api_support( $api_support ) {
		return absint( $api_support );
	}

	/**
	 * Insert a new social network.
	 *
	 * Internal use only.
	 *
	 * @since  1.2.0
	 * @access private
	 *
	 * @param  array $network_data
	 * @return int   Social network ID.
	 */
	public static function _insert_network( $network_data ) {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'ess_social_networks', self::prepare_network_data( $network_data ) );

		return $wpdb->insert_id;
	}

	/**
	 * Update a social network.
	 *
	 * Internal use only.
	 *
	 * @since  1.2.0
	 * @access private
	 *
	 * @param int $network_id
	 * @param array $network_data
	 */
	public static function _update_network( $network_id, $network_data ) {
		global $wpdb;

		$network_id = absint( $network_id );

		$wpdb->update(
			$wpdb->prefix . "ess_social_networks",
			self::prepare_network_data( $network_data ),
			array(
				'network_id' => $network_id
			)
		);
	}

	/**
	 * Delete a social network from the database.
	 *
	 * Internal use only.
	 *
	 * @since  1.2.0
	 * @access private
	 *
	 * @param int $network_id
	 */
	public static function _delete_network( $network_id ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ess_social_networks WHERE network_id = %d;", $network_id ) );
	}

	/**
	 * Update API Support for a social network in the DB.
	 *
	 * Internal use only.
	 *
	 * @since  1.2.0
	 * @access private
	 *
	 * @param int $network_id
	 * @param string $network_name
	 */
	public static function _update_network_api_support( $network_id, $network_name ) {
		global $wpdb;

		$is_api_support = in_array( $network_name, ess_get_share_networks_with_api_support() );
		$wpdb->update( $wpdb->prefix . 'ess_social_networks', array( 'is_api_support' => $is_api_support ), array( 'network_id' => $network_id ) );
	}

	/**
	 * Get social network statistics.
	 * @since  1.2.0
	 * @return array
	 */
	public static function _get_network_statistics( $network_name, $page_url ) {

		global $wpdb;

		$network_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ess_social_statistics WHERE network_name=%s and share_url=%s order by id desc limit 1", $network_name, $page_url ) );

		if ( isset( $network_data[0] ) ) {

			return ( (array) $network_data[0] );
		}

		return array();

	}

	/**
	 * Get social network count.
	 * @since  1.2.0
	 * @return array
	 */
	public static function get_network_count_for_non_api_support( $network_name, $page_url, $ip_address ) {

		global $wpdb;

		$network_data = $wpdb->get_results( $wpdb->prepare( "

SELECT network_name  FROM {$wpdb->prefix}ess_social_statistics GROUP BY network_name,share_url,ip_address  HAVING

 network_name=%s and share_url=%s  AND ip_address=%s ;", $network_name, $page_url, $ip_address ) );

		return count( $network_data );

	}

	public static function update_single_network_count( $network_name, $post_id, $ip_info, $ip_address, $share_location, $share_url, $latest_cout ) {

		global $wpdb;

		$status = $wpdb->query( $wpdb->prepare(
			"INSERT INTO  {$wpdb->prefix}ess_social_statistics
(network_name,sharing_date,post_id,ip_info,ip_address,share_location,share_url,latest_count)
 						VALUES(%s,CURRENT_TIMESTAMP ,%d,%s,%s,%s,%s,%d);", $network_name, $post_id, $ip_info, $ip_address, $share_location, $share_url, $latest_cout )
		);

		return $status;


	}

	public static function get_network_analytic_data( $date = array(), $location = "All", $start_date, $end_date, $date_fragment ) {

		global $wpdb;

		$location_query_string = "LOCATION_QUERY";

		$format_date_select_statement = " DATE_FORMAT(sharing_date,'%%b - %%d, %%Y') ";

		$format_date_group_by = " CAST(sharing_date AS DATE) ";

		$format_sharing_date = " DATE_FORMAT(sharing_date,'%%Y-%%m-%%d') ";


		if ( $date_fragment == "monthly" ) {

			$format_date_select_statement = " DATE_FORMAT(sharing_date,'%%b - %%Y') ";

			$format_sharing_date = " DATE_FORMAT(sharing_date,'%%Y-%%m') ";

			$format_date_group_by = " EXTRACT(YEAR_MONTH FROM sharing_date) ";

		} else if ( $date_fragment == "yearly" ) {

			$format_sharing_date = " DATE_FORMAT(sharing_date,'%%Y') ";

			$format_date_select_statement = " DATE_FORMAT(sharing_date,'%%Y') ";

			$format_date_group_by = " YEAR(sharing_date) ";
		}

		$query = "
SELECT
    COUNT(*) as total_count,
    date_string,
    {$format_sharing_date} as sharing_date,
     network_name,
    share_location,
    post_id
FROM
    (SELECT

  $format_date_select_statement as date_string,

        CAST(sharing_date AS DATE) AS sharing_date,
            network_name,
            ip_address,
            share_url,
            share_location,
            post_id
    FROM
        {$wpdb->prefix}ess_social_statistics
    GROUP BY network_name , ip_address , {$format_date_group_by} , share_url , share_location
    HAVING (CAST(sharing_date AS DATE) >= %s
        AND CAST(sharing_date AS DATE) <= %s)
        {$location_query_string}
    ORDER BY sharing_date ASC) tmp
GROUP BY sharing_date , network_name;";


		if ( strtolower( $location ) == "all" || $location == null || $location == "" ) {

			$query = str_replace( $location_query_string, "", $query );

			$network_data = $wpdb->get_results( $wpdb->prepare( $query, $start_date, $end_date ) );

		} else {

			$query = str_replace( $location_query_string, " AND share_location = %s ", $query );


			$network_data = $wpdb->get_results( $wpdb->prepare( $query, $start_date, $end_date, $location ) );


		}

		return $network_data;
	}

}
