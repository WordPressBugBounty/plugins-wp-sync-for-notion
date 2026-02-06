<?php
/**
 * Abstract class to manage content import and handles cron task.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

use DateTime, DateTimeZone, DateInterval;
use Exception, TypeError;
use WP_Error, WP_CLI;

/**
 * Notion_WP_Sync_Abstract_Importer class.
 */
abstract class Notion_WP_Sync_Abstract_Importer {
	/**
	 * Import infos.
	 *
	 * @var Notion_WP_Sync_Importer_Settings
	 */
	public $infos;

	/**
	 * Saved config for the connection.
	 *
	 * @var Notion_WP_Sync_Importer_Settings
	 */
	public $config;

	/**
	 * Notion API Client.
	 *
	 * @var Notion_WP_Sync_Notion_Api_Client
	 */
	protected $api_client;

	/**
	 * Module.
	 *
	 * @var Notion_WP_Sync_Abstract_Module
	 */
	protected $module;

	/**
	 * API client class factory.
	 *
	 * @var callable
	 */
	protected $api_client_class_factory;

	/**
	 * Constructor.
	 *
	 * @param \WP_Post                       $importer_post_object The connection.
	 * @param Notion_WP_Sync_Abstract_Module $module The module.
	 * @param callable                       $api_client_class_factory The API client class.
	 */
	public function __construct( $importer_post_object, $module, $api_client_class_factory ) {
		$this->module                   = $module;
		$this->api_client_class_factory = $api_client_class_factory;
		$this->load_settings( $importer_post_object );

		add_filter( 'notionwpsync/get_importers', array( $this, 'register' ) );
		$this->init();
		$this->schedule_cron_event();
	}

	/**
	 * Register importer
	 *
	 * @param Notion_WP_Sync_Abstract_Importer[] $importers Importers.
	 */
	public function register( $importers ) {
		$importers[] = $this;
		return $importers;
	}

	/**
	 * Infos getter.
	 */
	public function infos() {
		return $this->infos;
	}

	/**
	 * Config getter.
	 */
	public function config() {
		return $this->config;
	}

	/**
	 * Scheduled Sync next getter.
	 */
	public function get_next_scheduled_sync() {
		return wp_next_scheduled( $this->get_schedule_slug() );
	}

	/**
	 * Fields getter.
	 */
	public function get_notion_fields() {
		return get_post_meta( $this->infos()->get( 'id' ), 'notion_fields', true );
	}

	/**
	 * Run ID getter
	 */
	public function get_run_id() {
		return get_post_meta( $this->infos()->get( 'id' ), 'run', true );
	}


	/**
	 * Cron action.
	 *
	 * @return boolean|WP_Error
	 */
	public function cron() {
		return $this->run();
	}

	/**
	 * Run importer.
	 *
	 * @throws Exception Another instance is already running; terminating.
	 * @return boolean|WP_Error
	 */
	public function run() {
		if ( $this->get_run_id() ) {
			return new WP_Error( 'notion-wp-sync-run-error', __( 'A sync is already running.', 'wp-sync-for-notion' ) );
		}

		try {
			// Define a unique id for this run.
			$run_id = uniqid();

			// Save run.
			update_post_meta( $this->infos()->get( 'id' ), 'run', $run_id );

			$this->log( sprintf( 'Starting importer...' ) );

			// Save table schema.
			update_post_meta( $this->infos()->get( 'id' ), 'notion_fields', $this->get_object_fields() );

			// Loop through all pages.
			$this->get_records( $run_id );

			return true;
		} catch ( Exception $e ) {
			$this->log( $e->getMessage() );
			$this->end_run( 'error', $e->getMessage() );
			return new WP_Error( 'notion-wp-sync-run-error', $e->getMessage() );
		} catch ( TypeError $e ) {
			$this->log( $e->getMessage() );
			$this->end_run( 'error', $e->getMessage() );
			return new WP_Error( 'notion-wp-sync-run-error', $e->getMessage() );
		}
	}

