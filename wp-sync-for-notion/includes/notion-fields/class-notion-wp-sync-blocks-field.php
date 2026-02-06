<?php
/**
 * Blocks Notion field.
 * (fake Notion field to manage blocks https://developers.notion.com/reference/get-block-children)
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Blocks_Field class.
 */
class Notion_WP_Sync_Blocks_Field extends Notion_WP_Sync_Abstract_Field implements Notion_WP_Sync_Support_HTML_Value {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected static $type = '__notionwpsync_blocks';

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected static $default_value_type = Notion_WP_Sync_Support_HTML_Value::class;

	/**
	 * {@inheritDoc}
	 *
	 * @param array $params Extra params.
	 *
	 * @return string
	 */
	public function get_html_value( $params ): string {
		return Notion_WP_Sync_Services::get_instance()->get( 'block_parser' )->parse_blocks( $this->get_raw_value(), $params );
	}
}
