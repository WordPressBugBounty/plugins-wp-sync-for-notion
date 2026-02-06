<?php
/**
 * Manages import as post.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Post_Destination class.
 */
class Notion_WP_Sync_Post_Destination extends Notion_WP_Sync_Abstract_Post_Destination {
	/**
	 * Destination slug.
	 *
	 * @var string
	 */
	protected $slug = 'post';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		add_filter( 'notionwpsync/import_post_data', array( $this, 'add_to_post_data' ), 20, 4 );
	}

	/**
	 * Handle post data importing
	 *
	 * @param array                            $post_data The post data.
	 * @param Notion_WP_Sync_Abstract_Importer $importer Importer.
	 * @param array                            $fields Fields.
	 * @param Notion_WP_Sync_Abstract_Model    $record The Notion object.
	 */
	public function add_to_post_data( $post_data, $importer, $fields, $record ) {
		$mapped_fields = $this->get_destination_mapping( $importer, $fields );

		foreach ( $mapped_fields as $mapped_field ) {
			$notion_field = $fields[ $mapped_field['notion'] ];
			if ( $notion_field instanceof Notion_WP_Sync_Field_Interface ) {
				$post_data[ $mapped_field['wordpress'] ] = $this->format( $notion_field, $mapped_field, $importer );
			}
		}

		if ( $importer->config()->get( 'object_type' ) === 'page' && is_post_type_hierarchical( $post_data['post_type'] ) && $record->get_parent_id() !== 'workspace' ) {
			try {
				$parent_record  = $importer->get_api_client()->get_page( $record->get_parent_id() );
				$parent_post_id = $importer->get_existing_content_id( $parent_record );
				if ( $parent_post_id ) {
					$post_data['post_parent'] = $parent_post_id;
				}
			} catch ( \Exception $exception ) {
				$importer->log( sprintf( 'The parent page with the id %s can\'t be retrieved: %s', $record->get_parent_id(), $exception->getMessage() ) );
			}
		}

		return $post_data;
	}

	/**
	 * Add field features for each post types
	 *
	 * @param string $post_type Post type.
	 *
	 * @return string[]
	 */
	protected function get_features_by_post_type( $post_type ) {
		$features = array(
			'post_name',
			'post_date',
			'post_status',
		);

		if ( post_type_supports( $post_type, 'title' ) ) {
			$features[] = 'post_title';
		}

		if ( post_type_supports( $post_type, 'editor' ) ) {
			$features[] = 'post_content';
		}

		if ( post_type_supports( $post_type, 'excerpt' ) ) {
			$features[] = 'post_excerpt';
		}

		if ( post_type_supports( $post_type, 'author' ) ) {
			$features[] = 'post_author';
		}

		return $features;
	}

	/**
	 * Assign fields to mapping group.
	 */
	protected function get_group() {
		return array(
			'label' => __( 'Post', 'wp-sync-for-notion' ),
			'slug'  => 'post',
		);
	}

	/**
	 * Get mapping fields.
	 *
	 * @return array
	 */
	protected function get_mapping_fields() {
		return array(
			array(
				'value'                 => 'post_title',
				'label'                 => __( 'Title', 'wp-sync-for-notion' ),
				'enabled'               => true,
				'supported_value_types' => array( Notion_WP_Sync_Support_String_Value::class ),
			),
			array(
				'value'                 => 'post_content',
				'label'                 => __( 'Content', 'wp-sync-for-notion' ),
				'enabled'               => true,
				'supported_value_types' => array( Notion_WP_Sync_Support_HTML_Value::class, Notion_WP_Sync_Support_String_Value::class ),
			),
			array(
				'value'                 => 'post_excerpt',
				'label'                 => __( 'Excerpt', 'wp-sync-for-notion' ),
				'enabled'               => true,
				'supported_value_types' => array( Notion_WP_Sync_Support_String_Value::class ),
			),
			array(
				'value'                 => 'post_name',
				'label'                 => __( 'Slug', 'wp-sync-for-notion' ),
				'enabled'               => true,
				'supported_value_types' => array( Notion_WP_Sync_Support_String_Value::class ),
			),
			array(
				'value'                 => 'post_author',
				'label'                 => __( 'Author', 'wp-sync-for-notion' ),
				'enabled'               => true,
				'supported_value_types' => array( Notion_WP_Sync_Support_Email_Value::class ),
			),
			array(
				'value'                 => 'post_status',
				'label'                 => __( 'Status', 'wp-sync-for-notion' ),
				'enabled'               => true,
				'supported_value_types' => array( Notion_WP_Sync_Support_String_Value::class ),
			),
			array(
				'value'                 => 'post_date',
				'label'                 => __( 'Publication Date', 'wp-sync-for-notion' ),
				'enabled'               => true,
				'supported_value_types' => array( Notion_WP_Sync_Support_DateTime_Value::class ),
			),
		);
	}

	/**
	 * Format imported value
	 *
	 * @param Notion_WP_Sync_Field_Interface   $notion_field The Notion field.
	 * @param array                            $mapped_field Mapped field config.
	 * @param Notion_WP_Sync_Abstract_Importer $importer Importer.
	 *
	 * @return mixed|null
	 */
	protected function format( $notion_field, $mapped_field, $importer ) {
		$destination      = $mapped_field['wordpress'];
		$wp_field_mapping = $this->get_field_mapping( $destination );

		$value                              = '';
		$notion_field_supported_value_types = Notion_WP_Sync_Helpers::get_field_class_supported_value_types( $notion_field );

		if ( $wp_field_mapping ) {
			// Get first supported source value type from the field.
			$value_type = Notion_WP_Sync_Helpers::get_first_supported_source_value_type( $notion_field_supported_value_types, $wp_field_mapping['supported_value_types'] );
			if ( $value_type ) {
				$value = $notion_field->get_value( $value_type, array( 'importer' => $importer ) );
			}
		}

		if ( 'post_date' === $destination && $value instanceof \DateTimeInterface ) {
			$value = $value->format( 'Y-m-d H:i:s' );
		} elseif ( 'post_author' === $destination && is_string( $value ) && ! empty( $value ) ) {
			$user  = get_user_by( 'email', $value );
			$value = $user ? $user->ID : null;
		}

		return $value;
	}
}
