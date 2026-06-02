<?php
/**
 * Security checks for public chat requests.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Security_Manager {

	/**
	 * Review an incoming public chat message.
	 *
	 * @param string $message User message.
	 * @param string $session Session token.
	 * @param array  $options Plugin options.
	 */
	public static function review( string $message, string $session, array $options = array() ) {
		$options = $options ? $options : JCB_Options::all();

		if ( empty( $options['security_enabled'] ) ) {
			return self::result( 'allowed', 0, array(), __( 'Security system is disabled.', 'jeroens-chatbox' ) );
		}

		if ( self::is_whitelisted( $session, $options ) ) {
			return self::result( 'allowed', 0, array(), __( 'Visitor is whitelisted.', 'jeroens-chatbox' ) );
		}

		$ip_block = self::check_ip_blocklist( $options );
		if ( is_wp_error( $ip_block ) ) {
			self::log_security_event( 'security_blocked', array( 'reason' => 'ip_blocklist' ) );
			return $ip_block;
		}

		$length = self::check_message_length( $message, $options );
		if ( is_wp_error( $length ) ) {
			self::log_security_event( 'security_blocked', array( 'reason' => 'message_length' ) );
			return $length;
		}

		$rate = self::rate_limit( $session, $options );
		if ( is_wp_error( $rate ) ) {
			self::log_security_event( 'security_blocked', array( 'reason' => 'rate_limit' ) );
			return $rate;
		}

		$blocked_words = self::check_blocked_words( $message, $options );
		if ( is_wp_error( $blocked_words ) ) {
			self::log_security_event( 'security_blocked', array( 'reason' => 'blocked_words' ) );
			return $blocked_words;
		}

		$warnings = array();
		$warning_message = '';
		if ( is_array( $blocked_words ) && ! empty( $blocked_words['matched'] ) ) {
			$warnings[] = array(
				'name'     => 'blocked_words_warning',
				'label'    => __( 'Blocked word warning', 'jeroens-chatbox' ),
				'severity' => 1,
			);
			$warning_message = self::message_option( $options, 'blocked_words_message', __( 'Your message contains content that is not allowed. Please rephrase your question.', 'jeroens-chatbox' ) );
			self::log_security_event( 'security_warning', array( 'reason' => 'blocked_words', 'matched_count' => count( $blocked_words['matched'] ) ) );
		}

		$flag = self::auto_flag( $message, $session, $options, false );
		$flags = array_merge( $warnings, $flag['flags'] );
		$score = (int) $flag['score'] + count( $warnings );

		$threshold = JCB_Sanitizer::int_range( $options['auto_flag_threshold'] ?? 10, 1, 100 );
		if ( ! empty( $options['auto_flag_enabled'] ) && $score >= $threshold ) {
			$action = self::clean_action( (string) ( $options['auto_flag_action'] ?? 'flag' ), array( 'flag', 'block' ), 'flag' );
			self::log_security_event(
				'block' === $action ? 'security_blocked' : 'security_flagged',
				array(
					'reason' => 'auto_flag',
					'score'  => $score,
					'flags'  => wp_list_pluck( $flags, 'name' ),
				)
			);
			if ( 'block' === $action ) {
				return new WP_Error(
					'jcb_security_blocked',
					self::message_option( $options, 'auto_flag_block_message', __( 'Your message was flagged by the security system. Please rephrase your question.', 'jeroens-chatbox' ) ),
					array( 'status' => 403 )
				);
			}
		}

		return self::result( $score >= $threshold ? 'flagged' : ( $warning_message ? 'warning' : 'allowed' ), $score, $flags, $warning_message );
	}

	/**
	 * Test security settings without mutating rate counters.
	 *
	 * @param string $message Test message.
	 * @param array  $options Plugin options.
	 */
	public static function test( string $message, array $options = array() ): array {
		$options = $options ? $options : JCB_Options::all();
		$flags   = array();
		$score   = 0;
		$action  = 'allowed';
		$notice  = __( 'The message would be allowed.', 'jeroens-chatbox' );

		if ( empty( $options['security_enabled'] ) ) {
			return self::result( 'allowed', 0, array(), __( 'Security system is disabled.', 'jeroens-chatbox' ) );
		}

		$length = self::check_message_length( $message, $options );
		if ( is_wp_error( $length ) ) {
			return self::result( 'blocked', 0, array( self::simple_flag( 'message_length', __( 'Message length', 'jeroens-chatbox' ), 10 ) ), $length->get_error_message() );
		}

		$blocked_words = self::check_blocked_words( $message, $options );
		if ( is_wp_error( $blocked_words ) ) {
			return self::result( 'blocked', 0, array( self::simple_flag( 'blocked_words', __( 'Blocked words', 'jeroens-chatbox' ), 10 ) ), $blocked_words->get_error_message() );
		}
		if ( is_array( $blocked_words ) && ! empty( $blocked_words['matched'] ) ) {
			$flags[] = self::simple_flag( 'blocked_words_warning', __( 'Blocked words warning', 'jeroens-chatbox' ), 1 );
			$score++;
			$action = 'warning';
			$notice = self::message_option( $options, 'blocked_words_message', __( 'Your message contains content that is not allowed. Please rephrase your question.', 'jeroens-chatbox' ) );
		}

		$flag  = self::auto_flag( $message, 'security_test', $options, true );
		$flags = array_merge( $flags, $flag['flags'] );
		$score += (int) $flag['score'];

		$threshold = JCB_Sanitizer::int_range( $options['auto_flag_threshold'] ?? 10, 1, 100 );
		if ( ! empty( $options['auto_flag_enabled'] ) && $score >= $threshold ) {
			$configured = self::clean_action( (string) ( $options['auto_flag_action'] ?? 'flag' ), array( 'flag', 'block' ), 'flag' );
			$action     = 'block' === $configured ? 'blocked' : 'flagged';
			$notice     = 'block' === $configured ? self::message_option( $options, 'auto_flag_block_message', __( 'Your message was flagged by the security system. Please rephrase your question.', 'jeroens-chatbox' ) ) : __( 'The message would be flagged for review.', 'jeroens-chatbox' );
		}

		return self::result( $action, $score, $flags, $notice );
	}

	/**
	 * Rate limit by session and IP.
	 *
	 * @param string $session Session token.
	 * @param array  $options Options.
	 */
	private static function rate_limit( string $session, array $options ) {
		if ( empty( $options['rate_limit_enabled'] ) ) {
			return true;
		}

		$cooldown = JCB_Sanitizer::int_range( $options['rate_limit_cooldown_seconds'] ?? 30, 0, 3600 );
		$message  = self::message_option( $options, 'rate_limit_message', __( 'You are sending messages too quickly. Please wait a moment before trying again.', 'jeroens-chatbox' ) );
		$ip       = self::client_ip();
		$items    = array(
			array(
				'key'    => 'jcb_rl_user_' . md5( $session ),
				'limit'  => JCB_Sanitizer::int_range( $options['rate_limit_user_max'] ?? 30, 1, 500 ),
				'window' => JCB_Sanitizer::int_range( $options['rate_limit_user_window'] ?? 60, 1, 86400 ),
			),
			array(
				'key'    => 'jcb_rl_ip_' . md5( $ip ),
				'limit'  => JCB_Sanitizer::int_range( $options['rate_limit_ip_max'] ?? 60, 1, 1000 ),
				'window' => JCB_Sanitizer::int_range( $options['rate_limit_ip_window'] ?? 60, 1, 86400 ),
			),
		);

		foreach ( $items as $item ) {
			$cooldown_key = $item['key'] . '_cooldown';
			if ( get_transient( $cooldown_key ) ) {
				return new WP_Error( 'jcb_rate_limited', $message, array( 'status' => 429 ) );
			}
			$count = (int) get_transient( $item['key'] );
			if ( $count >= $item['limit'] ) {
				if ( $cooldown > 0 ) {
					set_transient( $cooldown_key, 1, $cooldown );
				}
				return new WP_Error( 'jcb_rate_limited', $message, array( 'status' => 429 ) );
			}
		}

		foreach ( $items as $item ) {
			$count = (int) get_transient( $item['key'] );
			set_transient( $item['key'], $count + 1, $item['window'] );
		}

		return true;
	}

	/**
	 * Check max message length.
	 *
	 * @param string $message Message.
	 * @param array  $options Options.
	 */
	private static function check_message_length( string $message, array $options ) {
		if ( empty( $options['message_length_enabled'] ) ) {
			return true;
		}
		$limit = JCB_Sanitizer::int_range( $options['message_max_chars'] ?? 2000, 50, 20000 );
		if ( strlen( $message ) > $limit ) {
			$text = self::message_option( $options, 'message_length_message', __( 'Your message is too long. Please keep it under {limit} characters.', 'jeroens-chatbox' ) );
			$text = str_replace( '{limit}', (string) $limit, $text );
			return new WP_Error( 'jcb_message_too_long', $text, array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Check blocked word rules.
	 *
	 * @param string $message Message.
	 * @param array  $options Options.
	 */
	private static function check_blocked_words( string $message, array $options ) {
		if ( empty( $options['blocked_words_enabled'] ) ) {
			return array( 'matched' => array() );
		}

		$patterns = (string) ( $options['blocked_words_list'] ?? '' );
		if ( ! empty( $options['blocked_words_use_default'] ) ) {
			$patterns = trim( $patterns . "\n" . self::default_profanity_patterns() );
		}

		$matched = self::match_patterns( $message, $patterns );
		if ( empty( $matched ) ) {
			return array( 'matched' => array() );
		}

		$action = self::clean_action( (string) ( $options['blocked_words_action'] ?? 'warn' ), array( 'warn', 'block' ), 'warn' );
		if ( 'block' === $action ) {
			return new WP_Error(
				'jcb_blocked_words',
				self::message_option( $options, 'blocked_words_message', __( 'Your message contains content that is not allowed. Please rephrase your question.', 'jeroens-chatbox' ) ),
				array( 'status' => 403 )
			);
		}

		return array( 'matched' => $matched );
	}

	/**
	 * Check IP blocklist.
	 *
	 * @param array $options Options.
	 */
	private static function check_ip_blocklist( array $options ) {
		if ( empty( $options['ip_blocklist_enabled'] ) ) {
			return true;
		}

		$ip = self::client_ip();
		if ( self::ip_matches_list( $ip, (string) ( $options['ip_blocklist'] ?? '' ) ) ) {
			return new WP_Error(
				'jcb_ip_blocked',
				self::message_option( $options, 'ip_block_message', __( 'Access denied. Please contact support if you believe this is an error.', 'jeroens-chatbox' ) ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Score auto flag rules.
	 *
	 * @param string $message Message.
	 * @param string $session Session token.
	 * @param array  $options Options.
	 * @param bool   $test_mode Test mode.
	 */
	private static function auto_flag( string $message, string $session, array $options, bool $test_mode ): array {
		$flags = array();
		$score = 0;
		if ( empty( $options['auto_flag_enabled'] ) ) {
			return array( 'score' => 0, 'flags' => array() );
		}

		if ( ! empty( $options['detect_jailbreak_enabled'] ) ) {
			$patterns = (string) ( $options['jailbreak_patterns'] ?? '' );
			if ( ! empty( $options['jailbreak_multilingual_enabled'] ) ) {
				$patterns = trim( $patterns . "\n" . self::default_multilingual_jailbreak_patterns() );
			}
			$matches = self::match_patterns( $message, $patterns );
			if ( ! empty( $matches ) ) {
				$severity = self::severity( $options['jailbreak_severity'] ?? 10 );
				$score += $severity;
				$flags[] = self::simple_flag( 'jailbreak', __( 'Jailbreak detection', 'jeroens-chatbox' ), $severity, $matches );
			}
		}

		if ( ! empty( $options['detect_abuse_enabled'] ) ) {
			$abuse_matches = array();
			if ( self::special_character_ratio( $message ) > 0.3 ) {
				$abuse_matches[] = 'special_characters';
			}
			if ( ! empty( $options['code_injection_enabled'] ) && self::looks_like_code_injection( $message ) ) {
				$abuse_matches[] = 'code_injection';
			}
			if ( ! empty( $abuse_matches ) ) {
				$severity = self::severity( $options['abuse_severity'] ?? 5 );
				$score += $severity;
				$flags[] = self::simple_flag( 'abuse', __( 'Abuse detection', 'jeroens-chatbox' ), $severity, $abuse_matches );
			}
		}

		if ( ! empty( $options['detect_content_enabled'] ) ) {
			$matches = self::match_patterns( $message, (string) ( $options['content_patterns'] ?? '' ) );
			if ( ! empty( $matches ) ) {
				$severity = self::severity( $options['content_severity'] ?? 3 );
				$score += $severity;
				$flags[] = self::simple_flag( 'content', __( 'Content flags', 'jeroens-chatbox' ), $severity, $matches );
			}
		}

		if ( ! empty( $options['detect_behavior_enabled'] ) && ! $test_mode ) {
			$behavior = self::behavior_flags( $message, $session, $options );
			if ( ! empty( $behavior ) ) {
				$severity = self::severity( $options['behavior_severity'] ?? 3 );
				$score += $severity;
				$flags[] = self::simple_flag( 'behavior', __( 'Behavioral analysis', 'jeroens-chatbox' ), $severity, $behavior );
			}
		}

		return array( 'score' => $score, 'flags' => $flags );
	}

	/**
	 * Behavior checks.
	 *
	 * @param string $message Message.
	 * @param string $session Session token.
	 * @param array  $options Options.
	 */
	private static function behavior_flags( string $message, string $session, array $options ): array {
		$flags  = array();
		$hash   = md5( $session );
		$window = JCB_Sanitizer::int_range( $options['behavior_time_window'] ?? 10, 1, 3600 );
		$rapid  = JCB_Sanitizer::int_range( $options['behavior_rapid_messages'] ?? 5, 2, 100 );
		$key    = 'jcb_bh_count_' . $hash;
		$count  = (int) get_transient( $key );
		$count++;
		set_transient( $key, $count, $window );
		if ( $count >= $rapid ) {
			$flags[] = 'rapid_messages';
		}

		$msg_hash      = md5( strtolower( trim( $message ) ) );
		$repeat_key    = 'jcb_bh_repeat_' . $hash;
		$repeat_record = get_transient( $repeat_key );
		$repeat_record = is_array( $repeat_record ) ? $repeat_record : array( 'hash' => '', 'count' => 0 );
		if ( $repeat_record['hash'] === $msg_hash ) {
			$repeat_record['count'] = absint( $repeat_record['count'] ) + 1;
		} else {
			$repeat_record = array( 'hash' => $msg_hash, 'count' => 1 );
		}
		set_transient( $repeat_key, $repeat_record, $window );
		$max_repeat = JCB_Sanitizer::int_range( $options['behavior_repeated_message_max'] ?? 3, 2, 50 );
		if ( $repeat_record['count'] >= $max_repeat ) {
			$flags[] = 'repeated_message';
		}

		return $flags;
	}

	/**
	 * Match a message against newline patterns.
	 *
	 * @param string $message Message.
	 * @param string $raw_patterns Patterns.
	 */
	private static function match_patterns( string $message, string $raw_patterns ): array {
		$patterns = preg_split( '/\r\n|\r|\n/', $raw_patterns );
		$matched  = array();

		foreach ( $patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}

			if ( self::line_is_regex( $pattern ) ) {
				$result = @preg_match( $pattern, $message );
				if ( 1 === $result ) {
					$matched[] = $pattern;
				}
				continue;
			}

			$regex = '/(^|\b)' . str_replace( '\*', '.*?', preg_quote( $pattern, '/' ) ) . '(\b|$)/iu';
			if ( str_contains( $pattern, '*' ) ) {
				if ( @preg_match( $regex, $message ) ) {
					$matched[] = $pattern;
				}
			} elseif ( false !== stripos( $message, $pattern ) ) {
				$matched[] = $pattern;
			}
		}

		return array_values( array_unique( $matched ) );
	}

	/**
	 * Determine if a line is a regex.
	 *
	 * @param string $pattern Pattern.
	 */
	private static function line_is_regex( string $pattern ): bool {
		return strlen( $pattern ) > 2 && '/' === $pattern[0] && '/' === substr( $pattern, -1 );
	}

	/**
	 * Does message look like injection.
	 *
	 * @param string $message Message.
	 */
	private static function looks_like_code_injection( string $message ): bool {
		$patterns = array(
			'/<\s*script\b/i',
			'/\bselect\b.+\bfrom\b/i',
			'/\bunion\b.+\bselect\b/i',
			'/\bdrop\s+table\b/i',
			'/\beval\s*\(/i',
			'/\bexec\s*\(/i',
			'/javascript\s*:/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Special character ratio.
	 *
	 * @param string $message Message.
	 */
	private static function special_character_ratio( string $message ): float {
		$length = strlen( $message );
		if ( $length < 20 ) {
			return 0;
		}
		$specials = preg_match_all( '/[^\p{L}\p{N}\s.,?!:;\'"()\-]/u', $message );
		return $specials ? $specials / $length : 0;
	}

	/**
	 * Check whitelist.
	 *
	 * @param string $session Session token.
	 * @param array  $options Options.
	 */
	private static function is_whitelisted( string $session, array $options ): bool {
		$token = trim( $session );
		if ( '' !== $token ) {
			$tokens = self::lines( (string) ( $options['whitelist_user_tokens'] ?? '' ) );
			if ( in_array( $token, $tokens, true ) ) {
				return true;
			}
		}

		return self::ip_matches_list( self::client_ip(), (string) ( $options['whitelist_ips'] ?? '' ) );
	}

	/**
	 * Match IP list.
	 *
	 * @param string $ip IP.
	 * @param string $list List.
	 */
	private static function ip_matches_list( string $ip, string $list ): bool {
		foreach ( self::lines( $list ) as $entry ) {
			if ( $ip === $entry ) {
				return true;
			}
			if ( str_contains( $entry, '/' ) && self::ipv4_in_cidr( $ip, $entry ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * IPv4 CIDR check.
	 *
	 * @param string $ip IP.
	 * @param string $cidr CIDR.
	 */
	private static function ipv4_in_cidr( string $ip, string $cidr ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}
		$parts = explode( '/', $cidr, 2 );
		if ( 2 !== count( $parts ) || ! filter_var( $parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}
		$bits = absint( $parts[1] );
		if ( $bits < 0 || $bits > 32 ) {
			return false;
		}
		$ip_long   = ip2long( $ip );
		$base_long = ip2long( $parts[0] );
		$mask      = -1 << ( 32 - $bits );
		return ( $ip_long & $mask ) === ( $base_long & $mask );
	}

	/**
	 * Client IP.
	 */
	private static function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	}

	/**
	 * Lines helper.
	 *
	 * @param string $text Text.
	 */
	private static function lines( string $text ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		$lines = array_map( 'trim', $lines );
		return array_values( array_filter( $lines, static fn( $line ) => '' !== $line ) );
	}

	/**
	 * Clean severity points.
	 *
	 * @param mixed $value Value.
	 */
	private static function severity( $value ): int {
		$value = absint( $value );
		return in_array( $value, array( 1, 3, 5, 10 ), true ) ? $value : 3;
	}

	/**
	 * Clean action.
	 *
	 * @param string $value Value.
	 * @param array  $allowed Allowed.
	 * @param string $fallback Fallback.
	 */
	private static function clean_action( string $value, array $allowed, string $fallback ): string {
		$value = sanitize_key( $value );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Get clean message option.
	 *
	 * @param array  $options Options.
	 * @param string $key Key.
	 * @param string $fallback Fallback.
	 */
	private static function message_option( array $options, string $key, string $fallback ): string {
		$value = trim( (string) ( $options[ $key ] ?? '' ) );
		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Build simple flag.
	 *
	 * @param string $name Name.
	 * @param string $label Label.
	 * @param int    $severity Severity.
	 * @param array  $matched Matched.
	 */
	private static function simple_flag( string $name, string $label, int $severity, array $matched = array() ): array {
		return array(
			'name'     => $name,
			'label'    => $label,
			'severity' => $severity,
			'matched'  => $matched,
		);
	}

	/**
	 * Build result.
	 *
	 * @param string $action Action.
	 * @param int    $score Score.
	 * @param array  $flags Flags.
	 * @param string $message Message.
	 */
	private static function result( string $action, int $score, array $flags, string $message ): array {
		return array(
			'action'  => $action,
			'score'   => $score,
			'flags'   => $flags,
			'message' => $message,
		);
	}

	/**
	 * Built-in offensive and profane word patterns (multilingual).
	 *
	 * These are checked in addition to the site owner's custom list when the
	 * "use default list" option is enabled. Wildcards catch common variants.
	 */
	public static function default_profanity_patterns(): string {
		$patterns = array(
			// English.
			'fuck*', 'sh*t', 'bullsh*t', 'bitch*', 'bastard*', 'asshole*', 'a**hole',
			'dick*', 'douche*', 'slut*', 'whore*', 'cunt*', 'wank*', 'twat*',
			'motherf*', 'mf*er', 'cock*', 'jerk off', 'jack off', 'retard*',
			// Dutch.
			'kut*', 'klootzak*', 'lul*', 'hoer*', 'slet*', 'kanker*', 'tering*',
			'tyfus*', 'godver*', 'gvd', 'verdomme', 'klere*', 'mongool*', 'debiel*',
			'sukkel*', 'eikel*', 'flikker*',
			// German.
			'scheiss*', 'scheiß*', 'arschloch*', 'fotze*', 'hurensohn*', 'wichser*',
			'schlampe*', 'fick*', 'verdammt',
			// French.
			'merde*', 'putain*', 'connard*', 'connasse*', 'salope*', 'enculé*',
			'encule*', 'pute*', 'bordel',
			// Spanish.
			'mierda*', 'puta*', 'puto*', 'cabron*', 'cabrón*', 'gilipollas*',
			'coño*', 'joder', 'pendejo*',
		);
		return implode( "\n", $patterns );
	}

	/**
	 * Built-in jailbreak and prompt-injection patterns for languages other than English.
	 *
	 * The English patterns ship in the editable list; this adds non-English coverage
	 * (and a few extra English variants) so detection works in any language.
	 */
	public static function default_multilingual_jailbreak_patterns(): string {
		$patterns = array(
			// Extra English variants.
			'ignore previous prompt', 'override your instructions', 'you are now',
			'act as if', 'no restrictions', 'without any restrictions', 'do anything now',
			'repeat your prompt', 'print your instructions', 'output your system prompt',
			// Dutch.
			'negeer alle voorgaande', 'negeer je instructies', 'negeer bovenstaande',
			'vergeet je instructies', 'vergeet alle voorgaande', 'doe alsof je',
			'gedraag je als', 'doe alsof', 'toon je instructies', 'laat je instructies zien',
			'wat zijn je instructies', 'wat is je systeemprompt', 'systeemprompt',
			'omzeil de regels', 'omzeil je beveiliging', 'ontwikkelaarsmodus', 'jailbreak',
			// German.
			'ignoriere alle vorherigen', 'ignoriere deine anweisungen', 'ignoriere die obigen',
			'vergiss deine anweisungen', 'vergiss alle vorherigen', 'tu so als ob',
			'verhalte dich wie', 'zeig deine anweisungen', 'zeige deine anweisungen',
			'was sind deine anweisungen', 'system-prompt', 'systemprompt',
			'umgehe die regeln', 'umgehe die sicherheit', 'entwicklermodus',
			// French.
			'ignore les instructions précédentes', 'ignore toutes les instructions',
			'ignorez les instructions', 'oublie tes instructions', 'oublie toutes les',
			'fais comme si', 'comporte-toi comme', 'montre tes instructions',
			'affiche tes instructions', 'quelles sont tes instructions', 'invite système',
			'contourne les règles', 'contourne la sécurité', 'mode développeur',
			// Spanish.
			'ignora las instrucciones anteriores', 'ignora todas las instrucciones',
			'olvida tus instrucciones', 'olvida todo lo anterior', 'actúa como si',
			'compórtate como', 'muestra tus instrucciones', 'cuáles son tus instrucciones',
			'mensaje del sistema', 'omite las reglas', 'modo desarrollador',
			// Italian.
			'ignora le istruzioni precedenti', 'ignora tutte le istruzioni',
			'dimentica le tue istruzioni', 'fingi di essere', 'comportati come',
			'mostra le tue istruzioni', 'prompt di sistema', 'modalità sviluppatore',
			// Portuguese.
			'ignore as instruções anteriores', 'ignore todas as instruções',
			'esqueça suas instruções', 'finja que', 'comporte-se como',
			'mostre suas instruções', 'prompt do sistema', 'modo desenvolvedor',
		);
		return implode( "\n", $patterns );
	}

	/**
	 * Log security event.
	 *
	 * @param string $type Type.
	 * @param array  $data Data.
	 */
	private static function log_security_event( string $type, array $data ): void {
		JCB_Logger::event( $type, $data );
	}
}
