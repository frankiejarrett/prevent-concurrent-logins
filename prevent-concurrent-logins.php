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
