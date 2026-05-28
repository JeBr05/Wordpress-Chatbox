<?php
/**
 * Front-end chatbot rendering.
 *
 * @package AIKnowledgeChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIKB_Shortcode {

	/** Has rendered marker. */
	private static $rendered = false;

	/**
	 * Register shortcode.
	 */
	public static function register(): void {
		add_shortcode( 'aikb_chatbot', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Enqueue front-end assets.
	 */
	public static function enqueue_assets(): void {
		wp_register_style( 'aikb-chat', AIKB_PLUGIN_URL . 'assets/chat.css', array(), AIKB_VERSION );
		wp_register_script( 'aikb-chat', AIKB_PLUGIN_URL . 'assets/chat.js', array(), AIKB_VERSION, true );
		wp_add_inline_script( 'aikb-chat', 'window.AIKB_CHAT = ' . wp_json_encode( AIKB_Options::public_config() ) . ';', 'before' );
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public static function render_shortcode( $atts = array() ): string {
		self::$rendered = true;
		wp_enqueue_style( 'aikb-chat' );
		wp_enqueue_script( 'aikb-chat' );
		$config = AIKB_Options::public_config();
		return '<div class="aikb-chat-root" data-config="' . esc_attr( wp_json_encode( $config ) ) . '"></div>';
	}

	/**
	 * Auto embed if enabled.
	 */
	public static function maybe_auto_embed(): void {
		if ( is_admin() || self::$rendered ) {
			return;
		}
		$options = AIKB_Options::all();
		if ( empty( $options['auto_embed'] ) ) {
			return;
		}
		echo self::render_shortcode();
	}
}
