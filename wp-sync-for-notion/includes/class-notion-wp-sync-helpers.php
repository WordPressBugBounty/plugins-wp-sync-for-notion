<?php
/**
 * Helper functions.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Helpers class.
 */
class Notion_WP_Sync_Helpers {
	/**
	 * Get properly formatted date for WP, with locale and timezone
	 *
	 * @param int|string $datetime A date as string or timestamp.
	 *
	 * @return string|false
	 */
	public static function get_formatted_date_time( $datetime ) {
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), is_int( $datetime ) ? $datetime : strtotime( $datetime ) );
	}


	/**
	 * Get importer instance from id.
	 * Return false if the imported is not found.
	 *
	 * @param Notion_WP_Sync_Abstract_Importer[] $importers Importers.
	 * @param int                                $id The importet id.
	 *
	 * @return bool|Notion_WP_Sync_Abstract_Importer
	 */
	public static function get_importer_by_id( $importers, $id ) {
		return array_reduce(
			$importers,
			function ( $result, $importer ) use ( $id ) {
				return $importer->infos()->get( 'id' ) === (int) $id ? $importer : $result;
			},
			false
		);
	}

	/**
	 * Get modules
	 */
	public static function get_modules() {
		return apply_filters( 'notionwpsync/get_modules', array() );
	}

	/**
	 * Get module by slug
	 *
	 * @param string $slug The module's slug.
	 */
	public static function get_module_by_slug( $slug ) {
		return self::get_modules()[ $slug ] ?? null;
	}

	/**
	 * Get importers
	 */
	public static function get_importers() {
		return apply_filters( 'notionwpsync/get_importers', array() );
	}

	/**
	 * Get module from importer post object
	 *
	 * @param \WP_Post $importer_post_object The connection.
	 */
	public static function get_importer_module( $importer_post_object ) {
		$config = json_decode( $importer_post_object->post_content, true );
		return $config['module'] ?? 'post';
	}

	/**
	 * Generate hash for given Notion record and config.
	 *
	 * @param Notion_WP_Sync_Abstract_Model $record The Notion object.
	 * @param array                         $config Importer config.
	 *
	 * @return string
	 */
	public static function generate_hash( $record, $config ) {
		$record_json = wp_json_encode( $record );
		return md5( $record_json . wp_json_encode( $config ) );
	}

	/**
	 * Try to extract filename from url (without extension).
	 *
	 * @param string $url The URL to parse.
	 *
	 * @return string|null
	 */
	public static function get_filename_from_url( $url ) {
		if ( ! is_string( $url ) ) {
			return null;
		}
		$url_info = explode( '?', $url );
		$filename = $url_info[0];
		$url_info = explode( '/', $filename );
		$filename = array_pop( $url_info );
		if ( ! $filename ) {
			return null;
		}
		$filename = pathinfo( $filename, PATHINFO_FILENAME );
		$filename = str_replace( '.', '-', $filename );
		return $filename;
	}

	/**
	 * Convert emoji in $data if required ($db_column in $db_table dot not use utf8mb4)
	 *
	 * @param array|string|object $data The data `wp_encode_emoji` will be applied to.
	 * @param string              $db_table The database table name (without the db prefix).
	 * @param string              $db_column The table column name.
	 *
	 * @return array|mixed
	 */
	public static function maybe_convert_emoji( $data, $db_table, $db_column ) {
		global $wpdb;
		// Get charset for the column where the data will be inserted into.
		$charset = $wpdb->get_col_charset( $wpdb->prefix . $db_table, $db_column );

		// If entry detail value column is not utf8mb4, encode emoji.
		if ( 'utf8mb4' !== $charset ) {
			$data = self::deep_convert_emoji( $data );
		}

		return $data;
	}

	/**
	 * Deep wp_encode_emoji.
	 *
	 * @param array|string|object $data The data `wp_encode_emoji` will be applied to.
	 * @param int                 $max_depth Limit of the recursion.
	 *
	 * @return array|mixed
	 */
	public static function deep_convert_emoji( $data, $max_depth = 512 ) {
		--$max_depth;
		if ( $max_depth <= 0 ) {
			return $data;
		}
		if ( is_string( $data ) ) {
			return wp_encode_emoji( $data );
		} elseif ( is_array( $data ) ) {
			$keys_to_delete = array();
			foreach ( $data as $key => $value ) {
				$data[ wp_encode_emoji( $key ) ] = self::deep_convert_emoji( $value, $max_depth );
				if ( wp_encode_emoji( $key ) !== $key ) {
					$keys_to_delete[] = $key;
				}
			}
			foreach ( $keys_to_delete as $key_to_delete ) {
				unset( $data[ $key_to_delete ] );
			}
		} elseif ( is_object( $data ) ) {
			$keys_to_delete = array();
			foreach ( $data as $key => $value ) {
				$data->{wp_encode_emoji( $key )} = self::deep_convert_emoji( $value, $max_depth );
				if ( wp_encode_emoji( $key ) !== $key ) {
					$keys_to_delete[] = $key;
				}
			}
			foreach ( $keys_to_delete as $key_to_delete ) {
				unset( $data->{$key_to_delete} );
			}
		}
		return $data;
	}

	/**
	 * Return first match between Notion field's supported values and destination ones.
	 *
	 * @param array $notion_field_supported_value_types Notion field's value types.
	 * @param array $destination_supported_value_types Destination supported value types.
	 *
	 * @return false|mixed
	 */
	public static function get_first_supported_source_value_type( $notion_field_supported_value_types, $destination_supported_value_types ) {
		$value_type_selected = false;
		foreach ( $destination_supported_value_types as $value_type ) {
			if ( in_array( $value_type, $notion_field_supported_value_types, true ) ) {
				$value_type_selected = $value_type;
				break;
			}
		}
		return $value_type_selected;
	}

	/**
	 * Sanitize field type key (allowed characters a-z_|).
	 *
	 * @param string $key The field type key to sanitize.
	 *
	 * @return array|string|string[]|null
	 */
	public static function sanitize_field_type( $key ) {
		$sanitized_key = '';

		if ( is_scalar( $key ) ) {
			$sanitized_key = strtolower( $key );
			$sanitized_key = preg_replace( '/[^a-z_|]/', '', $sanitized_key );
		}

		return $sanitized_key;
	}

	/**
	 * Returns supported value types for a field object or a field class.
	 *
	 * @param object|string $object_or_class An object (class instance) or a string (class or interface name).
	 *
	 * @return string[]
	 */
	public static function get_field_class_supported_value_types( $object_or_class ) {
		$supported_value_types = class_implements( $object_or_class );
		return apply_filters( 'notionwpsync/field-class-supported-value-types', $supported_value_types, $object_or_class );
	}

	/**
	 * If the value is not an array, convert to array.
	 * If the value is a multidimensional array flatten it.
	 *
	 * @param mixed $value The value to flatten / transform to array.
	 *
	 * @return array
	 */
	public static function flatten_value( $value ) {
		if ( null === $value || '' === $value ) {
			$value = array();
		}
		if ( ! is_array( $value ) ) {
			$value = array( $value );
		} else {
			$value = array_reduce(
				$value,
				function ( $carry, $value_item ) {
					if ( is_array( $value_item ) ) {
						$carry = array_merge( $carry, self::flatten_value( $value_item ) );
					} else {
						$carry[] = $value_item;
					}
					return $carry;
				},
				array()
			);
		}
		return $value;
	}

	/**
	 * Check user access.
	 *
	 * @param string $error_key Error key for the response.
	 *
	 * @return void
	 */
	public static function check_ajax_admin_user_access( $error_key = 'feedback' ) {
		if ( ! current_user_can( apply_filters( 'notionwpsync/manage_options_capability', 'manage_options' ) ) ) {
			wp_send_json_error(
				array(
					'status'   => 'error',
					$error_key => array( __( 'Unauthorized', 'wp-sync-for-notion' ) ),
				),
				403
			);
		}
	}
}
