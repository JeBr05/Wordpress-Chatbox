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
		$options  = JCB_Options::safe_for_admin();
		$language = JCB_Language::normalize( (string) ( $options['plugin_language'] ?? 'en' ) );
		$config = array(
			'restUrl'      => esc_url_raw( rest_url( JCB_REST_NAMESPACE ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'settings'     => $options,
			'shortcode'    => '[jeroens_chatbox]',
			'languages'    => JCB_Language::admin_options(),
			'adminStrings' => JCB_Language::admin_strings( $language ),
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

		$options  = JCB_Options::safe_for_admin();
		$language = JCB_Language::normalize( (string) ( $options['plugin_language'] ?? 'en' ) );
		$t = static function ( string $key ) use ( $language ): string {
			return JCB_Language::admin_text( $key, $language );
		};
		?>
		<div class="jcb-admin-wrap" id="jcb-admin-app">
			<header class="jcb-topbar">
				<nav class="jcb-tabs" aria-label="<?php echo esc_attr( $t( 'sections_label' ) ); ?>">
					<button class="jcb-tab is-active" data-tab="knowledge" type="button"><?php echo esc_html( $t( 'tab_knowledge' ) ); ?></button>
					<button class="jcb-tab" data-tab="chatbox" type="button"><?php echo esc_html( $t( 'tab_chatbox' ) ); ?></button>
					<button class="jcb-tab" data-tab="tools" type="button"><?php echo esc_html( $t( 'tab_tools' ) ); ?></button>
					<button class="jcb-tab" data-tab="channels" type="button"><?php echo esc_html( $t( 'tab_channels' ) ); ?></button>
					<button class="jcb-tab" data-tab="design" type="button"><?php echo esc_html( $t( 'tab_design' ) ); ?></button>
					<button class="jcb-tab" data-tab="analytics" type="button"><?php echo esc_html( $t( 'tab_analytics' ) ); ?></button>
					<button class="jcb-tab" data-tab="security" type="button"><?php echo esc_html( $t( 'tab_security' ) ); ?></button>
					<button class="jcb-tab" data-tab="api" type="button"><?php echo esc_html( $t( 'tab_api' ) ); ?></button>
					<button class="jcb-tab" data-tab="settings" type="button"><?php echo esc_html( $t( 'tab_settings' ) ); ?></button>
				</nav>
			</header>

			<section class="jcb-hero">
				<div class="jcb-icon">◎</div>
				<div>
					<h1><?php echo esc_html( $t( 'admin_title' ) ); ?></h1>
					<p><?php echo esc_html( $t( 'admin_description' ) ); ?></p>
				</div>
			</section>

			<div class="jcb-statusbar">
				<span><?php echo esc_html( $t( 'selected_pages' ) ); ?> <strong id="jcb-selected-count">0</strong></span>
				<span><?php echo esc_html( $t( 'vector_store' ) ); ?> <strong id="jcb-vector-status"><?php echo esc_html( $t( 'loading' ) ); ?></strong></span>
				<button class="button button-primary jcb-sync" type="button"><?php echo esc_html( $t( 'sync_to_vector_store' ) ); ?></button>
			</div>

			<div id="jcb-notices" class="jcb-notices" aria-live="polite"></div>

			<main class="jcb-panel is-active" data-panel="knowledge">
				<div class="jcb-grid jcb-grid-knowledge">
					<section class="jcb-card">
						<h2><?php echo esc_html( $t( 'available_content' ) ); ?></h2>
						<input id="jcb-content-search" class="jcb-search" type="search" placeholder="<?php echo esc_attr( $t( 'search_pages' ) ); ?>">
						<div id="jcb-content-list" class="jcb-content-list"></div>
					</section>
					<section class="jcb-card">
						<h2><?php echo esc_html( $t( 'page_metadata_editor' ) ); ?></h2>
						<div id="jcb-editor-empty" class="jcb-empty"><?php echo esc_html( $t( 'select_page_to_edit' ) ); ?></div>
						<form id="jcb-metadata-form" class="jcb-hidden">
							<input type="hidden" name="id" id="jcb-meta-id">
							<label><?php echo esc_html( $t( 'page' ) ); ?><input type="text" id="jcb-meta-title" readonly></label>
							<label><?php echo esc_html( $t( 'editor_summary' ) ); ?><textarea id="jcb-meta-summary" rows="6"></textarea></label>
							<label><?php echo esc_html( $t( 'tags' ) ); ?><input type="text" id="jcb-meta-tags" placeholder="support, pricing, opening hours"></label>
							<label><?php echo esc_html( $t( 'priority' ) ); ?><input type="number" id="jcb-meta-priority" min="0" max="10"></label>
							<button class="button button-primary" type="submit"><?php echo esc_html( $t( 'save_metadata' ) ); ?></button>
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
