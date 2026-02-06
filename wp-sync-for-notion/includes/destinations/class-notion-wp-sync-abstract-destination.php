<?php
/**
 * Base class to manage import destination.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Abstract Destination
 */
abstract class Notion_WP_Sync_Abstract_Destination {
	/**
	 * Destination slug.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $module;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'notionwpsync/get_wp_fields', array( $this, 'add_fields' ), 10, 2 );
	}

	/**
	 * Add fields to mapping options
	 *
	 * @param array  $fields Fields.
	 * @param string $module_slug Selected module's slug.
	 *
	 * @return array
	 */
	public function add_fields( $fields, $module_slug ) {
		if ( $module_slug === $this->module ) {
			$group      = $this->get_group();
			$new_fields = $this->get_mapping_fields();

			$options        = array();
			$default_option = array(
				'enabled'        => true,
				'allow_multiple' => false,
			);
			foreach ( $new_fields as $field ) {
				$options[] = array_merge( $default_option, $field, array( 'value' => $this->slug . '::' . $field['value'] ) );
			}

			$fields = array_merge_recursive(
				$fields,
				array(
					$group['slug'] => array(
						'options' => $options,
					),
				)
			);
			if ( ! empty( $group['label'] ) ) {
				$fields[ $group['slug'] ]['label'] = $group['label'];
			}
		}
		return $fields;
	}

	/**
	 * Return field mapping based on the field type.
	 *
	 * @param string $field_type The field type.
	 *
	 * @return array|null
	 */
	public function get_field_mapping( $field_type ) {
		$fields        = $this->get_mapping_fields();
		$field_mapping = null;
		foreach ( $fields as $field ) {
			if ( $field['value'] === $field_type ) {
				$field_mapping = $field;
				break;
			}
		}
		return $field_mapping;
	}

	/**
	 * Assign fields to mapping group.
	 */
	abstract protected function get_group();

	/**
	 * Get mapping fields.
	 *
	 * @return array
	 */
	abstract protected function get_mapping_fields();

	/**
	 * Get mapped fields for our destination specifically
	 *
	 * @param Notion_WP_Sync_Abstract_Importer $importer Importer.
	 * @param array                            $fields Fields.
	 *
	 * @TODO: double check why $fields parameter is not used and potentially remove it.
	 * @return array
	 */
	protected function get_destination_mapping( $importer, $fields ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$mapping = ! empty( $importer->config()->get( 'mapping' ) ) ? $importer->config()->get( 'mapping' ) : array();

		$destination_mapping = array();
		foreach ( $mapping as $mapping_pair ) {
			$wp_field_parts = explode( '::', $mapping_pair['wordpress'] );
			$wp_field_group = $wp_field_parts[0] ? $wp_field_parts[0] : '';
			$wp_field       = $wp_field_parts[1] ? $wp_field_parts[1] : '';

			if ( $wp_field_group === $this->slug ) {
				$destination_mapping[] = array_merge( $mapping_pair, array( 'wordpress' => $wp_field ) );
			}
		}
		return $destination_mapping;
	}

	/**
	 * Get source type from Notion id
	 *
	 * @param string                           $notion_id Notion field id.
	 * @param Notion_WP_Sync_Abstract_Importer $importer Importer.
	 *
	 * @return mixed|string
	 */
	protected function get_source_type( $notion_id, $importer ) {
		foreach ( $importer->get_notion_fields() as $field ) {
			if ( $field->get_id() === $notion_id ) {
				// Use result type for computed fields (formula, lookup).
				return $field->get_type();
			}
		}
		return '';
	}
}
