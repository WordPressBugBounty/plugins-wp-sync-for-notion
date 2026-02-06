<?php
/**
 * Abstract class to define a module.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Abstract Module
 */
abstract class Notion_WP_Sync_Abstract_Module {
	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Module name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Constructor: check properties then register the module.
	 *
	 * @throws \Exception Missing slug or name property.
	 */
	public function __construct() {
		if ( ! isset( $this->slug ) ) {
			throw new \Exception( esc_html( get_class( $this ) . ' must have a $slug property' ) );
		}
		if ( ! isset( $this->name ) ) {
			throw new \Exception( esc_html( get_class( $this ) . ' must have a $name property' ) );
		}

		add_filter( 'notionwpsync/get_modules', array( $this, 'register' ) );
	}

	/**
	 * Register module.
	 *
	 * @param Notion_WP_Sync_Abstract_Module[] $modules A list of registered modules.
	 * @return Notion_WP_Sync_Abstract_Module[] A list of registered modules included the current instance.
	 */
	public function register( $modules ) {
		return array_merge( $modules, array( $this->get_slug() => $this ) );
	}

	/**
	 * Slug getter
	 *
	 * @return string The module's slug.
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Name getter
	 *
	 * @return string The module's name.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Render module settings
	 *
	 * @param \WP_Post $post The post connection.
	 */
	abstract public function render_settings( $post );

	/**
	 * Get importer instance
	 *
	 * @param \WP_Post $post The post connection.
	 * @return Notion_WP_Sync_Abstract_Importer The importer instance.
	 */
	abstract public function get_importer_instance( $post );

	/**
	 * Get mapping options
	 *
	 * @return array The mapping options.
	 */
	public function get_mapping_options() {
		$fields = apply_filters( 'notionwpsync/get_wp_fields', array(), $this->get_slug() );
		foreach ( $fields as &$field ) {
			foreach ( $field['options'] as &$option ) {
				if ( isset( $option['supported_value_types'] ) ) {
					$option['supported_sources'] = Notion_WP_Sync_Field_Factory::get_field_types( $option['supported_value_types'] );
				} else {
					$option['supported_sources'] = array();
				}
			}
		}
		return $fields;
	}

	/**
	 * Get extra config
	 *
	 * @return array The extra config.
	 */
	public function get_extra_config() {
		return array();
	}
}
