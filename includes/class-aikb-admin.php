<?php
/**
 * Admin interface.
 *
 * @package AIKnowledgeChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIKB_Admin {

	/** Admin page hook. */
	private static $hook = '';

	/**
	 * Register admin menu.
	 */
	public static function register_menu(): void {
		self::$hook = add_menu_page(
			__( 'AI Chatbot', 'ai-knowledge-chatbot' ),
			__( 'AI Chatbot', 'ai-knowledge-chatbot' ),
			'manage_options',
			'aikb',
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
		wp_enqueue_style( 'aikb-admin', AIKB_PLUGIN_URL . 'assets/admin.css', array(), AIKB_VERSION );
		wp_enqueue_script( 'aikb-admin', AIKB_PLUGIN_URL . 'assets/admin.js', array(), AIKB_VERSION, true );
		$config = array(
			'restUrl'   => esc_url_raw( rest_url( AIKB_REST_NAMESPACE ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'settings'  => AIKB_Options::safe_for_admin(),
			'shortcode' => '[aikb_chatbot]',
		);
		wp_add_inline_script( 'aikb-admin', 'window.AIKB_ADMIN = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Render admin app shell.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="aikb-admin-wrap" id="aikb-admin-app">
			<header class="aikb-topbar">
				<nav class="aikb-tabs" aria-label="<?php esc_attr_e( 'AI Chatbot sections', 'ai-knowledge-chatbot' ); ?>">
					<button class="aikb-tab is-active" data-tab="knowledge" type="button"><?php esc_html_e( 'Knowledge Base', 'ai-knowledge-chatbot' ); ?></button>
					<button class="aikb-tab" data-tab="assistants" type="button"><?php esc_html_e( 'Assistants', 'ai-knowledge-chatbot' ); ?></button>
					<button class="aikb-tab" data-tab="tools" type="button"><?php esc_html_e( 'Tools', 'ai-knowledge-chatbot' ); ?></button>
					<button class="aikb-tab" data-tab="channels" type="button"><?php esc_html_e( 'Channels', 'ai-knowledge-chatbot' ); ?></button>
					<button class="aikb-tab" data-tab="design" type="button"><?php esc_html_e( 'Design', 'ai-knowledge-chatbot' ); ?></button>
					<button class="aikb-tab" data-tab="analytics" type="button"><?php esc_html_e( 'Analytics', 'ai-knowledge-chatbot' ); ?></button>
					<button class="aikb-tab" data-tab="security" type="button"><?php esc_html_e( 'Security', 'ai-knowledge-chatbot' ); ?></button>
					<button class="aikb-tab" data-tab="api" type="button"><?php esc_html_e( 'OpenAI API', 'ai-knowledge-chatbot' ); ?></button>
					<button class="aikb-tab" data-tab="settings" type="button"><?php esc_html_e( 'Settings', 'ai-knowledge-chatbot' ); ?></button>
				</nav>
			</header>

			<section class="aikb-hero">
				<div class="aikb-icon">◎</div>
				<div>
					<h1><?php esc_html_e( 'Knowledge Base Manager', 'ai-knowledge-chatbot' ); ?></h1>
					<p><?php esc_html_e( 'Select pages, sync them to a vector store, then publish a chatbot on your site.', 'ai-knowledge-chatbot' ); ?></p>
				</div>
			</section>

			<div class="aikb-statusbar">
				<span><?php esc_html_e( 'Selected Pages', 'ai-knowledge-chatbot' ); ?> <strong id="aikb-selected-count">0</strong></span>
				<span><?php esc_html_e( 'Vector Store', 'ai-knowledge-chatbot' ); ?> <strong id="aikb-vector-status">Loading</strong></span>
				<button class="button button-primary aikb-sync" type="button"><?php esc_html_e( 'Sync to Vector Store', 'ai-knowledge-chatbot' ); ?></button>
			</div>

			<div id="aikb-notices" class="aikb-notices" aria-live="polite"></div>

			<main class="aikb-panel is-active" data-panel="knowledge">
				<div class="aikb-grid aikb-grid-knowledge">
					<section class="aikb-card">
						<h2><?php esc_html_e( 'Available Content', 'ai-knowledge-chatbot' ); ?></h2>
						<input id="aikb-content-search" class="aikb-search" type="search" placeholder="<?php esc_attr_e( 'Search pages...', 'ai-knowledge-chatbot' ); ?>">
						<div id="aikb-content-list" class="aikb-content-list"></div>
					</section>
					<section class="aikb-card">
						<h2><?php esc_html_e( 'Page Metadata Editor', 'ai-knowledge-chatbot' ); ?></h2>
						<div id="aikb-editor-empty" class="aikb-empty"><?php esc_html_e( 'Select a page to edit its metadata.', 'ai-knowledge-chatbot' ); ?></div>
						<form id="aikb-metadata-form" class="aikb-hidden">
							<input type="hidden" name="id" id="aikb-meta-id">
							<label><?php esc_html_e( 'Page', 'ai-knowledge-chatbot' ); ?><input type="text" id="aikb-meta-title" readonly></label>
							<label><?php esc_html_e( 'Editor summary', 'ai-knowledge-chatbot' ); ?><textarea id="aikb-meta-summary" rows="6"></textarea></label>
							<label><?php esc_html_e( 'Tags', 'ai-knowledge-chatbot' ); ?><input type="text" id="aikb-meta-tags" placeholder="support, pricing, opening hours"></label>
							<label><?php esc_html_e( 'Priority', 'ai-knowledge-chatbot' ); ?><input type="number" id="aikb-meta-priority" min="0" max="10"></label>
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Save Metadata', 'ai-knowledge-chatbot' ); ?></button>
						</form>
					</section>
				</div>
			</main>

			<main class="aikb-panel" data-panel="assistants"></main>
			<main class="aikb-panel" data-panel="tools"></main>
			<main class="aikb-panel" data-panel="channels"></main>
			<main class="aikb-panel" data-panel="design"></main>
			<main class="aikb-panel" data-panel="analytics"></main>
			<main class="aikb-panel" data-panel="security"></main>
			<main class="aikb-panel" data-panel="api"></main>
			<main class="aikb-panel" data-panel="settings"></main>
		</div>
		<?php
	}
}
