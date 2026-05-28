<?php
/**
 * Short lived chat session context.
 *
 * @package AIKnowledgeChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIKB_Session {

	/**
	 * Get recent transient messages for a visitor session.
	 *
	 * @param string $session_id Browser session id.
	 * @param int    $limit Max messages.
	 */
	public static function recent( string $session_id, int $limit = 8 ): array {
		$options = AIKB_Options::all();
		if ( empty( $options['session_context_enabled'] ) ) {
			return array();
		}

		$key      = self::key( $session_id );
		$messages = get_transient( $key );
		if ( ! is_array( $messages ) ) {
			return array();
		}

		$limit = AIKB_Sanitizer::int_range( $limit, 0, 20 );
		if ( 0 === $limit ) {
			return array();
		}

		$messages = array_slice( $messages, -1 * $limit );
		$out      = array();
		foreach ( $messages as $message ) {
			$role = isset( $message['role'] ) && 'assistant' === $message['role'] ? 'assistant' : 'user';
			$text = isset( $message['content'] ) ? AIKB_Sanitizer::textarea( (string) $message['content'], 4000 ) : '';
			if ( '' !== $text ) {
				$out[] = array(
					'role'    => $role,
					'content' => $text,
				);
			}
		}
		return $out;
	}

	/**
	 * Append a message to session context.
	 *
	 * @param string $session_id Browser session id.
	 * @param string $role Role.
	 * @param string $content Message text.
	 */
	public static function append( string $session_id, string $role, string $content ): void {
		$options = AIKB_Options::all();
		if ( empty( $options['session_context_enabled'] ) ) {
			return;
		}

		$limit = AIKB_Sanitizer::int_range( $options['max_history_messages'] ?? 8, 0, 20 );
		if ( 0 === $limit ) {
			return;
		}

		$key      = self::key( $session_id );
		$messages = get_transient( $key );
		if ( ! is_array( $messages ) ) {
			$messages = array();
		}

		$messages[] = array(
			'role'       => 'assistant' === $role ? 'assistant' : 'user',
			'content'    => AIKB_Sanitizer::textarea( $content, 4000 ),
			'created_at' => time(),
		);
		$messages = array_slice( $messages, -1 * $limit );

		$ttl_minutes = AIKB_Sanitizer::int_range( $options['session_ttl_minutes'] ?? 60, 5, 1440 );
		set_transient( $key, $messages, $ttl_minutes * MINUTE_IN_SECONDS );
	}

	/**
	 * Build private transient key.
	 *
	 * @param string $session_id Browser session id.
	 */
	private static function key( string $session_id ): string {
		$session_id = AIKB_Sanitizer::text( $session_id, 160 );
		return 'aikb_ctx_' . hash_hmac( 'sha256', $session_id, wp_salt( 'nonce' ) );
	}
}
