<?php
/**
 * Manages connection to the Notion API.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

use Exception;

/**
 * Notion_WP_Sync_Notion_Api_Client class.
 */
class Notion_WP_Sync_Notion_Api_Client {
	/**
	 * API endpoint.
	 *
	 * @var string
	 */
	protected $base_url = 'https://api.notion.com/v1';

	/**
	 * Notion version (see https://developers.notion.com/reference/versioning).
	 *
	 * @var string
	 */
	protected $notion_version = '2022-06-28';


	/**
	 * Authentication Token.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Cache objects locally, it should be the same data within the request.
	 *
	 * @var array
	 */
	protected static $cache_objects = array();

	/**
	 * Constructor
	 *
	 * @param string $token Authentication Token.
	 */
	public function __construct( $token ) {
		$this->token = $token;
	}

	/**
	 * Get object from cache.
	 *
	 * @param string     $object_type Object type (e.g. "database").
	 * @param string|int $key Object key.
	 *
	 * @return mixed|false
	 */
	protected function get_cache( $object_type, $key ) {
		if ( defined( 'NOTION_WP_SYNC_SKIP_API_CACHE' ) && NOTION_WP_SYNC_SKIP_API_CACHE ) {
			return false;
		}
		$full_object_key = 'notionwpsync_api_cache_' . $object_type . '_' . $key;
		if ( 'database' === $object_type ) {
			return get_transient( $full_object_key );
		}
		return self::$cache_objects[ $full_object_key ] ?? false;
	}

	/**
	 * Put object in cache.
	 *
	 * @param string     $object_type Object type (e.g. "database").
	 * @param string|int $key Object key.
	 * @param mixed      $value Object value.
	 *
	 * @return void
	 */
	protected function set_cache( $object_type, $key, $value ) {
		$full_object_key = 'notionwpsync_api_cache_' . $object_type . '_' . $key;
		if ( 'database' === $object_type ) {
			set_transient( $full_object_key, $value, MINUTE_IN_SECONDS );
		}
		self::$cache_objects[ $full_object_key ] = $value;
	}

	/**
	 * List databases.
	 *
	 * @param array  $options Endpoint options.
	 * @param string $term Search term.
	 * @param int    $limit Max results to return or all if 0.
	 *
	 * @return Notion_WP_Sync_Database_Model[]
	 */
	public function list_databases( $options = array( 'page_size' => 50 ), $term = '', $limit = 0 ) {
		$args = array_merge(
			array(
				'filter' => array(
					'value'    => 'database',
					'property' => 'object',
				),
			),
			$options
		);

		if ( ! empty( $term ) ) {
			$args['query'] = $term;
		}

		$databases = $this->search( $args, $limit );

		$databases = array_map(
			function ( $database_data ) {
				return new Notion_WP_Sync_Database_Model( $database_data );
			},
			$databases
		);

		return $databases;
	}

	/**
	 * List pages.
	 *
	 * @param array  $options Endpoint options.
	 * @param string $term Search term.
	 * @param int    $limit Max results to return or all if 0.
	 *
	 * @return Notion_WP_Sync_Page_Model[]
	 */
	public function list_pages( $options = array( 'page_size' => 50 ), $term = '', $limit = 0 ) {
		$args = array_merge(
			array(
				'filter' => array(
					'value'    => 'page',
					'property' => 'object',
				),
			),
			$options
		);

		if ( ! empty( $term ) ) {
			$args['query'] = $term;
		}

		$pages = $this->search( $args, $limit );

		$pages = array_map(
			function ( $page_data ) {
				return new Notion_WP_Sync_Page_Model( $page_data );
			},
			$pages
		);

		$pages = array_filter(
			$pages,
			function ( $page ) {
				return $page->get_name() !== '';
			}
		);

		return array_values( $pages );
	}


	/**
	 * Search through Notion databases and pages.
	 *
	 * @param array $args Endpoint args.
	 * @param int   $limit Max results to return or all if 0.
	 *
	 * @return array
	 */
	public function search( $args, $limit ) {
		return $this->all_results(
			function ( $options ) use ( $args ) {
				return $this->make_api_request( '/search', array_merge( $args, $options ), 'POST' );
			},
			$limit
		);
	}

	/**
	 * Retrieve all records based on cursor.
	 *
	 * @param callable $api_call API to call.
	 * @param int      $limit Max results to return or all if 0.
	 *
	 * @return array
	 */
	protected function all_results( $api_call, $limit = 0 ) {
		$start_cursor = null;
		$items        = array();
		do {
			$options = array();
			if ( ! is_null( $start_cursor ) ) {
				$options['start_cursor'] = $start_cursor;
			}

			$response = call_user_func( $api_call, $options );

			if ( ! is_wp_error( $response ) ) {
				$items        = array_merge( $items, isset( $response->results ) ? $response->results : array() );
				$start_cursor = isset( $response->next_cursor ) ? $response->next_cursor : '';
				if ( $limit > 0 && count( $items ) >= $limit ) {
					$items = array_slice( $items, 0, $limit );
					break;
				}
				usleep( 500000 );
			}
		} while (
			! is_wp_error( $response )
			&& isset( $response->has_more )
			&& (bool) $response->has_more
		);

		return $items;
	}

