<?php
/**
 * Plugin Name: Prevent Concurrent Logins
 * Description: Prevents users from staying logged into the same account from multiple places.
 * Version: 0.1.1
 * Author: Frankie Jarrett
 * Author URI: http://frankiejarrett.com
 * License: GPLv2+
 * Text Domain: prevent-concurrent-logins
 */

/**
 * Define plugin constants
 */
define( 'PREVENT_CONCURRENT_LOGINS_VERSION', '0.1.1' );
define( 'PREVENT_CONCURRENT_LOGINS_PLUGIN', plugin_basename( __FILE__ ) );

/**
 * Detect if the current user has concurrent sessions
 *
 * @return bool
 */
function pcl_user_has_concurrent_sessions() {
	return ( is_user_logged_in() && count( wp_get_all_sessions() ) > 1 );
}

/**
 * Get the user's current session array
 *
 * @return array
 */
function pcl_get_current_session() {
	$sessions = WP_Session_Tokens::get_instance( get_current_user_id() );

	return $sessions->get( wp_get_session_token() );
}

/**
 * Only allow one session per user
 *
 * If the current user's session has been taken over by a newer
 * session then we will destroy their session automattically and
 * they will have to login again to continue.
 *
 * @action init
 *
 * @return void
 */
function pcl_prevent_concurrent_logins() {
	if ( ! pcl_user_has_concurrent_sessions() ) {
		return;
	}

	/**
	 * Filter to allow certain users to have concurrent sessions when necessary
	 *
	 * @param int  ID of the current user
	 *
	 * @return bool
	 */
	if ( false === apply_filters( 'pcl_prevent_concurrent_logins', true, get_current_user_id() ) ) {
		return;
	}

	$newest  = max( wp_list_pluck( wp_get_all_sessions(), 'login' ) );
	$session = pcl_get_current_session();

	if ( $session['login'] === $newest ) {
		wp_destroy_other_sessions();
	} else {
		wp_destroy_current_session();
	}
}
add_action( 'init', 'pcl_prevent_concurrent_logins' );

/**
 * Get all users with active sessions
 *
 * @return object WP_User
 */
function pcl_get_users_with_sessions() {
	$args = array(
		'number'     => '', // All users
		'blog_id'    => is_network_admin() ? 0 : get_current_blog_id(),
		'fields'     => array( 'ID' ), // Only the ID field is needed
		'meta_query' => array(
			array(
				'key'     => 'session_tokens',
				'compare' => 'EXISTS',
			),
		),
	);

	$users = new WP_User_Query( $args );

	return $users;
}

/**
 * Destroy old sessions for all users
 *
 * This function is meant to run on activation only so that old
 * sessions can be cleaned up immediately rather than waiting for
 * every user to login again.
 *
 * @action activate_{plugin}
 *
 * @return void
 */
function pcl_destroy_all_old_sessions() {
	$users = pcl_get_users_with_sessions()->get_results();

	foreach ( $users as $user ) {
		$sessions = get_user_meta( $user->ID, 'session_tokens', true );

		// Move along if this user only has one session
		if ( 1 === count( $sessions ) ) {
			continue;
		}

		// Extract the login timestamps from all sessions
		$logins = array_values( wp_list_pluck( $sessions, 'login' ) );

		// Sort by login timestamp DESC
		array_multisort( $logins, SORT_DESC, $sessions );

		// Get the newest (top-most) session
		$newest = array_slice( $sessions, 0, 1 );

		// Keep only the newest session
		update_user_meta( $user->ID, 'session_tokens', $newest );
	}
}
register_activation_hook( __FILE__, 'pcl_destroy_all_old_sessions' );
