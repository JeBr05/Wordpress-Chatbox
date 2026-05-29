<?php
/**
 * REST API routes.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_REST_Controller {

	/**
	 * Register routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			JCB_REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_settings' ),
					'permission_callback' => array( __CLASS__, 'admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'save_settings' ),
					'permission_callback' => array( __CLASS__, 'admin_permission' ),
				),
			)
		);

		register_rest_route(
			JCB_REST_NAMESPACE,
			'/content',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_content' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			JCB_REST_NAMESPACE,
			'/content/(?P<id>\d+)/include',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'set_content_include' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			JCB_REST_NAMESPACE,
			'/metadata/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'save_metadata' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			JCB_REST_NAMESPACE,
			'/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'sync' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			JCB_REST_NAMESPACE,
			'/sync-status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'sync_status' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			JCB_REST_NAMESPACE,
			'/test-api',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'test_api' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			JCB_REST_NAMESPACE,
			'/analytics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'analytics' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			JCB_REST_NAMESPACE,
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'chat' ),
				'permission_callback' => array( __CLASS__, 'public_permission' ),
			)
		);

		register_rest_route(
			JCB_REST_NAMESPACE,
			'/feedback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'feedback' ),
				'permission_callback' => array( __CLASS__, 'public_permission' ),
			)
		);
	}

	/**
	 * Admin permission.
	 */
	public static function admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Public nonce check for front-end routes.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function public_permission( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_header( 'x-wp-nonce' );
		}
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'jcb_bad_nonce', __( 'Security check failed. Refresh the page and try again.', 'jeroens-chatbox' ), array( 'status' => 403 ) );
		}

		if ( ! self::visible_to_current_user() ) {
			return new WP_Error( 'jcb_not_visible', __( 'The chatbox is not available for this visitor.', 'jeroens-chatbox' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check visitor level access for public chat routes.
	 */
	private static function visible_to_current_user(): bool {
		$options = JCB_Options::all();
		if ( empty( $options['frontend_enabled'] ) ) {
			return false;
		}

		$mode = isset( $options['visibility_mode'] ) ? sanitize_key( (string) $options['visibility_mode'] ) : 'everyone';
		if ( 'everyone' === $mode ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( 'logged_in' === $mode ) {
			return true;
		}

		if ( 'admins' === $mode ) {
			return current_user_can( 'manage_options' );
		}

		if ( 'selected_users' === $mode ) {
			$ids = preg_split( '/[\s,]+/', (string) ( $options['visibility_user_ids'] ?? '' ) );
			$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
			return in_array( get_current_user_id(), $ids, true );
		}

		return true;
	}

	/**
	 * Get settings.
	 */
	public static function get_settings(): WP_REST_Response {
		return rest_ensure_response( JCB_Options::safe_for_admin() );
	}

	/**
	 * Save settings.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function save_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = JCB_Options::update( $request->get_json_params() ?: array() );
		JCB_Logger::event( 'settings.updated' );
		return rest_ensure_response( $settings );
	}

	/**
	 * Get content.
	 */
	public static function get_content(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'items'    => JCB_Knowledge_Base::list_content(),
				'options'  => JCB_Options::safe_for_admin(),
				'selected' => count( JCB_Knowledge_Base::selected() ),
			)
		);
	}

	/**
	 * Set content include.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function set_content_include( WP_REST_Request $request ): WP_REST_Response {
		$id       = (int) $request['id'];
		$params   = $request->get_json_params() ?: array();
		$included = ! empty( $params['included'] );
		$item     = JCB_Knowledge_Base::set_included( $id, $included );
		return rest_ensure_response( array( 'item' => $item, 'selected' => count( JCB_Knowledge_Base::selected() ) ) );
	}

	/**
	 * Save metadata.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function save_metadata( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request['id'];
		$params = $request->get_json_params() ?: array();
		$item   = JCB_Knowledge_Base::save_metadata( $id, $params );
		return rest_ensure_response( array( 'item' => $item ) );
	}

	/**
	 * Sync knowledge base.
	 */
	public static function sync() {
		$result = JCB_Knowledge_Base::sync();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Check vector store processing status.
	 */
	public static function sync_status() {
		$result = JCB_Knowledge_Base::refresh_sync_status();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Test API.
	 */
	public static function test_api() {
		$client = new JCB_OpenAI_Client();
		$result = $client->request( 'GET', '/models', null, 30 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'ok' => true, 'message' => __( 'OpenAI connection works.', 'jeroens-chatbox' ) ) );
	}

	/**
	 * Analytics.
	 */
	public static function analytics(): WP_REST_Response {
		return rest_ensure_response( JCB_Analytics::stats() );
	}

	/**
	 * Public chat endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function chat( WP_REST_Request $request ) {
		$rate = self::rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$budget = self::budget_available();
		if ( is_wp_error( $budget ) ) {
			return $budget;
		}

		$params  = $request->get_json_params() ?: array();
		$message = JCB_Sanitizer::textarea( (string) ( $params['message'] ?? '' ), 2000 );
		$session = JCB_Sanitizer::text( (string) ( $params['sessionId'] ?? wp_generate_uuid4() ), 160 );
		$page    = esc_url_raw( (string) ( $params['pageUrl'] ?? '' ) );

		if ( '' === $message ) {
			return new WP_Error( 'jcb_empty_message', __( 'Message is required.', 'jeroens-chatbox' ), array( 'status' => 400 ) );
		}

		$options = JCB_Options::all();
		if ( empty( $options['api_key_encrypted'] ) ) {
			return new WP_Error( 'jcb_not_configured', __( 'The chatbox is not configured yet.', 'jeroens-chatbox' ), array( 'status' => 503 ) );
		}

		$history = JCB_Session::recent( $session, (int) $options['max_history_messages'] );
		$payload = self::build_chat_payload( $message, $options, $page, $history );
		$started = microtime( true );

		JCB_Logger::message( $session, 'user', $message, array( 'page_url' => $page ) );

		$client = new JCB_OpenAI_Client();
		$result = $client->create_response( $payload );
		if ( is_wp_error( $result ) ) {
			JCB_Logger::event( 'chat.error', array( 'message' => $result->get_error_message() ) );
			return $result;
		}

		$answer = JCB_OpenAI_Client::output_text( $result );
		if ( '' === $answer ) {
			$answer = JCB_Language::text( 'error_answer', (string) ( $options['plugin_language'] ?? 'en' ) );
		}

		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );
		$tokens  = isset( $result['usage']['total_tokens'] ) ? absint( $result['usage']['total_tokens'] ) : 0;
		self::record_usage( $tokens );
		JCB_Session::append( $session, 'user', $message );
		JCB_Session::append( $session, 'assistant', $answer );
		JCB_Logger::message( $session, 'assistant', $answer, array( 'latency_ms' => $latency, 'tokens' => $tokens, 'page_url' => $page ) );

		return rest_ensure_response(
			array(
				'answer'     => wp_kses_post( $answer ),
				'sources'    => JCB_OpenAI_Client::file_sources( $result ),
				'sessionId'  => $session,
				'latencyMs'  => $latency,
				'usage'      => $result['usage'] ?? null,
				'responseId' => $result['id'] ?? null,
			)
		);
	}

	/**
	 * Build OpenAI response payload.
	 *
	 * @param string $message User message.
	 * @param array  $options Options.
	 * @param string $page Current page.
	 * @param array  $history Previous messages.
	 */
	private static function build_chat_payload( string $message, array $options, string $page, array $history = array() ): array {
		$instructions  = $options['instructions'];
		$instructions .= "\n\nRules:\n";
		$instructions .= 'Use the website knowledge base when available. Do not invent page content. Keep answers practical. If sources are requested, mention the relevant page titles or URLs found in the knowledge base.';
		$instructions .= "\n" . JCB_Language::response_rule( (string) ( $options['plugin_language'] ?? 'en' ) );
		if ( $page ) {
			$instructions .= "\nThe visitor is currently on: " . $page;
		}

		$input = array();
		foreach ( $history as $message_item ) {
			$input[] = array(
				'role'    => 'assistant' === $message_item['role'] ? 'assistant' : 'user',
				'content' => (string) $message_item['content'],
			);
		}
		$input[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		$payload = array(
			'model'             => $options['model'],
			'instructions'      => $instructions,
			'input'             => $input,
			'max_output_tokens' => JCB_Sanitizer::int_range( $options['max_output_tokens'] ?? 700, 100, 4000 ),
		);

		if ( ! empty( $options['enable_file_search'] ) && ! empty( $options['vector_store_id'] ) ) {
			$tool = array(
				'type'             => 'file_search',
				'vector_store_ids' => array( $options['vector_store_id'] ),
				'max_num_results'  => (int) $options['max_file_results'],
			);
			$payload['tools'] = array( $tool );
			if ( ! empty( $options['include_sources'] ) ) {
				$payload['include'] = array( 'file_search_call.results' );
			}
		}

		return $payload;
	}

	/**
	 * Basic public rate limit by IP.
	 */
	private static function rate_limit() {
		$options      = JCB_Options::all();
		$limit_minute = JCB_Sanitizer::int_range( $options['rate_limit_per_minute'], 1, 120 );
		$limit_hour   = JCB_Sanitizer::int_range( $options['rate_limit_per_hour'] ?? 80, 1, 2000 );
		$ip           = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$hash         = md5( $ip );

		$checks = array(
			array( 'key' => 'jcb_rate_min_' . $hash, 'limit' => $limit_minute, 'ttl' => MINUTE_IN_SECONDS ),
			array( 'key' => 'jcb_rate_hour_' . $hash, 'limit' => $limit_hour, 'ttl' => HOUR_IN_SECONDS ),
		);

		foreach ( $checks as $check ) {
			$count = (int) get_transient( $check['key'] );
			if ( $count >= $check['limit'] ) {
				return new WP_Error( 'jcb_rate_limited', __( 'Too many messages. Please wait and try again.', 'jeroens-chatbox' ), array( 'status' => 429 ) );
			}
		}

		foreach ( $checks as $check ) {
			$count = (int) get_transient( $check['key'] );
			set_transient( $check['key'], $count + 1, $check['ttl'] );
		}

		return true;
	}

	/**
	 * Check daily token budget before calling the API.
	 */
	private static function budget_available() {
		$options = JCB_Options::all();
		$budget  = max( 0, absint( $options['daily_token_budget'] ?? 0 ) );
		if ( 0 === $budget ) {
			return true;
		}

		$key  = 'jcb_tokens_' . gmdate( 'Ymd' );
		$used = (int) get_transient( $key );
		if ( $used >= $budget ) {
			return new WP_Error( 'jcb_daily_budget_hit', __( 'The chatbox daily budget has been reached. Please try again later.', 'jeroens-chatbox' ), array( 'status' => 429 ) );
		}
		return true;
	}

	/**
	 * Record tokens used today.
	 *
	 * @param int $tokens Used tokens.
	 */
	private static function record_usage( int $tokens ): void {
		if ( $tokens <= 0 ) {
			return;
		}
		$key  = 'jcb_tokens_' . gmdate( 'Ymd' );
		$used = (int) get_transient( $key );
		set_transient( $key, $used + $tokens, DAY_IN_SECONDS + HOUR_IN_SECONDS );
	}

	/**
	 * Feedback endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function feedback( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?: array();
		JCB_Logger::event(
			'chat.feedback',
			array(
				'session' => JCB_Sanitizer::text( (string) ( $params['sessionId'] ?? '' ), 160 ),
				'rating'  => JCB_Sanitizer::text( (string) ( $params['rating'] ?? '' ), 20 ),
				'note'    => JCB_Sanitizer::textarea( (string) ( $params['note'] ?? '' ), 500 ),
			)
		);
		return rest_ensure_response( array( 'ok' => true ) );
	}
}
