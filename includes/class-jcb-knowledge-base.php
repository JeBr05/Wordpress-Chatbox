<?php
/**
 * Knowledge base content selection and sync.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Knowledge_Base {

	/** Include meta key. */
	private const INCLUDE_KEY = '_jcb_include';
	private const SUMMARY_KEY = '_jcb_summary';
	private const TAGS_KEY    = '_jcb_tags';
	private const PRIORITY_KEY = '_jcb_priority';

	/**
	 * List available public content.
	 */
	public static function list_content(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );
		$args = array(
			'post_type'      => array_values( $post_types ),
			'post_status'    => 'publish',
			'posts_per_page' => 250,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		);
		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post_id ) {
			$items[] = self::item( (int) $post_id );
		}
		return $items;
	}

	/**
	 * Get single content item.
	 *
	 * @param int $post_id Post id.
	 */
	public static function item( int $post_id ): array {
		$post = get_post( $post_id );
		return array(
			'id'       => $post_id,
			'title'    => $post ? get_the_title( $post_id ) : '',
			'type'     => $post ? $post->post_type : '',
			'url'      => get_permalink( $post_id ),
			'included' => (bool) get_post_meta( $post_id, self::INCLUDE_KEY, true ),
			'metadata' => self::metadata( $post_id ),
		);
	}

	/**
	 * Get selected items.
	 */
	public static function selected(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );
		$query = new WP_Query(
			array(
				'post_type'      => array_values( $post_types ),
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'meta_key'       => self::INCLUDE_KEY,
				'meta_value'     => '1',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		return $query->posts;
	}

	/**
	 * Update include flag.
	 *
	 * @param int  $post_id Post id.
	 * @param bool $included Include flag.
	 */
	public static function set_included( int $post_id, bool $included ): array {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array();
		}
		if ( $included ) {
			update_post_meta( $post_id, self::INCLUDE_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::INCLUDE_KEY );
		}
		return self::item( $post_id );
	}

	/**
	 * Get metadata.
	 *
	 * @param int $post_id Post id.
	 */
	public static function metadata( int $post_id ): array {
		return array(
			'summary'  => (string) get_post_meta( $post_id, self::SUMMARY_KEY, true ),
			'tags'     => (string) get_post_meta( $post_id, self::TAGS_KEY, true ),
			'priority' => (int) get_post_meta( $post_id, self::PRIORITY_KEY, true ),
		);
	}

	/**
	 * Save metadata.
	 *
	 * @param int   $post_id Post id.
	 * @param array $metadata Metadata.
	 */
	public static function save_metadata( int $post_id, array $metadata ): array {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array();
		}
		update_post_meta( $post_id, self::SUMMARY_KEY, JCB_Sanitizer::textarea( (string) ( $metadata['summary'] ?? '' ), 1000 ) );
		update_post_meta( $post_id, self::TAGS_KEY, JCB_Sanitizer::text( (string) ( $metadata['tags'] ?? '' ), 300 ) );
		update_post_meta( $post_id, self::PRIORITY_KEY, JCB_Sanitizer::int_range( $metadata['priority'] ?? 0, 0, 10 ) );
		return self::item( $post_id );
	}

	/**
	 * Build one local export file per selected page.
	 */
	public static function build_export_files() {
		$posts = self::selected();
		if ( empty( $posts ) ) {
			return new WP_Error( 'jcb_no_content', __( 'Select at least one page before syncing.', 'jeroens-chatbox' ) );
		}

		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . 'jcb';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'jcb_upload_dir', __( 'Could not create export directory.', 'jeroens-chatbox' ) );
		}

		$files = array();
		foreach ( $posts as $post ) {
			$post_id  = (int) $post->ID;
			$slug     = sanitize_title( $post->post_name ? $post->post_name : get_the_title( $post_id ) );
			$file     = trailingslashit( $dir ) . 'jcb-' . $post_id . '-' . $slug . '-' . gmdate( 'Ymd-His' ) . '.txt';
			$body     = self::build_page_document( $post );
			$result   = file_put_contents( $file, $body );
			if ( false === $result ) {
				self::delete_files( $files );
				return new WP_Error( 'jcb_export_failed', __( 'Could not write export file.', 'jeroens-chatbox' ) );
			}
			$files[] = $file;
		}

		return $files;
	}

	/**
	 * Delete local temporary files.
	 *
	 * @param array $files File paths.
	 */
	private static function delete_files( array $files ): void {
		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Build a clean document for one WordPress post.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function build_page_document( WP_Post $post ): string {
		$metadata = self::metadata( (int) $post->ID );
		$content  = self::clean_content( $post );
		$body     = "Site: " . get_bloginfo( 'name' ) . "\n";
		$body    .= "Site URL: " . home_url() . "\n";
		$body    .= "Generated: " . gmdate( 'c' ) . "\n";
		$body    .= "Title: " . get_the_title( $post ) . "\n";
		$body    .= "Type: " . $post->post_type . "\n";
		$body    .= "URL: " . get_permalink( $post ) . "\n";
		$body    .= "Modified: " . get_post_modified_time( 'c', true, $post ) . "\n";
		$body    .= "Priority: " . (int) $metadata['priority'] . "\n";
		if ( $metadata['tags'] ) {
			$body .= "Tags: " . $metadata['tags'] . "\n";
		}
		if ( $metadata['summary'] ) {
			$body .= "Editor Summary: " . $metadata['summary'] . "\n";
		}
		$body .= "Content:\n" . $content . "\n";
		return $body;
	}

	/**
	 * Clean post content.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function clean_content( WP_Post $post ): string {
		$content = $post->post_content;
		$content = strip_shortcodes( $content );
		$content = do_blocks( $content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$content = preg_replace( '/\s+/', ' ', $content );
		return trim( (string) $content );
	}

	/**
	 * Sync content to OpenAI.
	 */
	public static function sync() {
		$options = JCB_Options::all();
		$client  = new JCB_OpenAI_Client();
		$files   = self::build_export_files();
		if ( is_wp_error( $files ) ) {
			return $files;
		}

		$old_vector_store_id = (string) $options['vector_store_id'];
		$store               = $client->create_vector_store( get_bloginfo( 'name' ) . ' Knowledge Base ' . gmdate( 'Y-m-d H:i' ) );
		if ( is_wp_error( $store ) ) {
			self::delete_files( $files );
			return $store;
		}
		$vector_store_id = (string) ( $store['id'] ?? '' );
		if ( '' === $vector_store_id ) {
			self::delete_files( $files );
			return new WP_Error( 'jcb_missing_vector_id', __( 'OpenAI did not return a vector store id.', 'jeroens-chatbox' ) );
		}

		$file_ids = array();
		foreach ( $files as $file ) {
			$upload = $client->upload_file( $file, 'assistants' );
			if ( is_wp_error( $upload ) ) {
				self::delete_files( $files );
				return $upload;
			}
			$file_id = (string) ( $upload['id'] ?? '' );
			if ( '' === $file_id ) {
				self::delete_files( $files );
				return new WP_Error( 'jcb_missing_file_id', __( 'OpenAI did not return a file id.', 'jeroens-chatbox' ) );
			}
			$file_ids[] = $file_id;
		}

		self::delete_files( $files );

		$batch = $client->create_file_batch( $vector_store_id, $file_ids );
		if ( is_wp_error( $batch ) ) {
			return $batch;
		}

		JCB_Options::update_internal(
			array(
				'vector_store_id'     => $vector_store_id,
				'vector_store_status' => (string) ( $batch['status'] ?? 'in_progress' ),
				'last_sync_at'        => current_time( 'mysql', true ),
				'last_file_id'        => implode( ',', $file_ids ),
				'last_file_count'     => count( $file_ids ),
				'last_batch_id'       => (string) ( $batch['id'] ?? '' ),
			)
		);

		if ( ! empty( $options['replace_vector_store'] ) && $old_vector_store_id && $old_vector_store_id !== $vector_store_id ) {
			$client->delete_vector_store( $old_vector_store_id );
		}

		JCB_Logger::event( 'knowledge_base.synced', array( 'vector_store_id' => $vector_store_id, 'file_count' => count( $file_ids ) ) );
		return array(
			'vector_store_id' => $vector_store_id,
			'file_ids'        => $file_ids,
			'file_count'      => count( $file_ids ),
			'batch'           => $batch,
			'options'         => JCB_Options::safe_for_admin(),
		);
	}

	/**
	 * Refresh the stored vector store batch status.
	 */
	public static function refresh_sync_status() {
		$options = JCB_Options::all();
		if ( empty( $options['vector_store_id'] ) || empty( $options['last_batch_id'] ) ) {
			return new WP_Error( 'jcb_no_batch', __( 'There is no vector store batch to check yet.', 'jeroens-chatbox' ), array( 'status' => 404 ) );
		}

		$client = new JCB_OpenAI_Client();
		$batch  = $client->retrieve_file_batch( (string) $options['vector_store_id'], (string) $options['last_batch_id'] );
		if ( is_wp_error( $batch ) ) {
			return $batch;
		}

		JCB_Options::update_internal(
			array(
				'vector_store_status' => (string) ( $batch['status'] ?? $options['vector_store_status'] ),
			)
		);

		return array(
			'batch'   => $batch,
			'options' => JCB_Options::safe_for_admin(),
		);
	}
}
