<?php
/**
 * EasySocialSharing ESS_AJAX
 *
 * AJAX Event Handler
 *
 * @class    ESS_AJAX
 * @version  1.0.0
 * @package  EasySocialSharing/Classes
 * @category Class
 * @author   ThemeGrill
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ESS_AJAX Class
 */
class ESS_AJAX {

	/**
	 * Hooks in ajax handlers
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax)
	 */
	public static function add_ajax_events() {
		// easy_social_sharing_EVENT => nopriv
		$ajax_events = array(
			'update_single_share' => true,
			'get_shares_count'    => true,
			'get_total_counts'    => true,
			'rated'               => false
		);

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_easy_social_sharing_' . $ajax_event, array( __CLASS__, $ajax_event ) );

			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_easy_social_sharing_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Update single share on network click.
	 */
	public static function update_single_share() {
		ob_start();

		check_ajax_referer( 'update-share', 'security' );

		$post_id   = intval( $_POST['post_id'] );
		$min_count = intval( $_POST['min_count'] );
		$network   = ess_clean( $_POST['network'] );
		$page_url  = ess_clean( $_POST['page_url'] );

		$share_counts = self::get_shares_count( $network, $min_count, $post_id, $page_url, false, true );
		wp_send_json_success( array( 'counts' => $share_counts ) );

		die();
	}

	/**
	 * Get shares count for specified networks.
	 */
	public static function get_shares_count( $network = '', $min_count = 0, $post_id = '', $page_url = '', $is_ajax_request = true, $click_update = false ) {
		ob_start();

		if ( $is_ajax_request ) {
			check_ajax_referer( 'shares-count', 'security' );

			$post_id   = intval( $_POST['post_id'] );
			$min_count = intval( $_POST['min_count'] );
			$network   = ess_clean( $_POST['network'] );
			$page_url  = ess_clean( $_POST['page_url'] );
		} else {
			$post_id   = '' != $post_id ? $post_id : get_the_ID();
			$page_url  = '' != $page_url ? $page_url : get_permalink();
		}

		$share_counts_array = ( $social_shares = get_post_meta( $post_id, '_ess_social_shares_' . $network, true ) ) ? $social_shares : array();

		if ( ess_check_cached_counts( $post_id, $network, $click_update ) ) {
			$share_counts = (int) $share_counts_array[ 'counts' ];
		} else {
			$share_counts_received = ess_get_shares_number( $network, $page_url, $post_id );

			if ( in_array( $share_counts_received, array( false, 0 ) ) ) {
				$share_counts = isset( $share_counts_array[ 'counts' ] ) ? (int) $share_counts_array['counts'] : 0;
				$share_counts_temp_array['force_update'] = true;

				if ( $click_update && ! in_array( $network, ess_get_share_networks_with_api_support() ) ) {
					$share_counts++;
				}
			} else {
				$share_counts = (int) $share_counts_received;
				$share_counts_temp_array['force_update'] = false;
			}

			if ( $click_update && in_array( $network, ess_get_share_networks_with_api_support() ) ) {
				$share_counts_temp_array['force_update'] = true;
			}

			$share_counts_temp_array['counts']   = (int) $share_counts;
			$share_counts_temp_array['last_upd'] = date( 'Y-m-d H:i:s' );

			update_post_meta( $post_id, '_ess_social_shares_' . $network, $share_counts_temp_array );
		}

		$share_counts_output = '';

		if ( $share_counts >= $min_count ) {
			$share_counts_output = esc_html( ess_format_compact_number( (int) $share_counts, $network ) );
		}

		if ( ! $is_ajax_request ) {
			return $share_counts_output;
		} else {
			die( $share_counts_output );
		}
	}

	/**
	 * Get total counts for all listed networks.
	 */
	public static function get_total_counts() {
		ob_start();

		check_ajax_referer( 'total-counts', 'security' );

		$post_id  = intval( $_POST['post_id'] );
		$page_url = ess_clean( $_POST['page_url'] );

		if ( ! $post_id ) {
			die();
		}

		$total_shares     = 0;
		$allowed_networks = get_option( 'easy_social_sharing_allowed_networks', array() );

		foreach ( $allowed_networks as $network ) {
			$total_shares += self::get_shares_count( $network, 0, $post_id, $page_url, false );
		}

		$total_shares = ess_format_compact_number( (int) $total_shares );

		wp_send_json_success( array( 'totals' => $total_shares ) );
	}

	/**
	 * Triggered when clicking the rating footer.
	 */
	public static function rated() {
		if ( ! current_user_can( 'manage_options' ) ) {
			die(-1);
		}

		update_option( 'easy_social_sharing_admin_footer_text_rated', 1 );
		die();
	}
}

ESS_AJAX::init();