	/**
	 * Returns all pages from a database or a subset if $limit > 0.
	 *
	 * @param string $database_id The database id.
	 * @param array  $extra_args Filter and page_size.
	 * @param int    $limit Max results to return or all if 0.
	 *
	 * @return Notion_WP_Sync_Page_Model[]
	 */
	public function list_database_pages( $database_id, $extra_args = array(), $limit = 0 ) {
		$args      = array(
			'page_size' => 50,
		);
		$args      = array_merge( $args, $extra_args );
		$cache_key = $database_id . '_' . wp_json_encode( $args ) . '-' . $limit;
		if ( $this->get_cache( 'list_database_pages', $cache_key ) ) {
			return $this->get_cache( 'list_database_pages', $cache_key );
		}
		$endpoint = sprintf( '/databases/%s/query', $database_id );
		$pages    = $this->all_results(
			function ( $options ) use ( $endpoint, $args ) {
				return $this->make_api_request( $endpoint, array_merge( $args, $options ), 'POST' );
			},
			$limit
		);

		$pages = array_map(
			function ( $page_data ) {
				return new Notion_WP_Sync_Page_Model( $page_data );
			},
			$pages
		);

		$this->set_cache( 'list_database_pages', $cache_key, $pages );

		return $pages;
	}

	/**
	 * Get specific database.
	 *
	 * @param string $database_id The database id.
	 * @param array  $options Options (e.g. "enable_relation_field").
	 *
	 * @return Notion_WP_Sync_Database_Model
	 * @throws Exception API Exception.
	 */
	public function get_database( $database_id, $options = array() ) {
		$cache_key = $database_id . '_' . wp_json_encode( $options );
		if ( $this->get_cache( 'database', $cache_key ) ) {
			return $this->get_cache( 'database', $cache_key );
		}
		$database = $this->make_api_request( sprintf( '/databases/%s', $database_id ) );
		$database = new Notion_WP_Sync_Database_Model( $database );
		$database = apply_filters( 'notionwpsync/notion-api-client/get-database', $database, $this, $options );
		$this->set_cache( 'database', $cache_key, $database );
		return $database;
	}

	/**
	 * Get specific page.
	 *
	 * @param string $page_id The page id.
	 *
	 * @return Notion_WP_Sync_Page_Model
	 * @throws Exception API Exception.
	 */
	public function get_page( $page_id ) {
		if ( $this->get_cache( 'page', $page_id ) ) {
			return $this->get_cache( 'page', $page_id );
		}
		$page = $this->make_api_request( sprintf( '/pages/%s', $page_id ) );
		$page = new Notion_WP_Sync_Page_Model( $page );
		$page = apply_filters( 'notionwpsync/notion-api-client/get-page', $page, $this );
		$this->set_cache( 'page', $page->get_id(), $page );
		return $page;
	}

	/**
	 * Get blocks from page id.
	 *
	 * @param string $page_id Page id.
	 *
	 * @return array
	 */
	public function get_blocks( $page_id ) {
		$args     = array(
			'page_size' => 50,
		);
		$endpoint = sprintf( '/blocks/%s/children', $page_id );
		$blocks   = $this->all_results(
			function ( $options ) use ( $endpoint, $args ) {
				return $this->make_api_request( $endpoint, array_merge( $args, $options ) );
			}
		);

		foreach ( $blocks as $block ) {
			if ( $block->has_children && 'child_page' !== $block->type ) {
				$block->children = $this->get_blocks( $block->id );
			}
		}

		return $blocks;
	}

	/**
	 * Returns Notion users.
	 *
	 * @param array $args Endpoint args.
	 *
	 * @return array
	 */
	public function get_users( $args = array() ) {
		return $this->all_results(
			function ( $options ) use ( $args ) {
				return $this->make_api_request( '/users', array_merge( $args, $options ) );
			}
		);
	}

	/**
	 * Return Notion user.
	 *
	 * @param string $user_id User id.
	 *
	 * @return array
	 */
	public function get_user( $user_id ) {
		if ( $this->get_cache( 'user', $user_id ) ) {
			return $this->get_cache( 'user', $user_id );
		}
		$user = $this->make_api_request( sprintf( '/users/%s', $user_id ) );
		$this->set_cache( 'user', $user_id, $user );
		return $user;
	}

