<?php
/**
 * Manages formula Notion property type.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion field multi select
 */
class Notion_WP_Sync_Formula_Field extends Notion_WP_Sync_Abstract_Field {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected static $type = 'formula';

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected static $default_value_type = '';

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $filter_type = 'formula';

	const MANAGED_FORMULA_TYPES = array( 'string', 'number', 'boolean' );

	/**
	 * {@inheritDoc}
	 */
	public static function register() {
		// Add equivalent field.
		add_filter( 'notionwpsync/notion-api-client/get-database', self::class . '::register_equivalent_fields', 10, 2 );

		// Add equivalent field.
		add_filter( 'notionwpsync/notion-model/fields', self::class . '::add_equivalent_fields', 10, 2 );

		// Add equivalent filters.
		add_filter( 'notionwpsync/notion-model/filters', self::class . '::add_equivalent_filters', 10, 2 );
	}

	/**
	 * Load some pages from the database and try to get return type information from the formula fields.
	 * Based on this information add the right equivalent typed field to the database information.
	 *
	 * @param Notion_WP_Sync_Database_Model    $database The database object retrieved from the API.
	 * @param Notion_WP_Sync_Notion_Api_Client $api_client The Notion API client.
	 *
	 * @return Notion_WP_Sync_Database_Model
	 */
	public static function register_equivalent_fields( $database, $api_client ) {
		$has_formula_field = array_reduce(
			$database->get_fields(),
			function ( $result, $field ) {
				return $result || $field instanceof Notion_WP_Sync_Formula_Field;
			},
			false
		);
		if ( ! $has_formula_field ) {
			return $database;
		}
		$pages = $api_client->list_database_pages( $database->get_id(), array( 'page_size' => 10 ), 10 );
		if ( count( $pages ) === 0 ) {
			return $database;
		}
		$database->set_fields(
			array_reduce(
				$database->get_fields(),
				function ( $result, $field ) use ( $pages ) {
					if ( $field instanceof Notion_WP_Sync_Formula_Field ) {
						$first_page          = self::get_first_page_with_field( $pages, $field->get_id() );
						$formula_return_type = null;

						if ( $first_page ) {
							$first_page_field = $first_page->get_field( $field->get_id() );

							if ( $first_page_field ) {
								$data_raw                          = $first_page_field->get_raw_value();
								list($formula_return_type, $value) = self::get_type_and_value_from_field_data_raw( $data_raw );
							}
						}

						$result[] = self::build_equivalent_field( $field, $formula_return_type, null );
					} else {
						$result[] = $field;
					}

					return $result;
				},
				array()
			)
		);

		return $database;
	}

	/**
	 * Add Notion page field equivalent typed fields based on managed types.
	 *
	 * @param array  $properties_objects Field properties.
	 * @param object $data Notion object data (page / database).
	 *
	 * @return array
	 */
	public static function add_equivalent_fields( $properties_objects, $data ) {
		if ( 'page' !== $data->object ) {
			return $properties_objects;
		}
		$properties_objects = array_reduce(
			$properties_objects,
			function ( $result, $properties_object ) use ( $data ) {
				if ( $properties_object instanceof Notion_WP_Sync_Formula_Field ) {
					$data_raw                          = $properties_object->get_raw_value();
					list($formula_return_type, $value) = self::get_type_and_value_from_field_data_raw( $data_raw );
					// We don't know here what return type is expected, so we build fields based on all managed types + null (= unknown type).
					$types = array_merge( self::MANAGED_FORMULA_TYPES, array( null ) );
					foreach ( $types as $type ) {
						$result[] = self::build_equivalent_field( $properties_object, $formula_return_type, $value, $type );
					}
				}

				$result[] = $properties_object;
				return $result;
			},
			array()
		);

		return $properties_objects;
	}