	/**
	 * Log message to file and WPCLI output.
	 *
	 * @param mixed  $message The message or an object to display in the logs.
	 * @param string $level The log level.
	 */
	public function log( $message, $level = 'log' ) {
		if ( ! is_dir( NOTION_WP_SYNC_LOGDIR ) ) {
			wp_mkdir_p( NOTION_WP_SYNC_LOGDIR );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$file = fopen( NOTION_WP_SYNC_LOGDIR . '/' . $this->infos()->get( 'slug' ) . '-' . gmdate( 'Y-m-d' ) . '-' . $this->get_run_id() . '.log', 'a' );
		if ( ! is_string( $message ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
				$message = var_export( $message, true );
			} else {
				$message = 'Notion_WP_Sync_Importer::log, the $message parameter is not a string, to debug the object turn on WP_DEBUG.';
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( $file, "\n" . gmdate( 'Y-m-d H:i:s' ) . ' ' . $level . ' :: ' . $message );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $file );

		if ( class_exists( 'WP_CLI' ) ) {
			$method = method_exists( 'WP_CLI', $level ) ? $level : 'log';
			WP_CLI::$method( $message );
		}

		if ( 'error' === $level && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $message );
		}
	}

	/**
	 * Process a Notion record import.
	 *
	 * @param Notion_WP_Sync_Abstract_Model $record Record from Notion.
	 *
	 * @return int|WP_Error
	 */
	public function process_notion_record( $record ) {
		$current_user    = wp_get_current_user();
		$current_user_id = $current_user->ID;
		// Make sure we always work with the same user/capabilities, so we don't have diff. between import from admin and anonymous webhooks.
		wp_set_current_user( 0 );
		add_filter( 'user_has_cap', array( $this, 'allow_unfiltered_html_for_blocks' ) );
		kses_init();

		$this->log( sprintf( 'Record ID %s', $record->get_id() ) );

		$record = apply_filters( 'notionwpsync/importer/page-expand', $record, $this );

		$return = 0;
		try {
			// Check if we have existing post for this record.
			$post_id = $this->get_existing_content_id( $record );
			if ( $post_id ) {
				$this->log( sprintf( '- Found matching post, ID %s', $post_id ) );
				// Check if we must update it.
				if ( 'add' !== $this->config()->get( 'sync_strategy' ) && $this->needs_update( $post_id, $record ) ) {
					$post_id = $this->import_record( $record, $post_id );
				} else {
					$this->log( sprintf( '- No update needed' ) );
				}
			} else {
				$post_id = $this->import_record( $record );
			}
			$return = $post_id;
		} catch ( Exception $e ) {
			$this->log( $e );
			$return = new WP_Error( 'notion-wp-sync-run-error', $e->getMessage() );
		} catch ( TypeError $e ) {
			$this->log( $e );
			$return = new WP_Error( 'notion-wp-sync-run-error', $e->getMessage() );
		}

		wp_set_current_user( $current_user_id );
		remove_filter( 'user_has_cap', array( $this, 'allow_unfiltered_html_for_blocks' ) );
		kses_init();

		return $return;
	}


	/**
	 * Module getter.
	 *
	 * @return Notion_WP_Sync_Abstract_Module
	 */
	public function get_module() {
		return $this->module;
	}

	/**
	 * Delete other content existing in WP but deleted in Notion
	 */
	abstract public function delete_removed_contents();

	/**
	 * Get object fields from API
	 */
	protected function get_object_fields() {
		$objects_id = $this->config()->get( 'objects_id' );

		$object = null;
		if ( $this->config()->get( 'object_type' ) === 'page' ) {
			$object = $this->get_api_client()->get_page( $objects_id[0] );
		} else {
			$object = $this->get_api_client()->get_database( $objects_id[0], $this->get_import_fields_options() );
		}

		$fields = array();
		if ( $object ) {
			$fields = $object->get_fields();
		}

		return apply_filters( 'notionwpsync/get_object_fields', $fields );
	}

	/**
	 * Get Notion records from API
	 *
	 * @param string $run_id Run id.
	 * @throws \Exception Can't save records chunk, could be due to an invalid utf-8 character (e.g an emoji).
	 * @return void
	 */
	protected function get_records( $run_id ) {
		// Get records.
		$objects_id  = $this->config()->get( 'objects_id' );
		$object_type = $this->config()->get( 'object_type' );
		$records     = array();
		if ( 'database' === $object_type ) {
			$filters = $this->config()->get( 'filters' );
			$options = array();

			if ( ! empty( $filters ) ) {
				$options = array(
					'filter' => $this->get_api_client()->deep_sanitize_filters( $filters[0] ),
				);
			}
			$records  = $this->get_api_client()->list_database_pages( $objects_id[0], $options );
			$importer = $this;
			$records  = array_map(
				function ( $page ) use ( $importer ) {
					return apply_filters( 'notionwpsync/importer/page', $page, $importer );
				},
				$records
			);
		} else {
			$records = $this->get_pages( $objects_id );
			if ( $this->config()->get( 'page_scope' ) === 'includes_children' ) {
				$records = $this->get_pages_children( $records, $records );
			}
		}

		// Loop through all records.
		$chunks = array_chunk( $records, 10 );
		foreach ( $chunks as $chunk ) {
			// Save Notion record as a temporary option.
			$item_id = uniqid( 'notionwpsync-' . $this->infos()->get( 'id' ) . '-run-' . $this->get_run_id() . '-item-' );
			if ( ! update_option( $item_id, $chunk ) ) {
				throw new \Exception( 'Can\'t save records chunk, could be due to an invalid utf-8 character (e.g an emoji)', 500 );
			}
			// Add it to queue.
			as_enqueue_async_action(
				'notionwpsync_process_records',
				array(
					'importer_id' => $this->infos()->get( 'id' ),
					'run_id'      => $run_id,
					'item_id'     => $item_id,
				)
			);
		}
	}

	/**
	 * Returns pages object from pages id.
	 *
	 * @param string[] $pages_id The pages id.
	 *
	 * @return array
	 */
	protected function get_pages( $pages_id ) {
		$importer = $this;
		$pages    = array_map(
			function ( $page_id ) use ( $importer ) {
				$page = $this->get_api_client()->get_page( $page_id );
				return apply_filters( 'notionwpsync/importer/page', $page, $importer );
			},
			$pages_id
		);
		return $pages;
	}

	/**
	 * Get pages children recursively.
	 *
	 * @param Notion_WP_Sync_Page_Model[] $pages A list of pages.
	 * @param Notion_WP_Sync_Page_Model[] $result A list of pages with their children.
	 *
	 * @return array|mixed
	 */
	protected function get_pages_children( $pages, $result = array() ) {
		foreach ( $pages as $page ) {
			$blocks_field = $page->get_field( '__notionwpsync_blocks' );
			if ( $blocks_field ) {
				$children_pages_id = Notion_WP_Sync_Services::get_instance()->get( 'block_parser' )->get_page_children_id( $blocks_field->get_raw_value() );
				if ( count( $children_pages_id ) > 0 ) {
					$children = $this->get_pages( $children_pages_id );
					$result   = array_merge( $result, $children );
					$result   = $this->get_pages_children( $children, $result );
				}
			}
		}
		return $result;
	}


	/**
	 * Get mapped fields
	 *
	 * @param Notion_WP_Sync_Abstract_Model $record Record from Notion.
	 * @return array List of Notion_WP_Sync_Field_Interface or null indexed by Notion field key.
	 */
	protected function get_mapped_fields( $record ) {
		// ... omit keys for empty fields, lets add them with an empty string
		$mapping     = ! empty( $this->config()->get( 'mapping' ) ) ? $this->config()->get( 'mapping' ) : array();
		$notion_keys = array_map(
			function ( $mapping_pair ) {
				return $mapping_pair['notion'];
			},
			$mapping
		);

		$fields = array();
		foreach ( $notion_keys as $notion_key ) {
			$field                 = $record->get_field( $notion_key );
			$fields[ $notion_key ] = $field;
		}

		return apply_filters( 'notionwpsync/import_record_fields', $fields, $this );
	}

	/**
	 * End run
	 *
	 * @param string      $status Status.
	 * @param null|string $error Error message.
	 */
	public function end_run( $status = 'success', $error = null ) {
		global $wpdb;
		$importer_id = $this->infos()->get( 'id' );
		$run_id      = $this->get_run_id();

		// Delete any remaining AS actions.
		$action_ids = \ActionScheduler::store()->query_actions(
			array(
				'hook'                  => 'notionwpsync_process_records',
				'status'                => \ActionScheduler_Store::STATUS_PENDING,
				'partial_args_matching' => 'like',
				'args'                  => array(
					'importer_id' => $importer_id,
					'run_id'      => $run_id,
				),
				'per_page'              => -1,
			)
		);
		foreach ( $action_ids as $action_id ) {
			\ActionScheduler::store()->cancel_action( $action_id );
		}

		// Delete temporary options.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( sprintf( 'notionwpsync-%s-run-%s-', $importer_id, $run_id ) ) . '%'
			)
		);

