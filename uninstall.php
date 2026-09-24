<?php
/**
 * Uninstall cleanup, on explicit "Delete" from the Plugins screen.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'oyster_wc_interakt_settings' );

// Delivery markers. Normally left behind only for scans already sent.
// `global` is required: WordPress includes this file from inside a function.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'oyster_wc_interakt_sent_%'" );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'oyster-wc-interakt' );
}