	/**
	 * Build equivalent formula field based on return type.
	 *
	 * @param Notion_WP_Sync_Field_Interface $field The formula field.
	 * @param string                         $formula_return_type The formula return type as defined by Notion.
	 * @param mixed                          $value The value retrieved from the raw data Notion API gave us.
	 * @param string|null|false              $convert_to Convert to specified type.
	 *
	 * @return Notion_WP_Sync_Generic_Number_Field|Notion_WP_Sync_Generic_Text_Field
	 */
	protected static function build_equivalent_field( $field, $formula_return_type, $value, $convert_to = false ) {
		$return_type = $formula_return_type;
		if ( false !== $convert_to ) {
			$return_type = $convert_to;
		}
		if ( 'string' === $return_type ) {
			if ( false !== $convert_to ) {
				try {
					$value = (string) $value;
				} catch ( \Throwable $exception ) {
					$value = null;
				}
			}
			$equivalent_field = new Notion_WP_Sync_Generic_Text_Field(
				(object) array(
					'id'               => $field->get_id() . '.nws_generic_text',
					'type'             => 'nws_generic_text',
					/* translators: %S the field name */
					'name'             => sprintf( __( '%s (as text)', 'wp-sync-for-notion' ), $field->get_name() ),
					'nws_generic_text' => $value ?? '',
				)
			);
		} elseif ( 'number' === $return_type ) {
			if ( false !== $convert_to ) {
				try {
					if ( is_scalar( $value ) ) {
						$value = floatval( $value );
					} else {
						$value = null;
					}
				} catch ( \Throwable $exception ) {
					$value = null;
				}
			}
			$equivalent_field = new Notion_WP_Sync_Generic_Number_Field(
				(object) array(
					'id'                 => $field->get_id() . '.nws_generic_number',
					'type'               => 'nws_generic_number',
					/* translators: %S the field name */
					'name'               => sprintf( __( '%s (as number)', 'wp-sync-for-notion' ), $field->get_name() ),
					'nws_generic_number' => $value ?? 0,
				)
			);
		} elseif ( 'boolean' === $return_type ) {
			if ( false !== $convert_to ) {
				try {
					$value = ! ! $value;
				} catch ( \Throwable $exception ) {
					$value = null;
				}
			}
			$equivalent_field = new Notion_WP_Sync_Generic_Boolean_Field(
				(object) array(
					'id'                  => $field->get_id() . '.nws_generic_boolean',
					'type'                => 'nws_generic_boolean',
					/* translators: %S the field name */
					'name'                => sprintf( __( '%s (as boolean)', 'wp-sync-for-notion' ), $field->get_name() ),
					'nws_generic_boolean' => $value ?? false,
				)
			);
		} else {
			try {
				$value = (string) $value;
			} catch ( \Throwable $exception ) {
				$value = null;
			}
			$equivalent_field = new Notion_WP_Sync_Generic_Text_Field(
				(object) array(
					'id'               => $field->get_id() . '.nws_generic_text',
					'type'             => 'nws_generic_text',
					/* translators: %S the field name */
					'name'             => sprintf( __( '%s (unknown return type)', 'wp-sync-for-notion' ), $field->get_name() ),
					'nws_generic_text' => $value ?? '',
				)
			);
		}

		return $equivalent_field;
	}

	/**
	 * From a list of pages, find one that has the expected field with a formula type & value.
	 *
	 * @param Notion_WP_Sync_Page_Model[] $pages The pages.
	 * @param string                      $field_id The field id.
	 *
	 * @return Notion_WP_Sync_Page_Model|false;
	 */
	protected static function get_first_page_with_field( $pages, $field_id ) {
		$found_page = false;
		foreach ( $pages as $page ) {
			$field              = $page->get_field( $field_id );
			$data_raw           = $field->get_raw_value();
			list($type, $value) = self::get_type_and_value_from_field_data_raw( $data_raw );
			if ( $type && $value ) {
				$found_page = $page;
			}
		}
		return $found_page;
	}

	/**
	 * Extract formula field type and value.
	 *
	 * @param \stdClass $data_raw Raw data from the field.
	 *
	 * @return array Type and value
	 */
	protected static function get_type_and_value_from_field_data_raw( $data_raw ) {
		$type  = $data_raw->type ?? '';
		$value = $data_raw->{$type} ?? null;
		return array( $type, $value );
	}

	/**
	 * Build equivalent formula filters based on supported return types.
	 *
	 * @param array                         $filters Filter list.
	 * @param Notion_WP_Sync_Database_Model $database Database model object.
	 *
	 * @return mixed
	 */
	public static function add_equivalent_filters( $filters, $database ) {
		$filters = array_reduce(
			$filters,
			function ( $result, $filter ) {
				// Replace formula filter by equivalent filters for each supported filter_type.
				if ( $filter && 'formula' === $filter->filter_type ) {
					$filter_types = array(
						'checkbox'  => __( 'checkbox', 'wp-sync-for-notion' ),
						'date'      => __( 'date', 'wp-sync-for-notion' ),
						'number'    => __( 'number', 'wp-sync-for-notion' ),
						'rich_text' => __( 'text', 'wp-sync-for-notion' ),
					);
					foreach ( $filter_types as $filter_type => $filter_type_name ) {
						$filter_type_filter      = clone $filter;
						$filter_type_filter->id .= '::' . $filter_type;
						/* translators: 1 field's name, 2: filter type */
						$filter_type_filter->name        = sprintf( __( '%1$s (%2$s)', 'wp-sync-for-notion' ), $filter_type_filter->name, $filter_type_name );
						$filter_type_filter->filter_type = $filter_type;
						$result[]                        = $filter_type_filter;
					}
				} else {
					$result[] = $filter;
				}

				return $result;
			},
			array()
		);
		return $filters;
	}
}
