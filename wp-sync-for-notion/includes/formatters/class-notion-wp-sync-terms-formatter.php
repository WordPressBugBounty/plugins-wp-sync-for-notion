<?php
/**
 * Terms Formatter: import a list of string as terms.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Terms_Formatter class.
 */
class Notion_WP_Sync_Terms_Formatter {
	/**
	 * Importer.
	 *
	 * @var Notion_WP_Sync_Abstract_Importer
	 */
	protected $importer;

	/**
	 * Format source value
	 *
	 * @param string[]|string|null             $value The list of string.
	 * @param Notion_WP_Sync_Abstract_Importer $importer The importer.
	 * @param string                           $taxonomy The taxonomy.
	 * @param boolean                          $split_comma_separated_string_into_terms Should string comma separated value be split into multiple terms.
	 *
	 * @return array
	 */
	public function format( $value, $importer, $taxonomy, $split_comma_separated_string_into_terms = false ) {
		$this->importer = $importer;

		if ( is_null( $value ) ) {
			return array();
		}

		if ( $split_comma_separated_string_into_terms ) {
			if ( ! is_array( $value ) ) {
				$value = array( $value );
			}
			// If the incoming value is a comma-seperated list of values, split the string.
			$value = array_map( array( $this, 'split_comma_separated_string_into_terms' ), $value );
			$value = Notion_WP_Sync_Helpers::flatten_value( $value );
		}

		// Make sure we have an array of terms.
		$values = ! is_array( $value ) ? array( $value ) : $value;

		$terms = array();
		foreach ( $values as $value ) {
			$value = wp_strip_all_tags( $value );
			$term  = term_exists( $value, $taxonomy );
			if ( 0 === $term || null === $term ) {
				$term = wp_insert_term( $value, $taxonomy );
			}

			if ( is_wp_error( $term ) ) {
				$this->log( sprintf( '- Cannot get term \'%s\' (taxonomy: \'%s\'), error: %s', $value, $taxonomy, $term->get_error_message() ) );
			} else {
				$terms[] = (int) $term['term_id'];
			}
		}
		return $terms;
	}

	/**
	 * Split comma separated string into terms.
	 *
	 * @param string|mixed $value The value to split.
	 *
	 * @return array
	 */
	protected function split_comma_separated_string_into_terms( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) ) {
			return array( $value );
		}
		return array_map( 'trim', explode( ',', $value ) );
	}

	/**
	 * Log message.
	 *
	 * @param string $message Message to log.
	 * @param string $level Log level.
	 */
	protected function log( $message, $level = 'log' ) {
		if ( $this->importer ) {
			$this->importer->log( $message, $level );
		}
	}
}
