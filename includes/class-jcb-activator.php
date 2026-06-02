<?php
/**
 * Activation logic.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Activator {

	/**
	 * Create database tables and default options.
	 */
	public static function activate(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$conversations   = $wpdb->prefix . 'jcb_conversations';
		$messages        = $wpdb->prefix . 'jcb_messages';
		$events          = $wpdb->prefix . 'jcb_events';

		$sql = "CREATE TABLE {$conversations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_hash varchar(128) NOT NULL,
			started_at datetime NOT NULL,
			last_seen datetime NOT NULL,
			page_url text NULL,
			ip_hash varchar(128) NULL,
			user_agent varchar(255) NULL,
			status varchar(30) NOT NULL DEFAULT 'open',
			PRIMARY KEY (id),
			KEY session_hash (session_hash),
			KEY last_seen (last_seen)
		) {$charset_collate};

		CREATE TABLE {$messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(30) NOT NULL,
			content longtext NOT NULL,
			tokens int unsigned NULL,
			latency_ms int unsigned NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) {$charset_collate};

		CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(80) NOT NULL,
			event_data longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
		self::migrate_legacy_metadata();
		update_option( 'jeroens_chatbox_db_version', JCB_VERSION );

		if ( ! get_option( 'jeroens_chatbox_options' ) ) {
			$legacy = get_option( 'aikb_options', array() );

			if ( is_array( $legacy ) && ! empty( $legacy ) ) {
				add_option( 'jeroens_chatbox_options', $legacy, '', false );
			}
			$defaults = array(
				'version'                   => JCB_VERSION,
				'plugin_language'           => 'en',
				'assistant_name'            => "Jeroen's Chatbox",
				'model'                     => 'gpt-4.1-mini',
				'instructions'              => 'Answer questions using the selected website knowledge base. Be clear, helpful and honest. If the answer is not in the knowledge base, say that you do not know based on the available site content.',
				'welcome_message'           => 'Hi. How can I help you?',
				'placeholder'               => 'Ask a question...',
				'accent_color'              => '#6f5bd6',
				'launcher_position'         => 'right',
				'launcher_label'            => 'Chat',
				'frontend_enabled'          => true,
				'auto_embed'                => false,
				'start_open'                => false,
				'show_on_home'              => true,
				'show_on_pages'             => true,
				'show_on_posts'             => true,
				'show_on_archives'          => true,
				'show_on_mobile'            => true,
				'excluded_page_ids'         => '',
				'excluded_url_paths'        => '',
				'z_index'                   => 99999,
				'enable_file_search'        => true,
				'include_sources'           => true,
				'max_file_results'          => 6,
				'max_output_tokens'         => 700,
				'session_context_enabled'   => true,
				'max_history_messages'      => 8,
				'session_ttl_minutes'       => 90,
				'rate_limit_per_minute'     => 12,
				'rate_limit_per_hour'       => 80,
				'daily_token_budget'        => 100000,
				'log_conversations'         => true,
				'log_retention_days'        => 30,
				'redact_personal_data'      => true,
				'debug_mode'                => false,
				'delete_data_on_uninstall'  => false,
				'vector_store_id'           => '',
				'vector_store_status'       => 'not_connected',
				'last_sync_at'              => '',
				'last_file_id'              => '',
				'last_file_count'           => 0,
				'last_batch_id'             => '',
				'api_key_encrypted'         => '',
				'replace_vector_store'      => false,
			);

			// Ship the website-representative preset as the starting instructions so
			// the company-style persona is available out of the box. Falls back to the
			// generic default if presets are unavailable for any reason.
			if ( class_exists( 'JCB_Presets' ) ) {
				$defaults['instruction_preset'] = 'representative';
				foreach ( JCB_Presets::all( 'en', $defaults ) as $preset ) {
					if ( 'representative' === $preset['id'] && ! empty( $preset['instructions'] ) ) {
						$defaults['instructions'] = $preset['instructions'];
						break;
					}
				}
			}

			add_option( 'jeroens_chatbox_options', $defaults, '', false );
		}

		if ( ! wp_next_scheduled( 'jcb_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'jcb_daily_cleanup' );
		}
	}


	/**
	 * Move early test metadata to the public Jeroen's Chatbox keys.
	 */
	private static function migrate_legacy_metadata(): void {
		global $wpdb;

		$pairs = array(
			'_aikb_include'  => '_jcb_include',
			'_aikb_summary'  => '_jcb_summary',
			'_aikb_tags'     => '_jcb_tags',
			'_aikb_priority' => '_jcb_priority',
		);

		foreach ( $pairs as $old_key => $new_key ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
					$new_key,
					$old_key
				)
			);
		}
	}

	/**
	 * Remove scheduled events.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'jcb_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'jcb_daily_cleanup' );
		}
	}
}
