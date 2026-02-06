<?php
/**
 * Image Formatter: retrieve first image from a Notion Field files value
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Image_Formatter class.
 */
class Notion_WP_Sync_Image_Formatter {
	/**
	 * Format source value
	 *
	 * @param Notion_WP_Sync_Support_Files_Value $notion_field The Notion field with files value support.
	 * @param Notion_WP_Sync_Abstract_Importer   $importer Importer.
	 * @param int                                $post_id The post id.
	 * @param mixed                              $value Default value.
	 *
	 * @return mixed|null
	 */
	public function format( $notion_field, $importer, $post_id, $value = null ) {
		$files_value = $notion_field->get_value(
			Notion_WP_Sync_Support_Files_Value::class,
			array(
				'post_id'  => $post_id,
				'importer' => $importer,
			)
		);

		if ( ! is_array( $files_value ) || empty( $files_value ) ) {
			return $value;
		}

		// Keep first image attachment from multipleAttachments.
		$image_mime_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		foreach ( $files_value as $attachment_id ) {
			if ( in_array( get_post_mime_type( $attachment_id ), $image_mime_types, true ) ) {
				$value = $attachment_id;
				break;
			}
		}

		if ( ! empty( $value ) && ! is_int( $value ) ) {
			return null;
		}

		return $value;
	}
}
