<?php
/**
 * Options storage.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Options {

	/**
	 * Option key.
	 */
	const KEY = 'jeroens_chatbox_options';

	/**
	 * Legacy option key used before the public rename.
	 */
	const LEGACY_KEY = 'aikb_options';

	/**
	 * Register the option with the WordPress Settings API.
	 */
	public static function register_settings(): void {
		register_setting(
			'jeroens_chatbox_settings',
			self::KEY,
			array(
				'type'              => 'array',
				'default'           => self::defaults(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_registered_option' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize native Settings API saves without writing inside the callback.
	 *
	 * @param mixed $input Incoming option value.
	 */
	public static function sanitize_registered_option( $input ): array {
		if ( ! is_array( $input ) ) {
			return self::all();
		}
		return self::sanitize_for_storage( $input, self::all() );
	}

	/**
	 * Get all options merged with defaults.
	 */
	public static function all(): array {
		$options = get_option( self::KEY, null );

		if ( null === $options ) {
			$legacy = get_option( self::LEGACY_KEY, array() );
			$options = is_array( $legacy ) ? $legacy : array();
		}

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return array_merge( self::defaults(), $options );
	}

	/**
	 * Run one-time data migrations when the stored option is from an older version.
	 *
	 * Hooked early so both the admin and the front end benefit. It only writes once
	 * per version bump (guarded by the stored 'version'), so it is cheap to call on
	 * every request.
	 */
	public static function maybe_upgrade(): void {
		$stored = get_option( self::KEY, null );

		// Fresh installs (no stored option yet) are handled by the activator and by
		// defaults(), which already ship the new protections turned on.
		if ( ! is_array( $stored ) ) {
			return;
		}

		$version = isset( $stored['version'] ) ? (string) $stored['version'] : '0';
		if ( version_compare( $version, JCB_VERSION, '>=' ) ) {
			return;
		}

		// --- 0.9.0 step ------------------------------------------------------
		// The site owner asked for offensive-word blocking and multilingual
		// jailbreak detection to be on by default. Sites upgrading from 0.8.0
		// kept their old stored values (for example blocked_words_enabled was
		// false), which silently prevented the new protections from running.
		if ( version_compare( $version, '0.9.0', '<' ) ) {
			$stored['security_enabled']               = true;
			$stored['blocked_words_enabled']          = true;
			$stored['blocked_words_use_default']      = true;
			if ( empty( $stored['blocked_words_action'] ) || 'warn' === $stored['blocked_words_action'] ) {
				$stored['blocked_words_action'] = 'block';
			}
			$stored['auto_flag_enabled']              = true;
			$stored['detect_jailbreak_enabled']       = true;
			$stored['jailbreak_multilingual_enabled'] = true;

			// Seed the website-representative preset, but only when the Instructions
			// field has not been customised (empty or a shipped default).
			$current = trim( (string) ( $stored['instructions'] ?? '' ) );
			if ( '' === $current || self::is_default_instructions( $current ) ) {
				self::apply_representative_preset( $stored );
			}
		}

		// --- 0.9.2 step ------------------------------------------------------
		// The representative preset became much more detailed. Refresh it for sites
		// that still have an auto-seeded (un-edited) copy of the older, shorter text,
		// or an empty/default field. Hand-edited instructions are left untouched.
		if ( version_compare( $version, '0.9.2', '<' ) ) {
			$current = trim( (string) ( $stored['instructions'] ?? '' ) );
			if ( '' === $current
				|| self::is_default_instructions( $current )
				|| self::matches_legacy_representative( $current, $stored ) ) {
				self::apply_representative_preset( $stored );
			}
		}

		$stored['version'] = JCB_VERSION;
		update_option( self::KEY, $stored, false );
	}

	/**
	 * Fill the Instructions field with the resolved website-representative preset.
	 *
	 * @param array $stored Stored options, passed by reference.
	 */
	private static function apply_representative_preset( array &$stored ): void {
		$language = JCB_Language::normalize( (string) ( $stored['plugin_language'] ?? 'en' ) );
		$presets  = JCB_Presets::all( $language, array_merge( self::defaults(), $stored ) );
		foreach ( $presets as $preset ) {
			if ( 'representative' === $preset['id'] && ! empty( $preset['instructions'] ) ) {
				$stored['instructions']       = $preset['instructions'];
				$stored['instruction_preset'] = 'representative';
				return;
			}
		}
	}

	/**
	 * Whether the current instructions are a verbatim, un-edited copy of the older
	 * auto-seeded representative preset (English or Dutch), resolved with current
	 * tokens. Only a verbatim match returns true, so hand-edited text is never lost.
	 *
	 * @param string $current Trimmed current instructions.
	 * @param array  $stored  Stored options.
	 */
	private static function matches_legacy_representative( string $current, array $stored ): bool {
		if ( ! method_exists( 'JCB_Presets', 'legacy_representative' ) ) {
			return false;
		}
		$options = array_merge( self::defaults(), $stored );
		foreach ( array( 'en', 'nl' ) as $lang ) {
			if ( $current === trim( JCB_Presets::legacy_representative( $lang, $options ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the given instructions text matches a shipped default (any language),
	 * meaning it has not been customised by the site owner.
	 *
	 * @param string $instructions Trimmed instructions text.
	 */
	private static function is_default_instructions( string $instructions ): bool {
		foreach ( array( 'en', 'nl', 'de', 'fr' ) as $lang ) {
			if ( $instructions === trim( JCB_Language::text( 'instructions', $lang ) ) ) {
				return true;
			}
		}
		// The original 0.8.0 activator default (in case the language table changes).
		$legacy_default = 'Answer questions using the selected website knowledge base. Be clear, helpful and honest. If the answer is not in the knowledge base, say that you do not know based on the available site content.';
		return $instructions === $legacy_default;
	}

	/**
	 * Get public options for front-end.
	 */
	public static function public_config(): array {
		$options = self::all();
		$language = JCB_Language::normalize( (string) ( $options['plugin_language'] ?? 'en' ) );
		return array(
			'language'       => $language,
			'strings'        => JCB_Language::front_end_strings( $language ),
			'assistantName'  => $options['assistant_name'],
			'welcomeMessage' => $options['welcome_message'],
			'placeholder'    => $options['placeholder'],
			'accentColor'              => $options['accent_color'],
			'fontColor'                => $options['font_color'],
			'backgroundColor'          => $options['background_color'],
			'userBubbleColor'          => $options['user_bubble_color'],
			'userBubbleTextColor'      => $options['user_bubble_text_color'],
			'assistantBubbleColor'     => $options['assistant_bubble_color'],
			'assistantBubbleTextColor' => $options['assistant_bubble_text_color'],
			'bubbleStyle'              => $options['bubble_style'],
			'designTheme'              => $options['design_theme'],
			'avatarUrl'                => esc_url_raw( (string) $options['avatar_url'] ),
			'avatarShape'              => $options['avatar_shape'],
			'showAvatarInHeader'       => (bool) $options['show_avatar_in_header'],
			'showAvatarOnMessages'     => (bool) $options['show_avatar_on_messages'],
			'launcherStyle'            => $options['launcher_style'],
			'launcherIcon'             => $options['launcher_icon'],
			'enableMarkdown'           => (bool) $options['enable_markdown'],
			'quickReplies'             => self::quick_replies_list( (string) $options['quick_replies'] ),
			'position'                 => $options['launcher_position'],
			'launcherLabel'  => $options['launcher_label'],
			'startOpen'      => (bool) $options['start_open'],
			'zIndex'         => (int) $options['z_index'],
			'apiUrl'         => esc_url_raw( rest_url( JCB_REST_NAMESPACE . '/chat' ) ),
			'feedbackUrl'    => esc_url_raw( rest_url( JCB_REST_NAMESPACE . '/feedback' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
		);
	}

	/**
	 * Default values.
	 */
	public static function defaults(): array {
		return array(
			'version'                  => JCB_VERSION,
			'plugin_language'          => 'en',
			'assistant_name'           => "Jeroen's Chatbox",
			'model'                    => 'gpt-4.1-mini',
			'instructions'             => JCB_Language::text( 'instructions', 'en' ),
			'welcome_message'          => JCB_Language::text( 'welcome_message', 'en' ),
			'placeholder'              => JCB_Language::text( 'placeholder', 'en' ),
			'accent_color'             => '#6f5bd6',
			'font_color'               => '#111827',
			'background_color'         => '#f8fafc',
			'user_bubble_color'        => '#6f5bd6',
			'user_bubble_text_color'   => '#ffffff',
			'assistant_bubble_color'   => '#ffffff',
			'assistant_bubble_text_color' => '#111827',
			'bubble_style'             => 'soft',
			'design_theme'             => 'custom',
			'avatar_url'               => '',
			'avatar_shape'             => 'circle',
			'show_avatar_in_header'    => true,
			'show_avatar_on_messages'  => true,
			'launcher_style'           => 'label',
			'launcher_icon'            => 'chat',
			'enable_markdown'          => true,
			'quick_replies'            => '',
			'contact_email'            => '',
			'contact_phone'            => '',
			'contact_address'          => '',
			'instruction_preset'       => 'custom',
			'launcher_position'        => 'right',
			'launcher_label'           => JCB_Language::text( 'launcher_label', 'en' ),
			'frontend_enabled'         => true,
			'visibility_mode'          => 'everyone',
			'visibility_user_ids'      => '',
			'auto_embed'               => false,
			'start_open'               => false,
			'show_on_home'             => true,
			'show_on_pages'            => true,
			'show_on_posts'            => true,
			'show_on_archives'         => true,
			'show_on_mobile'           => true,
			'excluded_page_ids'        => '',
			'excluded_url_paths'       => '',
			'z_index'                  => 99999,
			'enable_file_search'       => true,
			'include_sources'          => true,
			'max_file_results'         => 6,
			'max_output_tokens'        => 700,
			'session_context_enabled'  => true,
			'max_history_messages'     => 8,
			'session_ttl_minutes'      => 90,
			'rate_limit_per_minute'    => 12,
			'rate_limit_per_hour'      => 80,
			'daily_token_budget'       => 100000,
			'log_conversations'        => true,
			'log_retention_days'       => 30,
			'redact_personal_data'     => true,
			'security_enabled'         => true,
			'rate_limit_enabled'       => true,
			'rate_limit_user_max'      => 30,
			'rate_limit_user_window'   => 60,
			'rate_limit_ip_max'        => 60,
			'rate_limit_ip_window'     => 60,
			'rate_limit_cooldown_seconds' => 30,
			'rate_limit_message'       => 'You are sending messages too quickly. Please wait a moment before trying again.',
			'message_length_enabled'   => true,
			'message_max_chars'        => 2000,
			'message_length_message'   => 'Your message is too long. Please keep it under {limit} characters.',
			'blocked_words_enabled'    => true,
			'blocked_words_use_default' => true,
			'blocked_words_list'       => '',
			'blocked_words_action'     => 'block',
			'blocked_words_message'    => 'Your message contains content that is not allowed. Please rephrase your question.',
			'ip_blocklist_enabled'     => false,
			'ip_blocklist'             => '',
			'ip_block_message'         => 'Access denied. Please contact support if you believe this is an error.',
			'auto_flag_enabled'        => true,
			'auto_flag_threshold'      => 10,
			'auto_flag_action'         => 'flag',
			'auto_flag_block_message'  => 'Your message was flagged by the security system. Please rephrase your question.',
			'detect_jailbreak_enabled' => true,
			'jailbreak_multilingual_enabled' => true,
			'jailbreak_severity'       => 10,
			'jailbreak_patterns'       => self::default_jailbreak_patterns(),
			'detect_abuse_enabled'     => true,
			'abuse_severity'           => 5,
			'code_injection_enabled'   => true,
			'detect_content_enabled'   => true,
			'content_severity'         => 3,
			'content_patterns'         => self::default_content_patterns(),
			'detect_behavior_enabled'  => true,
			'behavior_severity'        => 3,
			'behavior_rapid_messages'  => 5,
			'behavior_time_window'     => 10,
			'behavior_repeated_message_max' => 3,
			'whitelist_user_tokens'    => '',
			'whitelist_ips'            => '',
			'debug_mode'               => false,
			'delete_data_on_uninstall' => false,
			'replace_vector_store'     => false,
			'vector_store_id'          => '',
			'vector_store_status'      => 'not_connected',
			'last_sync_at'             => '',
			'last_file_id'             => '',
			'last_file_count'          => 0,
			'last_batch_id'            => '',
			'api_key_encrypted'        => '',
		);
	}

	/**
	 * Update selected options.
	 *
	 * @param array $input Incoming values.
	 */
	public static function update( array $input ): array {
		$current = self::sanitize_for_storage( $input, self::all() );
		update_option( self::KEY, $current, false );
		return self::safe_for_admin( $current );
	}

	/**
	 * Sanitize incoming values into a full stored option array.
	 *
	 * @param array $input Incoming values.
	 * @param array $current Current stored values.
	 */
	private static function sanitize_for_storage( array $input, array $current ): array {
		$allowed_models = array(
			'gpt-4.1-mini',
			'gpt-4.1',
			'gpt-4o-mini',
			'gpt-4o',
			'gpt-5-mini',
			'gpt-5',
			'gpt-5.1-mini',
			'gpt-5.1',
			'gpt-5.2-mini',
			'gpt-5.2',
		);


		$previous_language = JCB_Language::normalize( (string) ( $current['plugin_language'] ?? 'en' ) );
		$new_language      = $previous_language;
		$language_changed  = false;
		if ( isset( $input['plugin_language'] ) ) {
			$new_language = JCB_Language::normalize( (string) $input['plugin_language'] );
			if ( $new_language !== $previous_language ) {
				$language_changed = true;
			}
			$current['plugin_language'] = $new_language;
		}

		if ( isset( $input['assistant_name'] ) ) {
			$current['assistant_name'] = JCB_Sanitizer::text( (string) $input['assistant_name'], 100 );
		}
		if ( isset( $input['model'] ) ) {
			$model = sanitize_text_field( wp_unslash( $input['model'] ) );
			$current['model'] = in_array( $model, $allowed_models, true ) ? $model : 'gpt-4.1-mini';
		}
		if ( isset( $input['instructions'] ) ) {
			$current['instructions'] = JCB_Sanitizer::textarea( (string) $input['instructions'], 8000 );
		}
		if ( isset( $input['welcome_message'] ) ) {
			$current['welcome_message'] = JCB_Sanitizer::text( (string) $input['welcome_message'], 300 );
		}
		if ( isset( $input['placeholder'] ) ) {
			$current['placeholder'] = JCB_Sanitizer::text( (string) $input['placeholder'], 100 );
		}
		$color_fields = array(
			'accent_color'                 => '#6f5bd6',
			'font_color'                   => '#111827',
			'background_color'             => '#f8fafc',
			'user_bubble_color'            => '#6f5bd6',
			'user_bubble_text_color'       => '#ffffff',
			'assistant_bubble_color'       => '#ffffff',
			'assistant_bubble_text_color'  => '#111827',
		);
		foreach ( $color_fields as $key => $fallback ) {
			if ( isset( $input[ $key ] ) ) {
				$current[ $key ] = JCB_Sanitizer::color( (string) $input[ $key ], $fallback );
			}
		}
		if ( isset( $input['bubble_style'] ) ) {
			$bubble_style = sanitize_key( (string) $input['bubble_style'] );
			$current['bubble_style'] = in_array( $bubble_style, array( 'soft', 'round', 'square' ), true ) ? $bubble_style : 'soft';
		}
		if ( isset( $input['design_theme'] ) ) {
			$design_theme = sanitize_key( (string) $input['design_theme'] );
			$allowed_design_themes = array( 'custom', 'purple', 'ocean', 'forest', 'midnight', 'sand' );
			$current['design_theme'] = in_array( $design_theme, $allowed_design_themes, true ) ? $design_theme : 'custom';
		}
		if ( isset( $input['avatar_url'] ) ) {
			$current['avatar_url'] = esc_url_raw( trim( (string) wp_unslash( $input['avatar_url'] ) ) );
		}
		if ( isset( $input['avatar_shape'] ) ) {
			$avatar_shape = sanitize_key( (string) $input['avatar_shape'] );
			$current['avatar_shape'] = in_array( $avatar_shape, array( 'circle', 'rounded', 'squircle', 'speech' ), true ) ? $avatar_shape : 'circle';
		}
		if ( isset( $input['launcher_style'] ) ) {
			$launcher_style = sanitize_key( (string) $input['launcher_style'] );
			$current['launcher_style'] = in_array( $launcher_style, array( 'label', 'icon', 'avatar' ), true ) ? $launcher_style : 'label';
		}
		if ( isset( $input['launcher_icon'] ) ) {
			$launcher_icon = sanitize_key( (string) $input['launcher_icon'] );
			$current['launcher_icon'] = in_array( $launcher_icon, array( 'chat', 'question', 'sparkle', 'bot' ), true ) ? $launcher_icon : 'chat';
		}
		if ( isset( $input['quick_replies'] ) ) {
			$current['quick_replies'] = self::sanitize_quick_replies( (string) $input['quick_replies'] );
		}
		if ( isset( $input['contact_email'] ) ) {
			$current['contact_email'] = sanitize_email( wp_unslash( (string) $input['contact_email'] ) );
		}
		if ( isset( $input['contact_phone'] ) ) {
			$current['contact_phone'] = JCB_Sanitizer::text( (string) $input['contact_phone'], 40 );
		}
		if ( isset( $input['contact_address'] ) ) {
			$current['contact_address'] = JCB_Sanitizer::text( (string) $input['contact_address'], 200 );
		}
		if ( isset( $input['instruction_preset'] ) ) {
			$current['instruction_preset'] = sanitize_key( (string) $input['instruction_preset'] );
		}
		if ( isset( $input['launcher_position'] ) ) {
			$current['launcher_position'] = 'left' === $input['launcher_position'] ? 'left' : 'right';
		}
		if ( isset( $input['launcher_label'] ) ) {
			$current['launcher_label'] = JCB_Sanitizer::text( (string) $input['launcher_label'], 40 );
		}

		if ( isset( $input['visibility_mode'] ) ) {
			$visibility_mode = sanitize_key( (string) $input['visibility_mode'] );
			$allowed_visibility_modes = array( 'everyone', 'logged_in', 'admins', 'selected_users' );
			$current['visibility_mode'] = in_array( $visibility_mode, $allowed_visibility_modes, true ) ? $visibility_mode : 'everyone';
		}
		if ( isset( $input['visibility_user_ids'] ) ) {
			$current['visibility_user_ids'] = self::sanitize_id_list( (string) $input['visibility_user_ids'] );
		}

		if ( $language_changed ) {
			$text_keys = array( 'instructions', 'welcome_message', 'placeholder', 'launcher_label' );
			foreach ( $text_keys as $text_key ) {
				$current_value = (string) ( $current[ $text_key ] ?? '' );
				if ( '' === $current_value || in_array( $current_value, JCB_Language::default_candidates( $text_key ), true ) ) {
					$current[ $text_key ] = JCB_Language::text( $text_key, $new_language );
				}
			}
		}
		if ( isset( $input['excluded_page_ids'] ) ) {
			$current['excluded_page_ids'] = self::sanitize_id_list( (string) $input['excluded_page_ids'] );
		}
		if ( isset( $input['excluded_url_paths'] ) ) {
			$current['excluded_url_paths'] = self::sanitize_path_list( (string) $input['excluded_url_paths'] );
		}

		$security_text_keys = array(
			'rate_limit_message'      => 300,
			'message_length_message'  => 300,
			'blocked_words_message'   => 300,
			'ip_block_message'        => 300,
			'auto_flag_block_message' => 300,
		);
		foreach ( $security_text_keys as $key => $limit ) {
			if ( isset( $input[ $key ] ) ) {
				$current[ $key ] = JCB_Sanitizer::text( (string) $input[ $key ], $limit );
			}
		}

		$security_textarea_keys = array(
			'blocked_words_list'     => 20000,
			'ip_blocklist'           => 12000,
			'jailbreak_patterns'     => 30000,
			'content_patterns'       => 30000,
			'whitelist_user_tokens'  => 12000,
			'whitelist_ips'          => 12000,
		);
		foreach ( $security_textarea_keys as $key => $limit ) {
			if ( isset( $input[ $key ] ) ) {
				$current[ $key ] = JCB_Sanitizer::textarea( (string) $input[ $key ], $limit );
			}
		}

		$security_action_fields = array(
			'blocked_words_action' => array( 'warn', 'block' ),
			'auto_flag_action'     => array( 'flag', 'block' ),
		);
		foreach ( $security_action_fields as $key => $allowed ) {
			if ( isset( $input[ $key ] ) ) {
				$value = sanitize_key( (string) $input[ $key ] );
				$current[ $key ] = in_array( $value, $allowed, true ) ? $value : $allowed[0];
			}
		}

		$security_int_ranges = array(
			'rate_limit_user_max'           => array( 1, 500 ),
			'rate_limit_user_window'        => array( 1, 86400 ),
			'rate_limit_ip_max'             => array( 1, 1000 ),
			'rate_limit_ip_window'          => array( 1, 86400 ),
			'rate_limit_cooldown_seconds'   => array( 0, 3600 ),
			'message_max_chars'             => array( 50, 20000 ),
			'auto_flag_threshold'           => array( 1, 100 ),
			'behavior_rapid_messages'       => array( 2, 100 ),
			'behavior_time_window'          => array( 1, 3600 ),
			'behavior_repeated_message_max' => array( 2, 50 ),
		);
		foreach ( $security_int_ranges as $key => $range ) {
			if ( isset( $input[ $key ] ) ) {
				$current[ $key ] = JCB_Sanitizer::int_range( $input[ $key ], $range[0], $range[1] );
			}
		}

		$security_severity_keys = array( 'jailbreak_severity', 'abuse_severity', 'content_severity', 'behavior_severity' );
		foreach ( $security_severity_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$value = absint( $input[ $key ] );
				$current[ $key ] = in_array( $value, array( 1, 3, 5, 10 ), true ) ? $value : 3;
			}
		}

		$internal_text_keys = array( 'vector_store_id', 'vector_store_status', 'last_sync_at', 'last_file_id', 'last_batch_id', 'api_key_encrypted' );
		foreach ( $internal_text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$current[ $key ] = JCB_Sanitizer::text( (string) $input[ $key ], 2000 );
			}
		}
		if ( isset( $input['last_file_count'] ) ) {
			$current['last_file_count'] = absint( $input['last_file_count'] );
		}

		$bool_keys = array( 'frontend_enabled', 'auto_embed', 'start_open', 'show_on_home', 'show_on_pages', 'show_on_posts', 'show_on_archives', 'show_on_mobile', 'enable_file_search', 'include_sources', 'session_context_enabled', 'log_conversations', 'redact_personal_data', 'security_enabled', 'rate_limit_enabled', 'message_length_enabled', 'blocked_words_enabled', 'blocked_words_use_default', 'ip_blocklist_enabled', 'auto_flag_enabled', 'detect_jailbreak_enabled', 'jailbreak_multilingual_enabled', 'detect_abuse_enabled', 'code_injection_enabled', 'detect_content_enabled', 'detect_behavior_enabled', 'show_avatar_in_header', 'show_avatar_on_messages', 'enable_markdown', 'debug_mode', 'delete_data_on_uninstall', 'replace_vector_store' );
		foreach ( $bool_keys as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$current[ $key ] = JCB_Sanitizer::bool( $input[ $key ] );
			}
		}

		if ( isset( $input['z_index'] ) ) {
			$current['z_index'] = JCB_Sanitizer::int_range( $input['z_index'], 100, 2147483647 );
		}

		if ( isset( $input['max_file_results'] ) ) {
			$current['max_file_results'] = JCB_Sanitizer::int_range( $input['max_file_results'], 1, 20 );
		}
		if ( isset( $input['max_output_tokens'] ) ) {
			$current['max_output_tokens'] = JCB_Sanitizer::int_range( $input['max_output_tokens'], 100, 4000 );
		}
		if ( isset( $input['max_history_messages'] ) ) {
			$current['max_history_messages'] = JCB_Sanitizer::int_range( $input['max_history_messages'], 0, 20 );
		}
		if ( isset( $input['session_ttl_minutes'] ) ) {
			$current['session_ttl_minutes'] = JCB_Sanitizer::int_range( $input['session_ttl_minutes'], 5, 1440 );
		}
		if ( isset( $input['rate_limit_per_minute'] ) ) {
			$current['rate_limit_per_minute'] = JCB_Sanitizer::int_range( $input['rate_limit_per_minute'], 1, 120 );
		}
		if ( isset( $input['rate_limit_per_hour'] ) ) {
			$current['rate_limit_per_hour'] = JCB_Sanitizer::int_range( $input['rate_limit_per_hour'], 1, 2000 );
		}
		if ( isset( $input['daily_token_budget'] ) ) {
			$current['daily_token_budget'] = max( 0, min( 10000000, absint( $input['daily_token_budget'] ) ) );
		}
		if ( isset( $input['log_retention_days'] ) ) {
			$current['log_retention_days'] = JCB_Sanitizer::int_range( $input['log_retention_days'], 1, 365 );
		}
		if ( isset( $input['api_key'] ) && '' !== trim( (string) $input['api_key'] ) ) {
			$current['api_key_encrypted'] = JCB_Encryption::encrypt( sanitize_text_field( wp_unslash( $input['api_key'] ) ) );
		}

		$current['version'] = JCB_VERSION;
		return array_merge( self::defaults(), $current );
	}


	/**
	 * Default jailbreak patterns.
	 */
	private static function default_jailbreak_patterns(): string {
		return implode(
			"\n",
			array(
				'ignore all previous instructions',
				'ignore your instructions',
				'ignore the above',
				'disregard your instructions',
				'disregard all previous',
				'forget your instructions',
				'forget all previous',
				'pretend you are',
				'pretend to be',
				'DAN mode',
				'developer mode',
				'reveal your instructions',
				'show your instructions',
				'what are your instructions',
				'system prompt',
				'bypass safety',
				'jailbreak',
			)
		);
	}

	/**
	 * Default content flag patterns.
	 */
	private static function default_content_patterns(): string {
		return implode(
			"\n",
			array(
				'give me all the data',
				'list all users',
				'show me the database',
				'export all data',
				'download all files',
				'access admin panel',
				'admin credentials',
				'private key',
				'api key',
			)
		);
	}

	/**
	 * Save internal values.
	 *
	 * @param array $values Values.
	 */
	public static function update_internal( array $values ): array {
		$current = self::all();
		$current = array_merge( $current, $values );
		update_option( self::KEY, $current, false );
		return $current;
	}



	/**
	 * Sanitize a comma separated list of page IDs.
	 *
	 * @param string $value Raw input.
	 */
	private static function sanitize_id_list( string $value ): string {
		$ids = preg_split( '/[\s,]+/', $value );
		$ids = array_filter( array_map( 'absint', $ids ) );
		$ids = array_values( array_unique( $ids ) );
		return implode( ',', $ids );
	}

	/**
	 * Sanitize a list of URL paths.
	 *
	 * @param string $value Raw input.
	 */
	private static function sanitize_path_list( string $value ): string {
		$lines = preg_split( '/[\r\n,]+/', $value );
		$paths = array();

		foreach ( $lines as $line ) {
			$line = trim( sanitize_text_field( wp_unslash( $line ) ) );
			if ( '' === $line ) {
				continue;
			}
			$paths[] = '/' . trim( $line, '/' );
		}

		return implode( "\n", array_values( array_unique( $paths ) ) );
	}

	/**
	 * Sanitize the quick reply suggestions (one per line).
	 *
	 * @param string $value Raw input.
	 */
	private static function sanitize_quick_replies( string $value ): string {
		$lines   = preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $value ) );
		$replies = array();
		foreach ( (array) $lines as $line ) {
			$line = JCB_Sanitizer::text( (string) $line, 120 );
			if ( '' !== $line ) {
				$replies[] = $line;
			}
		}
		$replies = array_slice( array_values( array_unique( $replies ) ), 0, 8 );
		return implode( "\n", $replies );
	}

	/**
	 * Parse the quick reply suggestions into an array for the front-end.
	 *
	 * @param string $value Stored value.
	 */
	public static function quick_replies_list( string $value ): array {
		$lines   = preg_split( '/\r\n|\r|\n/', $value );
		$replies = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$replies[] = $line;
			}
		}
		return array_slice( array_values( array_unique( $replies ) ), 0, 8 );
	}

	/**
	 * Return admin-safe settings.
	 *
	 * @param array|null $options Options.
	 */
	public static function safe_for_admin( ?array $options = null ): array {
		$options = $options ? $options : self::all();
		$options['api_key_saved'] = ! empty( $options['api_key_encrypted'] );
		unset( $options['api_key_encrypted'] );
		return $options;
	}

	/**
	 * Get decrypted API key.
	 */
	public static function api_key(): string {
		$options = self::all();
		if ( empty( $options['api_key_encrypted'] ) ) {
			return '';
		}
		return JCB_Encryption::decrypt( (string) $options['api_key_encrypted'] );
	}
}