		// Remove temporary metas.
		update_post_meta( $importer_id, 'content_ids', null );
		update_post_meta( $importer_id, 'post_ids', null ); // backward compatibility.
		update_post_meta( $importer_id, 'run', null );
		update_post_meta( $importer_id, 'notion_fields', null );

		// Update status and error.
		update_post_meta( $importer_id, 'status', $status );
		update_post_meta( $importer_id, 'last_error', $error );

		// Save date if success.
		if ( 'success' === $status ) {
			update_post_meta( $importer_id, 'last_updated', gmdate( 'Y-m-d H:i:s' ) );
		}
	}


	/**
	 * Load importer settings from post object
	 *
	 * @param \WP_Post $post The connection.
	 */
	protected function load_settings( $post ) {
		$infos = array(
			'id'       => $post->ID,
			'slug'     => $post->post_name,
			'title'    => $post->post_title,
			'modified' => $post->post_modified_gmt,
			'hash'     => wp_hash( $post->ID ),
		);

		$this->infos = new Notion_WP_Sync_Importer_Settings( $infos );

		$config       = json_decode( $post->post_content, true );
		$this->config = new Notion_WP_Sync_Importer_Settings( $config );
	}

	/**
	 * Get cron schedule slug
	 */
	protected function get_schedule_slug() {
		return 'notion_wp_sync_importer_' . $this->infos()->get( 'id' );
	}

	/**
	 * Init cron events
	 */
	protected function schedule_cron_event() {
		if ( 'cron' === $this->config()->get( 'scheduled_sync.type' ) && $this->config()->get( 'scheduled_sync.recurrence' ) ) {
			add_action( $this->get_schedule_slug(), array( $this, 'cron' ) );
			if ( false === $this->get_next_scheduled_sync() ) {
				wp_schedule_event( $this->get_schedule_timestamp(), $this->config()->get( 'scheduled_sync.recurrence' ), $this->get_schedule_slug() );
			}
		} elseif ( $this->get_next_scheduled_sync() ) {
				wp_clear_scheduled_hook( $this->get_schedule_slug() );
		}
	}

	/**
	 * Get Schedule timestamp
	 */
	protected function get_schedule_timestamp() {
		$datetime   = new DateTime( 'now', new DateTimeZone( wp_timezone_string() ) );
		$recurrence = $this->config()->get( 'scheduled_sync.recurrence' );
		if ( 'weekly' === $recurrence ) {
			if ( $this->config()->get( 'scheduled_sync.weekday' ) ) {
				$datetime->modify( 'next ' . $this->config()->get( 'scheduled_sync.weekday' ) );
			}
		}
		if ( in_array( $recurrence, array( 'weekly', 'daily' ), true ) ) {
			if ( $this->config()->get( 'scheduled_sync.time' ) ) {
				$time = explode( ':', $this->config()->get( 'scheduled_sync.time' ) );
				$datetime->setTime( $time[0], $time[1] );
			}
		} else {
			$schedules = wp_get_schedules();
			$interval  = isset( $schedules[ $recurrence ] ) ? $schedules[ $recurrence ]['interval'] : HOUR_IN_SECONDS;
			$datetime->add( new DateInterval( 'PT' . $interval . 'S' ) );
		}
		return $datetime->getTimestamp();
	}

	/**
	 * Get or instantiate Notion API client
	 */
	public function get_api_client() {
		if ( null === $this->api_client ) {
			$api_client_class_factory = $this->api_client_class_factory;
			$this->api_client         = $api_client_class_factory( $this->config()->get( 'api_key' ) );
		}
		return $this->api_client;
	}

	/**
	 * Returns the import fields options.
	 *
	 * @return array Import field's options
	 */
	public function get_import_fields_options() {
		$options = array(
			'enable_relation_field' => 'yes' === $this->config()->get( 'enable_relation_field' ),
		);

		return $options;
	}

	/**
	 * Add "unfiltered_html" cap to the current user.
	 *
	 * @param boolean[] $caps User capabilities.
	 *
	 * @return array
	 */
	public function allow_unfiltered_html_for_blocks( $caps ) {
		$caps['unfiltered_html'] = true;
		return $caps;
	}

	/**
	 * Init
	 */
	protected function init() {
	}

	/**
	 * Compare hashes to check if WP object needs update.
	 *
	 * @param int|null                      $existing_object_id The object id from WordPress.
	 * @param Notion_WP_Sync_Abstract_Model $record The Notion object to import.
	 *
	 * @return bool
	 */
	protected function needs_update( $existing_object_id, $record ) {
		if ( defined( 'NOTION_WP_SYNC_FORCE_UPDATES' ) && NOTION_WP_SYNC_FORCE_UPDATES ) {
			return true;
		}
		return Notion_WP_Sync_Helpers::generate_hash( $record, $this->config()->to_array() ) !== $this->get_existing_content_hash( $existing_object_id );
	}

	/**
	 * Import Notion record.
	 *
	 * @param Notion_WP_Sync_Abstract_Model $record The Notion object to import.
	 * @param int|null                      $existing_object_id The object id from WordPress.
	 *
	 * @return int
	 */
	abstract protected function import_record( $record, $existing_object_id = null );

	/**
	 * Get existing content id
	 *
	 * @param Notion_WP_Sync_Abstract_Model $record The Notion object to import.
	 */
	abstract public function get_existing_content_id( $record );

	/**
	 * Get existing content hash
	 *
	 * @param int $existing_object_id The object id from WordPress.
	 */
	abstract protected function get_existing_content_hash( $existing_object_id );
}
