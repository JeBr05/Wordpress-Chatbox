<?php
/**
 * Analytics helper.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Analytics {

	/**
	 * Get dashboard stats.
	 */
	public static function stats(): array {
		global $wpdb;
		$conversations = $wpdb->prefix . 'jcb_conversations';
		$messages      = $wpdb->prefix . 'jcb_messages';
		$events        = $wpdb->prefix . 'jcb_events';

		$since_30 = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$since_7  = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );

		$total_conversations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$conversations}" );
		$total_messages      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$messages}" );
		$recent_messages     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$messages} WHERE created_at >= %s", $since_7 ) );
		$avg_latency         = (int) $wpdb->get_var( "SELECT AVG(latency_ms) FROM {$messages} WHERE role = 'assistant' AND latency_ms IS NOT NULL" );
		$tokens_7_days      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(tokens) FROM {$messages} WHERE role = 'assistant' AND created_at >= %s", $since_7 ) );

		$recent = $wpdb->get_results(
			"SELECT m.role, m.content, m.created_at, c.page_url
			FROM {$messages} m
			LEFT JOIN {$conversations} c ON c.id = m.conversation_id
			ORDER BY m.created_at DESC
			LIMIT 20",
			ARRAY_A
		);

		$events_list = $wpdb->get_results(
			$wpdb->prepare( "SELECT event_type, event_data, created_at FROM {$events} WHERE created_at >= %s ORDER BY created_at DESC LIMIT 30", $since_30 ),
			ARRAY_A
		);

		return array(
			'total_conversations' => $total_conversations,
			'total_messages'      => $total_messages,
			'recent_messages'     => $recent_messages,
			'avg_latency_ms'      => $avg_latency,
			'tokens_7_days'       => $tokens_7_days,
			'recent'              => array_map( array( __CLASS__, 'safe_row' ), $recent ),
			'events'              => $events_list,
		);
	}

	/**
	 * Make row safe.
	 *
	 * @param array $row Row.
	 */
	private static function safe_row( array $row ): array {
		$row['content'] = wp_trim_words( wp_strip_all_tags( (string) $row['content'] ), 28 );
		$row['page_url'] = esc_url_raw( (string) $row['page_url'] );
		return $row;
	}

	/**
	 * Cleanup old logs.
	 */
	public static function cleanup_old_logs(): void {
		global $wpdb;
		$options = JCB_Options::all();
		$days    = JCB_Sanitizer::int_range( $options['log_retention_days'], 1, 365 );
		$before  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}jcb_messages WHERE created_at < %s", $before ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}jcb_events WHERE created_at < %s", $before ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}jcb_conversations WHERE last_seen < %s", $before ) );
	}
}
