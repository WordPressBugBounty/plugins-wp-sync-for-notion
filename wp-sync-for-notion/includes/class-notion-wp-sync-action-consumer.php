<?php
/**
 * Action consumer manager.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Action_Consumer class.
 */
class Notion_WP_Sync_Action_Consumer {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'notionwpsync_process_records', array( $this, 'consume' ), 10, 4 );
	}

	/**
	 * Consume a record from queue
	 *
	 * @param int    $importer_id Importer id.
	 * @param string $run_id Run id.
	 * @param string $item_id Item id.
	 */
	public function consume( $importer_id, $run_id, $item_id ) {
		// Get importer instance from id.
		$importer = Notion_WP_Sync_Helpers::get_importer_by_id( Notion_WP_Sync_Helpers::get_importers(), $importer_id );
		if ( ! $importer ) {
			return;
		}
		// Get Notion record saved as a temporary option.
		$records = get_option( $item_id );
		if ( ! $records ) {
			return;
		}

		foreach ( $records as $record ) {
			// Import record.
			$content_id = $importer->process_notion_record( $record );
			// Temporarily save created or saved content ID.
			if ( ! empty( $content_id ) && ! is_wp_error( $content_id ) ) {
				$this->append_run_result( $content_id, $importer );
			}
		}

		// Delete temporary option.
		delete_option( $item_id );

		if ( $run_id === $importer->get_run_id() && 0 === $this->get_remaining_actions_count( $importer_id, $run_id ) ) {
			$importer->delete_removed_contents();
			$importer->end_run( 'success' );
		}
	}

	/**
	 * Get count of remaining actions
	 *
	 * @param int         $importer_id Importer id.
	 * @param null|string $run_id Run id.
	 */
	protected function get_remaining_actions_count( $importer_id, $run_id = null ) {
		$args = array(
			'importer_id' => $importer_id,
		);

		if ( $run_id ) {
			$args['run_id'] = $run_id;
		}

		$actions = as_get_scheduled_actions(
			array(
				'hook'                  => 'notionwpsync_process_records',
				'status'                => \ActionScheduler_Store::STATUS_PENDING,
				'partial_args_matching' => 'like',
				'args'                  => $args,
				'per_page'              => -1,
			)
		);
		return count( $actions );
	}

	/**
	 * Append new content_id to the run result
	 *
	 * @param int|null                         $content_id The object id from WordPress.
	 * @param Notion_WP_Sync_Abstract_Importer $importer Importer.
	 */
	protected function append_run_result( $content_id, $importer ) {
		$content_ids = get_post_meta( $importer->infos()->get( 'id' ), 'content_ids', true );

		if ( ! is_array( $content_ids ) ) {
			// Make sure the connection did not have "post_ids" set before the module update.
			$content_ids = get_post_meta( $importer->infos()->get( 'id' ), 'post_ids', true );
			if ( ! is_array( $content_ids ) ) {
				$content_ids = array();
			}
		}
		$content_ids[] = $content_id;
		update_post_meta( $importer->infos()->get( 'id' ), 'content_ids', $content_ids );
	}
}