	/**
	 * Performs API request
	 *
	 * @param string $url API URL.
	 * @param array  $data Data.
	 * @param string $type Method.
	 *
	 * @return mixed
	 * @throws Exception API Exception.
	 */
	protected function make_api_request( $url, $data = array(), $type = 'GET' ) {
		$url = $this->base_url . $url;

		if ( 'POST' === $type ) {
			$data = wp_json_encode( $data );
			if ( false === $data ) {
				throw new Exception( 'Cannot encode body in JSON' );
			}
		}
		$args     = $this->get_request_args( array( 'body' => $data ) );
		$response = 'POST' === $type ? wp_remote_post( $url, $args ) : wp_remote_get( $url, $args );

		return $this->validate_response( $response );
	}

	/**
	 * Build request args.
	 *
	 * @param array $args Request args.
	 *
	 * @return array
	 */
	protected function get_request_args( $args = array() ) {
		return array_merge(
			array(
				'headers' => array(
					'Authorization'  => 'Bearer ' . $this->token,
					'Content-Type'   => 'application/json',
					'Notion-Version' => $this->notion_version,
				),
				'timeout' => 15,
			),
			$args
		);
	}

	/**
	 * Validate HTTP Response and returns data
	 *
	 * @param array|WP_Error $response The API request réponse.
	 *
	 * @return mixed
	 * @throws Exception API Exception.
	 * @throws Exception Notion API: Could not decode JSON response.
	 */
	protected function validate_response( $response ) {
		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( sprintf( 'Notion API: %s', $response->get_error_message() ) ) );
		}
		// Check HTTP code.
		$reponse_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $reponse_code ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body );
			if ( ! empty( $data->error ) ) {
				throw new Exception( esc_html( sprintf( 'Notion API: %s', $this->get_error_message( $data ) ) ), intval( $reponse_code ) );
			}
			throw new Exception( esc_html( sprintf( 'Notion API: Received HTTP Error, code %s', $reponse_code ) ), intval( $reponse_code ) );
		}
		// Get JSON data from request body.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );
		$data = Notion_WP_Sync_Helpers::maybe_convert_emoji( $data, 'options', 'option_value' );
		if ( is_null( $data ) ) {
			throw new Exception( 'Notion API: Could not decode JSON response' );
		}
		return $data;
	}

	/**
	 * Get error message from Notion response.
	 *
	 * @TODO: test errors https://developers.notion.com/reference/errors
	 *
	 * @param \stdClass $data Error data.
	 *
	 * @return mixed|string
	 */
	protected function get_error_message( $data ) {
		$message = 'No error message';
		if ( ! empty( $data->message ) ) {
			$message = $data->message;
		}
		return $message;
	}

	/**
	 * Make sure filters structure is fine.
	 * Return false if there is a problem with the structure.
	 *
	 * @param array $group A group of filters.
	 *
	 * @return array|false
	 */
	public function deep_sanitize_filters( $group ) {
		$result   = array();
		$operator = sanitize_text_field( $group['operator'] );
		$operator = in_array( $operator, array( 'and', 'or' ), true ) ? $operator : 'and';

		if ( ! isset( $group['filters'] ) || ! is_array( $group['filters'] ) ) {
			$group['filters'] = array();
		}

		$result[ $operator ] = array_map(
			function ( $filter ) {
				if ( isset( $filter['filters'] ) || isset( $filter['operator'] ) ) {
					if ( ! isset( $filter['filters'] ) || empty( $filter['filters'] ) ) {
						return false;
					}
					return $this->deep_sanitize_filters( $filter );
				} else {
					// TODO: check property exists.
					$property    = $filter['property'];
					$comparison  = sanitize_text_field( $filter['comparison'] );
					$value       = sanitize_text_field( $filter['value'] );
					$type        = sanitize_text_field( $filter['type'] );
					$filter_type = sanitize_text_field( $filter['filter_type'] );
					$sub_type    = null;
					if ( strpos( $property, '::' ) !== false ) {
						list($property, $sub_type) = explode( '::', $property );
						$filter_type               = $type;
						if ( 'rich_text' === $sub_type ) {
							$sub_type = 'string';
						}
					}
					$data_type     = $sub_type ?? $filter_type;
					$filter_object = array(
						'property' => $property,
					);
					if ( in_array( $comparison, array( 'is_empty', 'is_not_empty' ), true ) || 'checkbox' === $data_type ) {
						$value = true;
						if ( 'checkbox' === $data_type ) {
							$comparison = 'is_empty' === $comparison ? 'does_not_equal' : 'equals';
						}
					} elseif ( 'number' === $data_type ) {
						$value = floatval( $value );
					}
					$filter_object[ $filter_type ] = array();
					if ( $sub_type ) {
						$filter_object[ $filter_type ][ $sub_type ][ $comparison ] = $value;
					} else {
						$filter_object[ $filter_type ][ $comparison ] = $value;
					}
					return $filter_object;
				}
			},
			$group['filters']
		);

		$result[ $operator ] = array_filter( $result[ $operator ] );

		return $result;
	}
}
