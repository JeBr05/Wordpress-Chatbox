<?php
/**
 * Uninstall handler.
 *
 * @package AIKnowledgeChatbot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = get_option( 'aikb_options', array() );

if ( ! empty( $options['delete_data_on_uninstall'] ) ) {
	global $wpdb;
	delete_option( 'aikb_options' );
	delete_option( 'aikb_db_version' );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aikb_messages" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aikb_conversations" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aikb_events" );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_aikb_%'" );
}
