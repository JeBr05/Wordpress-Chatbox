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
		wp_enqueue_media();
		$options  = JCB_Options::safe_for_admin();
		$language = JCB_Language::normalize( (string) ( $options['plugin_language'] ?? 'en' ) );
		$config = array(
			'restUrl'      => esc_url_raw( rest_url( JCB_REST_NAMESPACE ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'settings'     => $options,
			'shortcode'    => '[jeroens_chatbox]',
			'languages'    => JCB_Language::admin_options(),
			'users'        => self::user_options(),
			'presets'      => JCB_Presets::all( $language, JCB_Options::all() ),
			'categories'   => self::category_suggestions(),
			'securityStats' => JCB_Analytics::security_stats(),
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
							<div class="jcb-meta-url-row">
								<span class="jcb-meta-url-label"><?php echo esc_html( $t( 'page_url' ) ); ?></span>
								<a id="jcb-meta-url" class="jcb-meta-url" href="#" target="_blank" rel="noopener noreferrer"></a>
							</div>
							<label class="jcb-meta-summary-label">
								<span class="jcb-meta-summary-head">
									<?php echo esc_html( $t( 'editor_summary' ) ); ?>
									<span id="jcb-meta-autofilled" class="jcb-meta-badge jcb-hidden"><?php echo esc_html( $t( 'auto_filled' ) ); ?></span>
								</span>
								<textarea id="jcb-meta-summary" rows="6"></textarea>
							</label>
							<div class="jcb-meta-summary-tools">
								<button type="button" class="button" id="jcb-meta-autofill"><?php echo esc_html( $t( 'autofill_summary' ) ); ?></button>
								<span id="jcb-meta-wordcount" class="jcb-meta-hint"></span>
							</div>
							<p class="jcb-meta-hint"><?php echo esc_html( $t( 'autofill_summary_help' ) ); ?></p>
							<label class="jcb-check">
								<input type="checkbox" id="jcb-meta-auto-summary" checked> <?php echo esc_html( $t( 'auto_summary_toggle' ) ); ?>
							</label>
							<label><?php echo esc_html( $t( 'kb_category' ) ); ?><input type="text" id="jcb-meta-category" list="jcb-category-suggestions" placeholder="<?php echo esc_attr( $t( 'kb_category_placeholder' ) ); ?>"></label>
							<datalist id="jcb-category-suggestions"></datalist>
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


	/**
	 * Suggest category names from existing taxonomies for the editor datalist.
	 */
	private static function category_suggestions(): array {
		$names = array();

		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'number'     => 100,
				'fields'     => 'names',
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			$names = array_merge( $names, $terms );
		}

		// Include any categories already assigned in the knowledge base.
		global $wpdb;
		$saved = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' LIMIT 100",
				'_jcb_category'
			)
		);
		if ( is_array( $saved ) ) {
			$names = array_merge( $names, $saved );
		}

		$names = array_filter( array_map( 'strval', $names ) );
		$names = array_values( array_unique( $names ) );
		sort( $names );
		return $names;
	}

	/**
	 * Get WordPress users for visibility testing controls.
	 */
	private static function user_options(): array {
		$users = get_users(
			array(
				'number'  => 250,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name', 'user_login', 'user_email' ),
			)
		);

		return array_map(
			static function ( $user ): array {
				$label = $user->display_name ? $user->display_name : $user->user_login;
				return array(
					'id'    => (int) $user->ID,
					'label' => $label,
					'login' => $user->user_login,
					'email' => $user->user_email,
				);
			},
			$users
		);
	}

}
