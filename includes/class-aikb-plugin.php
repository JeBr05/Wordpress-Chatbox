<?php
/**
 * Main plugin bootstrap.
 *
 * @package AIKnowledgeChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIKB_Plugin {

	/** @var AIKB_Plugin|null */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function instance(): AIKB_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load files.
	 */
	private function __construct() {
		$this->includes();
	}

	/**
	 * Require classes.
	 */
	private function includes(): void {
		$files = array(
			'class-aikb-sanitizer.php',
			'class-aikb-options.php',
			'class-aikb-encryption.php',
			'class-aikb-logger.php',
			'class-aikb-session.php',
			'class-aikb-openai-client.php',
			'class-aikb-knowledge-base.php',
			'class-aikb-analytics.php',
			'class-aikb-rest-controller.php',
			'class-aikb-admin.php',
			'class-aikb-shortcode.php',
		);

		foreach ( $files as $file ) {
			require_once AIKB_PLUGIN_DIR . 'includes/' . $file;
		}
	}

	/**
	 * Register hooks.
	 */
	public function run(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( 'AIKB_Shortcode', 'register' ) );
		add_action( 'admin_init', array( 'AIKB_Options', 'register_settings' ) );
		add_action( 'rest_api_init', array( 'AIKB_REST_Controller', 'register_routes' ) );
		add_action( 'admin_menu', array( 'AIKB_Admin', 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( 'AIKB_Admin', 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( 'AIKB_Shortcode', 'enqueue_assets' ) );
		add_action( 'wp_footer', array( 'AIKB_Shortcode', 'maybe_auto_embed' ) );
		add_action( 'aikb_daily_cleanup', array( 'AIKB_Analytics', 'cleanup_old_logs' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'ai-knowledge-chatbot', false, dirname( plugin_basename( AIKB_PLUGIN_FILE ) ) . '/languages' );
	}
}
