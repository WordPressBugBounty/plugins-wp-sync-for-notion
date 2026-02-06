<?php
/**
 * Class to manage post import.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Importer
 */
class Notion_WP_Sync_Post_Importer extends Notion_WP_Sync_Abstract_Importer {
	/**
	 * Init
	 */
	protected function init() {
		if ( $this->config()->get( 'post_type' ) === 'custom' ) {
			$this->register_post_type();
		} elseif ( $this->config()->get( 'post_type' ) === 'ntwpsync-content' ) {
			$this->register_notion_content_post_type();
		}
	}

	/**
	 * Register associated post type
	 */
	public function register_post_type() {
		$cpt_slug = sanitize_key( $this->config()->get( 'post_type_slug' ) );
		$cpt_name = $this->config()->get( 'post_type_name' );

		if ( $cpt_slug && $cpt_name ) {
			register_post_type(
				$cpt_slug,
				array(
					'labels'       => array(
						'name'          => $cpt_name,
						'singular_name' => $cpt_name,
					),
					'public'       => true,
					'supports'     => array( 'title', 'editor', 'author', 'excerpt', 'thumbnail', 'custom-fields' ),
					'rewrite'      => array(
						'slug'       => $cpt_slug,
						'with_front' => false,
					),
					'show_in_rest' => true,
				)
			);
			add_filter(
				'notionwpsync/get_custom_post_types',
				function ( $post_types ) use ( $cpt_slug ) {
					$post_types [] = $cpt_slug;
					return $post_types;
				}
			);
		}
	}

	/**
	 * Register internal "ntwpsync-content" type.
	 *
	 * @return void
	 */
	public function register_notion_content_post_type() {
		Notion_WP_Sync_Notion_Content::register_post_type();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_Post $post The connection.
	 */
	protected function load_settings( $post ) {
		parent::load_settings( $post );

		// Update WordPress destination with new keys (Modules update).
		if ( $this->config->get( 'mapping' ) ) {
			$this->config->set(
				'mapping',
				array_map(
					function ( $mapping ) {
						if ( 'meta::custom_field' === $mapping['wordpress'] ) {
							$mapping['wordpress'] = 'postmeta::custom_field';
						} elseif ( 'meta::_thumbnail_id' === $mapping['wordpress'] ) {
							$mapping['wordpress'] = 'postmeta::_thumbnail_id';
						}
						return $mapping;
					},
					$this->config->get( 'mapping' )
				)
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_removed_contents() {
		if ( 'add_update_delete' !== $this->config()->get( 'sync_strategy' ) ) {
			return;
		}

		$post_ids = get_post_meta( $this->infos()->get( 'id' ), 'content_ids', true );
		if ( ! is_array( $post_ids ) ) {
			$post_ids = array();
		}

		$posts = get_posts(
			array(
				'post_type'      => $this->get_post_type(),
				'post_status'    => 'any',
				'post__not_in'   => $post_ids,
				'fields'         => 'ids',
				'posts_per_page' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_notion_wp_sync_importer_id',
						'value' => $this->infos()->get( 'id' ),
					),
					array(
						'key'     => '_notion_wp_sync_record_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Import record as post object.
	 *
	 * @param Notion_WP_Sync_Abstract_Model $record The Notion object to import.
	 * @param int|null                      $existing_object_id The object id from WordPress.
	 *
	 * @return int
	 * @throws Exception An exception triggered by wp_insert_post or wp_update_post.
	 */
	protected function import_record( $record, $existing_object_id = null ) {
		$this->log( sprintf( $existing_object_id ? '- Update record %s' : '- Create record %s', $record->get_id() ) );

		$record = apply_filters( 'notionwpsync/import_record_data', $record, $this );
		$fields = $this->get_mapped_fields( $record );

		$post_data = array(
			'post_type'   => $this->get_post_type(),
			'post_author' => $this->config()->get( 'post_author' ),
			'post_status' => $this->config()->get( 'post_status' ),
			'post_title'  => _x( 'Notion Imported Content', 'default imported post title', 'wp-sync-for-notion' ),
		);

		$post_metas = array(
			'_notion_wp_sync_record_id'   => $record->get_id(),
			'_notion_wp_sync_hash'        => Notion_WP_Sync_Helpers::generate_hash( $record, $this->config()->to_array() ),
			'_notion_wp_sync_importer_id' => $this->infos()->get( 'id' ),
			'_notion_wp_sync_updated_at'  => gmdate( 'Y-m-d H:i:s' ),
		);

		$post_data = apply_filters( 'notionwpsync/import_post_data', $post_data, $this, $fields, $record, $existing_object_id );

		if ( empty( $post_data['post_author'] ) ) {
			$post_data['post_author'] = $this->config()->get( 'post_author' );
		}
		if ( empty( $post_data['post_status'] ) ) {
			$post_data['post_status'] = $this->config()->get( 'post_status' );
		}

		$post_data = array_map(
			function ( $value ) {
				return is_string( $value ) ? wp_encode_emoji( $value ) : $value;
			},
			$post_data
		);

		// Insert or update post.
		add_filter( 'wp_insert_post_empty_content', '__return_false' );

		if ( $existing_object_id ) {
			$existing_object_id = wp_update_post( array_merge( array( 'ID' => $existing_object_id ), $post_data ), true );
		} else {
			$existing_object_id = wp_insert_post( $post_data, true );
		}

		remove_filter( 'wp_insert_post_empty_content', '__return_false' );

		if ( is_wp_error( $existing_object_id ) ) {
			$error_data = $existing_object_id->get_error_data();
			if ( ! empty( $error_data ) ) {
				$this->log( $error_data, 'error' );
			}
			throw new Exception( esc_html( $existing_object_id->get_error_message() ) );
		}

		// Handle metas.
		foreach ( $post_metas as $meta_key => $meta_value ) {
			update_post_meta( $existing_object_id, $meta_key, $meta_value );
		}

		do_action( 'notionwpsync/import_record_after', $this, $fields, $record, $existing_object_id );

		// Force wp_insert_post re-trigger after metas.
		do_action( 'wp_insert_post', $existing_object_id, get_post( $existing_object_id ), true );

		return $existing_object_id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Notion_WP_Sync_Abstract_Model $record The Notion object to import.
	 * @return WP_Post|null
	 */
	public function get_existing_content_id( $record ) {
		$objects = get_posts(
			array(
				'fields'      => 'ids',
				'post_type'   => $this->get_post_type(),
				'post_status' => 'any',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_notion_wp_sync_importer_id',
						'value' => $this->infos()->get( 'id' ),
					),
					array(
						'key'   => '_notion_wp_sync_record_id',
						'value' => $record->get_id(),
					),
				),
			)
		);
		return array_shift( $objects );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $existing_object_id The object id from WordPress.
	 */
	protected function get_existing_content_hash( $existing_object_id ) {
		return get_post_meta( $existing_object_id, '_notion_wp_sync_hash', true );
	}

	/**
	 * Post Type getter.
	 */
	public function get_post_type() {
		return $this->config()->get( 'post_type' ) === 'custom' ? $this->config()->get( 'post_type_slug' ) : $this->config()->get( 'post_type' );
	}
}
