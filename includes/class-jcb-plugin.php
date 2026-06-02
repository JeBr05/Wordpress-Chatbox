<?php
/**
 * Main plugin bootstrap.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JCB_Plugin {

	/** @var JCB_Plugin|null */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function instance(): JCB_Plugin {
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
			'class-jcb-sanitizer.php',
			'class-jcb-language.php',
			'class-jcb-presets.php',
			'class-jcb-options.php',
			'class-jcb-security-manager.php',
			'class-jcb-encryption.php',
			'class-jcb-logger.php',
			'class-jcb-session.php',
			'class-jcb-openai-client.php',
			'class-jcb-knowledge-base.php',
			'class-jcb-analytics.php',
			'class-jcb-rest-controller.php',
			'class-jcb-admin.php',
			'class-jcb-shortcode.php',
		);

		foreach ( $files as $file ) {
			require_once JCB_PLUGIN_DIR . 'includes/' . $file;
		}
	}

	/**
	 * Register hooks.
	 */
	public function run(): void {
		add_action( 'plugins_loaded', array( 'JCB_Options', 'maybe_upgrade' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( 'JCB_Shortcode', 'register' ) );
		add_action( 'admin_init', array( 'JCB_Options', 'register_settings' ) );
		add_action( 'rest_api_init', array( 'JCB_REST_Controller', 'register_routes' ) );
		add_action( 'admin_menu', array( 'JCB_Admin', 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( 'JCB_Admin', 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( 'JCB_Shortcode', 'enqueue_assets' ) );
		add_action( 'wp_footer', array( 'JCB_Shortcode', 'maybe_auto_embed' ) );
		add_action( 'jcb_daily_cleanup', array( 'JCB_Analytics', 'cleanup_old_logs' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'jeroens-chatbox', false, dirname( plugin_basename( JCB_PLUGIN_FILE ) ) . '/languages' );
	}
}
