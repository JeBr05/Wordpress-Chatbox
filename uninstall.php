<?php
/**
 * Uninstall handler.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = get_option( 'jeroens_chatbox_options', get_option( 'aikb_options', array() ) );

if ( ! empty( $options['delete_data_on_uninstall'] ) ) {
	global $wpdb;
	delete_option( 'jeroens_chatbox_options' );
	delete_option( 'jeroens_chatbox_db_version' );
	delete_option( 'aikb_options' );
	delete_option( 'jcb_db_version' );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jcb_messages" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jcb_conversations" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jcb_events" );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_jcb_%'" );
}
