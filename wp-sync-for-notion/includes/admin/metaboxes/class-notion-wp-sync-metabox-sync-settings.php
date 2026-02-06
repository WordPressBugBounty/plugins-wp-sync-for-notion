<?php
/**
 * Manage the sync strategy options: manual, recurring...
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Metabox_Sync_Settings
 */
class Notion_WP_Sync_Metabox_Sync_Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}

	/**
	 * Add metabox
	 */
	public function add_meta_box() {
		add_meta_box(
			'notionwpsync-sync-settings',
			__( 'Sync Settings', 'wp-sync-for-notion' ),
			array( $this, 'display' ),
			'nwpsync-connection',
			'normal'
		);
	}

	/**
	 * Output metabox HTML
	 *
	 * @param WP_Post $post The connection.
	 */
	public function display( $post ) {
		$importer        = Notion_WP_Sync_Helpers::get_importer_by_id( Notion_WP_Sync_Helpers::get_importers(), $post->ID );
		$webhook_url     = $importer ? get_rest_url( null, 'notionwpsync/v1/import/' . $importer->infos()->get( 'hash' ) ) : null;
		$sync_strategies = $this->get_sync_strategies();
		$schedules       = $this->get_schedules();
		$view            = include_once NOTION_WP_SYNC_PLUGIN_DIR . 'views/metabox-sync.php';
		$view( $sync_strategies, $schedules, $webhook_url );
	}

	/**
	 * Get sync strategies
	 */
	protected function get_sync_strategies() {
		return array(
			'add_update_delete' => __( 'Add, Update & Delete', 'wp-sync-for-notion' ),
			'add_update'        => __( 'Add & Update', 'wp-sync-for-notion' ),
			'add'               => __( 'Add', 'wp-sync-for-notion' ),
		);
	}

	/**
	 * Get schedules
	 */
	protected function get_schedules() {
		$schedules = array();

		$wp_schedules = array_filter(
			wp_get_schedules(),
			function ( $schedule ) {
				return isset( $schedule['interval'] ) && $schedule['interval'] >= 1800;
			}
		);

		foreach ( $wp_schedules as $key => $schedule ) {
			$enabled                            = in_array( $key, array( 'weekly', 'daily' ), true );
			$schedules[ $schedule['interval'] ] = array(
				'value'   => $key,
				/* translators: %s feature name available in pro version */
				'label'   => $enabled ? $schedule['display'] : sprintf( __( '%s (Pro version)', 'wp-sync-for-notion' ), $schedule['display'] ),
				'enabled' => $enabled,
			);
		}
		ksort( $schedules );
		return array_reverse( $schedules );
	}
}
