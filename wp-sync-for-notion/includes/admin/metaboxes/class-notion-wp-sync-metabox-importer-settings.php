<?php
/**
 * Display modules settings.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Metabox_Importer_Settings
 */
class Notion_WP_Sync_Metabox_Importer_Settings {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}

	/**
	 * Add metabox.
	 */
	public function add_meta_box() {
		add_meta_box(
			'notionwpsync-post-settings',
			__( 'Import As...', 'wp-sync-for-notion' ),
			array( $this, 'display' ),
			'nwpsync-connection',
			'normal',
			'high'
		);
	}

	/**
	 * Output metabox HTML.
	 *
	 * @param \WP_Post $post The post connection.
	 */
	public function display( $post ) {
		$modules = Notion_WP_Sync_Helpers::get_modules();
		$view    = require NOTION_WP_SYNC_PLUGIN_DIR . 'views/metabox-importer-settings.php';
		$view( $modules, $post );
	}
}
