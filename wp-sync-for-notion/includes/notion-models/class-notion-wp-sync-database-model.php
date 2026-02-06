<?php
/**
 * Notion database object.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Database_Model class.
 */
class Notion_WP_Sync_Database_Model extends Notion_WP_Sync_Abstract_Model implements \JsonSerializable {

	/**
	 * Filters
	 *
	 * @var array
	 */
	protected $filters;

	/**
	 * {@inheritDoc}
	 *
	 * @param \stdClass $data Notion object datas.
	 */
	public function __construct( $data ) {
		parent::__construct( $data );
		$this->filters = $this->populate_filters();
	}

	/**
	 * Populate the available filters from the object properties.
	 *
	 * @return array
	 */
	protected function populate_filters() {
		$filters = array_reduce(
			$this->get_properties(),
			function ( $result, $property ) {
				if ( $property instanceof Notion_WP_Sync_Field_Interface ) {
					$result [] = $property->get_filter();
				}
				return $result;
			},
			array()
		);
		$filters = apply_filters( 'notionwpsync/notion-model/filters', $filters, $this );
		$filters = array_filter( $filters );

		// Sort by name.
		usort(
			$filters,
			function ( $item_a, $item_b ) {
				return strcmp( $item_a->name, $item_b->name );
			}
		);
		return $filters;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_name(): string {
		return Notion_WP_Sync_Services::get_instance()->get( 'rich_text_parser' )->to_plain_text( $this->data->title );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return array(
			'id'        => $this->get_id(),
			'name'      => $this->get_name(),
			'parent_id' => $this->get_parent_id(),
			'fields'    => array_values( $this->get_fields() ),
			'filters'   => array_values( $this->filters ),
			'type'      => 'database',
		);
	}
}
