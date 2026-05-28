<?php
/**
 * Activation logic.
 *
 * @package AIKnowledgeChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIKB_Activator {

	/**
	 * Create database tables and default options.
	 */
	public static function activate(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$conversations   = $wpdb->prefix . 'aikb_conversations';
		$messages        = $wpdb->prefix . 'aikb_messages';
		$events          = $wpdb->prefix . 'aikb_events';

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
		update_option( 'aikb_db_version', AIKB_VERSION );

		if ( ! get_option( 'aikb_options' ) ) {
			$defaults = array(
				'version'                   => AIKB_VERSION,
				'assistant_name'            => get_bloginfo( 'name' ) . ' Assistant',
				'model'                     => 'gpt-4.1-mini',
				'instructions'              => 'Answer questions using the selected website knowledge base. Be clear, helpful and honest. If the answer is not in the knowledge base, say that you do not know based on the available site content.',
				'welcome_message'           => 'Hi. How can I help you?',
				'placeholder'               => 'Ask a question...',
				'accent_color'              => '#6f5bd6',
				'launcher_position'         => 'right',
				'auto_embed'                => false,
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
			add_option( 'aikb_options', $defaults, '', false );
		}

		if ( ! wp_next_scheduled( 'aikb_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aikb_daily_cleanup' );
		}
	}

	/**
	 * Remove scheduled events.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'aikb_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'aikb_daily_cleanup' );
		}
	}
}
