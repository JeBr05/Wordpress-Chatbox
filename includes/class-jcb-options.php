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
	 * Get public options for front-end.
	 */
	public static function public_config(): array {
		$options = self::all();
		return array(
			'assistantName'  => $options['assistant_name'],
			'welcomeMessage' => $options['welcome_message'],
			'placeholder'    => $options['placeholder'],
			'accentColor'    => $options['accent_color'],
			'position'       => $options['launcher_position'],
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
			'assistant_name'           => "Jeroen's Chatbox",
			'model'                    => 'gpt-4.1-mini',
			'instructions'             => 'Answer questions using the selected website knowledge base. Be clear, helpful and honest. If the answer is not in the knowledge base, say that you do not know based on the available site content.',
			'welcome_message'          => 'Hi. How can I help you?',
			'placeholder'              => 'Ask a question...',
			'accent_color'             => '#6f5bd6',
			'launcher_position'        => 'right',
			'launcher_label'           => 'Chat',
			'frontend_enabled'         => true,
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
		if ( isset( $input['accent_color'] ) ) {
			$current['accent_color'] = JCB_Sanitizer::color( (string) $input['accent_color'] );
		}
		if ( isset( $input['launcher_position'] ) ) {
			$current['launcher_position'] = 'left' === $input['launcher_position'] ? 'left' : 'right';
		}
		if ( isset( $input['launcher_label'] ) ) {
			$current['launcher_label'] = JCB_Sanitizer::text( (string) $input['launcher_label'], 40 );
		}
		if ( isset( $input['excluded_page_ids'] ) ) {
			$current['excluded_page_ids'] = self::sanitize_id_list( (string) $input['excluded_page_ids'] );
		}
		if ( isset( $input['excluded_url_paths'] ) ) {
			$current['excluded_url_paths'] = self::sanitize_path_list( (string) $input['excluded_url_paths'] );
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

		$bool_keys = array( 'frontend_enabled', 'auto_embed', 'start_open', 'show_on_home', 'show_on_pages', 'show_on_posts', 'show_on_archives', 'show_on_mobile', 'enable_file_search', 'include_sources', 'session_context_enabled', 'log_conversations', 'redact_personal_data', 'debug_mode', 'delete_data_on_uninstall', 'replace_vector_store' );
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
