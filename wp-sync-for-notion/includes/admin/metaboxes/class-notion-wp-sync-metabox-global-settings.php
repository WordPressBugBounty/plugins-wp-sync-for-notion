<?php
/**
 * Manages Notion settings options: API Key, page selection and page scope.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

use Exception;

/**
 * Notion_WP_Sync_Metabox_Global_Settings class.
 */
class Notion_WP_Sync_Metabox_Global_Settings {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'wp_ajax_notion_wp_sync_get_notion_objects', array( $this, 'get_notion_objects' ) );
	}

	/**
	 * Add metabox
	 */
	public function add_meta_box() {
		add_meta_box(
			'notionwpsync-global-settings',
			__( 'Notion Settings', 'wp-sync-for-notion' ),
			array( $this, 'display' ),
			'nwpsync-connection',
			'normal',
			'high'
		);
	}

	/**
	 * Output metabox HTML
	 */
	public function display() {
		global $post;

		if ( in_array( $post->post_status, array( 'publish', 'draft' ), true ) ) {
			$importer = Notion_WP_Sync_Helpers::get_importer_by_id( Notion_WP_Sync_Helpers::get_importers(), $post->ID );
			$client   = $importer->get_api_client();

			$object_type = $importer->config()->get( 'object_type' );
			$objects_id  = $importer->config()->get( 'objects_id' );
		} else {
			$objects_id = array();
		}

		$default_objects = array(
			'page'     => array(),
			'database' => array(),
		);
		if ( ! is_array( $objects_id ) || empty( $objects_id ) ) {
			$objects = $default_objects;
		} else {
			// @TODO: try / catch
			$objects = array_reduce(
				$objects_id,
				function ( $result, $object_id ) use ( $client, $object_type, $importer ) {
					$object = null;
					if ( 'page' === $object_type ) {
						$object = $client->get_page( $object_id );
					}

					if ( $object ) {
						$result[ $object_type ][ $object_id ] = $object;
					}

					return $result;
				},
				$default_objects
			);
		}
		if ( ! isset( $objects['database'] ) ) {
			$objects['database'] = array();
		}
		$view = include_once NOTION_WP_SYNC_PLUGIN_DIR . 'views/metabox-notion-settings.php';
		$view( $objects );
	}

	/**
	 * Ajax action to get Notion objects (pages).
	 *
	 * @return void
	 */
	public function get_notion_objects() {
		// Nonce check.
		check_ajax_referer( 'notion-wp-sync-ajax', 'nonce' );
		Notion_WP_Sync_Helpers::check_ajax_admin_user_access();

		// Data check.
		if ( empty( $_POST['apiKey'] ) ) {
			wp_die();
		}

		// Get data.
		$params      = array_merge( $_POST );
		$params      = wp_unslash( $params );
		$api_key     = sanitize_text_field( $params['apiKey'] );
		$object_type = sanitize_text_field( $params['objectType'] );
		if ( ! in_array( $object_type, array( 'database', 'page' ), true ) ) {
			$object_type = '';
		}
		$term = sanitize_text_field( $params['term'] ?? '' );

		try {
			$client = new Notion_WP_Sync_Notion_Api_Client( $api_key );
			$result = array();
			if ( 'page' === $object_type ) {
				$result = $client->list_pages( array( 'page_size' => 10 ), $term, 10 );
			} else {
				$result = $client->search( array( 'page_size' => 10 ), 10 );
			}

			wp_send_json_success(
				$result
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'error' => $e->getMessage(),
				)
			);
		}
	}
}
