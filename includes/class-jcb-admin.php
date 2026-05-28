<?php
/**
 * Admin interface.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Admin {

	/** Admin page hook. */
	private static $hook = '';

	/**
	 * Register admin menu.
	 */
	public static function register_menu(): void {
		self::$hook = add_menu_page(
			__( "Jeroen's Chatbox", 'jeroens-chatbox' ),
			__( "Jeroen's Chatbox", 'jeroens-chatbox' ),
			'manage_options',
			'jeroens-chatbox',
			array( __CLASS__, 'render' ),
			'dashicons-format-chat',
			58
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( self::$hook !== $hook ) {
			return;
		}
		wp_enqueue_style( 'jcb-admin', JCB_PLUGIN_URL . 'assets/admin.css', array(), JCB_VERSION );
		wp_enqueue_script( 'jcb-admin', JCB_PLUGIN_URL . 'assets/admin.js', array(), JCB_VERSION, true );
		$config = array(
			'restUrl'   => esc_url_raw( rest_url( JCB_REST_NAMESPACE ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'settings'  => JCB_Options::safe_for_admin(),
			'shortcode' => '[jeroens_chatbox]',
			'languages' => JCB_Language::admin_options(),
		);
		wp_add_inline_script( 'jcb-admin', 'window.JCB_ADMIN = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Render admin app shell.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="jcb-admin-wrap" id="jcb-admin-app">
			<header class="jcb-topbar">
				<nav class="jcb-tabs" aria-label="<?php esc_attr_e( "Jeroen's Chatbox sections", 'jeroens-chatbox' ); ?>">
					<button class="jcb-tab is-active" data-tab="knowledge" type="button"><?php esc_html_e( 'Knowledge Base', 'jeroens-chatbox' ); ?></button>
					<button class="jcb-tab" data-tab="chatbox" type="button"><?php esc_html_e( 'Chatbox', 'jeroens-chatbox' ); ?></button>
					<button class="jcb-tab" data-tab="tools" type="button"><?php esc_html_e( 'Tools', 'jeroens-chatbox' ); ?></button>
					<button class="jcb-tab" data-tab="channels" type="button"><?php esc_html_e( 'Channels', 'jeroens-chatbox' ); ?></button>
					<button class="jcb-tab" data-tab="design" type="button"><?php esc_html_e( 'Design', 'jeroens-chatbox' ); ?></button>
					<button class="jcb-tab" data-tab="analytics" type="button"><?php esc_html_e( 'Analytics', 'jeroens-chatbox' ); ?></button>
					<button class="jcb-tab" data-tab="security" type="button"><?php esc_html_e( 'Security', 'jeroens-chatbox' ); ?></button>
					<button class="jcb-tab" data-tab="api" type="button"><?php esc_html_e( 'OpenAI API', 'jeroens-chatbox' ); ?></button>
					<button class="jcb-tab" data-tab="settings" type="button"><?php esc_html_e( 'Settings', 'jeroens-chatbox' ); ?></button>
				</nav>
			</header>

			<section class="jcb-hero">
				<div class="jcb-icon">◎</div>
				<div>
					<h1><?php esc_html_e( "Jeroen's Chatbox Manager", 'jeroens-chatbox' ); ?></h1>
					<p><?php esc_html_e( "Select pages, sync them to a vector store, then publish Jeroen's Chatbox on your site.", 'jeroens-chatbox' ); ?></p>
				</div>
			</section>

			<div class="jcb-statusbar">
				<span><?php esc_html_e( 'Selected Pages', 'jeroens-chatbox' ); ?> <strong id="jcb-selected-count">0</strong></span>
				<span><?php esc_html_e( 'Vector Store', 'jeroens-chatbox' ); ?> <strong id="jcb-vector-status">Loading</strong></span>
				<button class="button button-primary jcb-sync" type="button"><?php esc_html_e( 'Sync to Vector Store', 'jeroens-chatbox' ); ?></button>
			</div>

			<div id="jcb-notices" class="jcb-notices" aria-live="polite"></div>

			<main class="jcb-panel is-active" data-panel="knowledge">
				<div class="jcb-grid jcb-grid-knowledge">
					<section class="jcb-card">
						<h2><?php esc_html_e( 'Available Content', 'jeroens-chatbox' ); ?></h2>
						<input id="jcb-content-search" class="jcb-search" type="search" placeholder="<?php esc_attr_e( 'Search pages...', 'jeroens-chatbox' ); ?>">
						<div id="jcb-content-list" class="jcb-content-list"></div>
					</section>
					<section class="jcb-card">
						<h2><?php esc_html_e( 'Page Metadata Editor', 'jeroens-chatbox' ); ?></h2>
						<div id="jcb-editor-empty" class="jcb-empty"><?php esc_html_e( 'Select a page to edit its metadata.', 'jeroens-chatbox' ); ?></div>
						<form id="jcb-metadata-form" class="jcb-hidden">
							<input type="hidden" name="id" id="jcb-meta-id">
							<label><?php esc_html_e( 'Page', 'jeroens-chatbox' ); ?><input type="text" id="jcb-meta-title" readonly></label>
							<label><?php esc_html_e( 'Editor summary', 'jeroens-chatbox' ); ?><textarea id="jcb-meta-summary" rows="6"></textarea></label>
							<label><?php esc_html_e( 'Tags', 'jeroens-chatbox' ); ?><input type="text" id="jcb-meta-tags" placeholder="support, pricing, opening hours"></label>
							<label><?php esc_html_e( 'Priority', 'jeroens-chatbox' ); ?><input type="number" id="jcb-meta-priority" min="0" max="10"></label>
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Save Metadata', 'jeroens-chatbox' ); ?></button>
						</form>
					</section>
				</div>
			</main>

			<main class="jcb-panel" data-panel="chatbox"></main>
			<main class="jcb-panel" data-panel="tools"></main>
			<main class="jcb-panel" data-panel="channels"></main>
			<main class="jcb-panel" data-panel="design"></main>
			<main class="jcb-panel" data-panel="analytics"></main>
			<main class="jcb-panel" data-panel="security"></main>
			<main class="jcb-panel" data-panel="api"></main>
			<main class="jcb-panel" data-panel="settings"></main>
		</div>
		<?php
	}
}
