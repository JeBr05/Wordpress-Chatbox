<?php
/**
 * Front-end chatbox rendering.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Shortcode {

	/** Has rendered marker. */
	private static $rendered = false;

	/**
	 * Register shortcodes.
	 */
	public static function register(): void {
		add_shortcode( 'jeroens_chatbox', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Enqueue front-end assets.
	 */
	public static function enqueue_assets(): void {
		wp_register_style( 'jcb-chat', JCB_PLUGIN_URL . 'assets/chat.css', array(), JCB_VERSION );
		wp_register_script( 'jcb-chat', JCB_PLUGIN_URL . 'assets/chat.js', array(), JCB_VERSION, true );
		wp_add_inline_script( 'jcb-chat', 'window.JCB_CHAT = ' . wp_json_encode( JCB_Options::public_config() ) . ';', 'before' );
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public static function render_shortcode( $atts = array() ): string {
		$options = JCB_Options::all();

		if ( empty( $options['frontend_enabled'] ) ) {
			return '';
		}

		self::$rendered = true;
		wp_enqueue_style( 'jcb-chat' );
		wp_enqueue_script( 'jcb-chat' );
		$config = JCB_Options::public_config();
		return '<div class="jcb-chat-root" data-config="' . esc_attr( wp_json_encode( $config ) ) . '"></div>';
	}

	/**
	 * Auto embed if enabled.
	 */
	public static function maybe_auto_embed(): void {
		if ( is_admin() || self::$rendered ) {
			return;
		}

		$options = JCB_Options::all();

		if ( empty( $options['frontend_enabled'] ) || empty( $options['auto_embed'] ) ) {
			return;
		}

		if ( ! self::allowed_on_current_page( $options ) ) {
			return;
		}

		echo self::render_shortcode();
	}

	/**
	 * Check front-end display rules.
	 *
	 * @param array $options Plugin options.
	 */
	private static function allowed_on_current_page( array $options ): bool {
		if ( wp_is_mobile() && empty( $options['show_on_mobile'] ) ) {
			return false;
		}

		if ( ( is_front_page() || is_home() ) && empty( $options['show_on_home'] ) ) {
			return false;
		}

		if ( is_singular( 'page' ) && empty( $options['show_on_pages'] ) ) {
			return false;
		}

		if ( is_singular( 'post' ) && empty( $options['show_on_posts'] ) ) {
			return false;
		}

		if ( is_archive() && empty( $options['show_on_archives'] ) ) {
			return false;
		}

		$current_id = get_queried_object_id();
		$excluded_ids = self::parse_id_list( (string) $options['excluded_page_ids'] );
		if ( $current_id && in_array( $current_id, $excluded_ids, true ) ) {
			return false;
		}

		$current_path = self::current_request_path();
		$excluded_paths = self::parse_path_list( (string) $options['excluded_url_paths'] );

		foreach ( $excluded_paths as $path ) {
			if ( $path === $current_path || 0 === strpos( $current_path, trailingslashit( $path ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Parse page ID list.
	 *
	 * @param string $value Raw ID list.
	 */
	private static function parse_id_list( string $value ): array {
		$ids = preg_split( '/[\s,]+/', $value );
		$ids = array_filter( array_map( 'absint', $ids ) );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Parse URL path list.
	 *
	 * @param string $value Raw path list.
	 */
	private static function parse_path_list( string $value ): array {
		$paths = preg_split( '/[\r\n,]+/', $value );
		$paths = array_map(
			static function ( $path ) {
				return '/' . trim( (string) $path, '/' );
			},
			$paths
		);
		$paths = array_filter( $paths, static fn( $path ) => '/' !== $path );
		return array_values( array_unique( $paths ) );
	}

	/**
	 * Get current request path.
	 */
	private static function current_request_path(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		return '/' . trim( $path, '/' );
	}
}
