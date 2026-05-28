<?php
/**
 * Logging helper.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Logger {

	/**
	 * Store event.
	 *
	 * @param string $type Event type.
	 * @param array  $data Event data.
	 */
	public static function event( string $type, array $data = array() ): void {
		global $wpdb;
		$options = JCB_Options::all();
		if ( empty( $options['debug_mode'] ) && str_starts_with( $type, 'debug.' ) ) {
			return;
		}
		$wpdb->insert(
			$wpdb->prefix . 'jcb_events',
			array(
				'event_type' => sanitize_key( $type ),
				'event_data' => wp_json_encode( $data ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * Store a conversation message when enabled.
	 *
	 * @param string $session_id Session id.
	 * @param string $role Role.
	 * @param string $content Message.
	 * @param array  $meta Extra data.
	 */
	public static function message( string $session_id, string $role, string $content, array $meta = array() ): void {
		$options = JCB_Options::all();
		if ( empty( $options['log_conversations'] ) ) {
			return;
		}

		global $wpdb;
		$conversation_id = self::conversation_id( $session_id, $meta );
		if ( ! $conversation_id ) {
			return;
		}

		if ( ! empty( $options['redact_personal_data'] ) ) {
			$content = JCB_Sanitizer::redact( $content );
		}

		$wpdb->insert(
			$wpdb->prefix . 'jcb_messages',
			array(
				'conversation_id' => $conversation_id,
				'role'            => sanitize_key( $role ),
				'content'         => $content,
				'tokens'          => isset( $meta['tokens'] ) ? absint( $meta['tokens'] ) : null,
				'latency_ms'      => isset( $meta['latency_ms'] ) ? absint( $meta['latency_ms'] ) : null,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * Get or create conversation id.
	 *
	 * @param string $session_id Session id.
	 * @param array  $meta Extra data.
	 */
	private static function conversation_id( string $session_id, array $meta ): int {
		global $wpdb;
		$hash = hash_hmac( 'sha256', $session_id, wp_salt( 'nonce' ) );
		$table = $wpdb->prefix . 'jcb_conversations';
		$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE session_hash = %s", $hash ) );

		if ( $id > 0 ) {
			$wpdb->update(
				$table,
				array( 'last_seen' => current_time( 'mysql', true ) ),
				array( 'id' => $id ),
				array( '%s' ),
				array( '%d' )
			);
			return $id;
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? JCB_Sanitizer::text( (string) $_SERVER['HTTP_USER_AGENT'], 255 ) : '';

		$wpdb->insert(
			$table,
			array(
				'session_hash' => $hash,
				'started_at'   => current_time( 'mysql', true ),
				'last_seen'    => current_time( 'mysql', true ),
				'page_url'     => isset( $meta['page_url'] ) ? esc_url_raw( $meta['page_url'] ) : '',
				'ip_hash'      => $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '',
				'user_agent'   => $ua,
				'status'       => 'open',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}
}
