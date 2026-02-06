<?php
/**
 * Manages import as meta.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Post_Meta_Destination class.
 */
class Notion_WP_Sync_Post_Meta_Destination extends Notion_WP_Sync_Abstract_Post_Destination {
	/**
	 * Destination slug.
	 *
	 * @var string
	 */
	protected $slug = 'postmeta';

	/**
	 * Image formatter dependency.
	 *
	 * @var Notion_WP_Sync_Image_Formatter
	 */
	protected $image_formatter;

	/**
	 * Constructor
	 *
	 * @param Notion_WP_Sync_Image_Formatter $image_formatter Image formatter dependency.
	 */
	public function __construct( $image_formatter ) {
		parent::__construct();

		$this->image_formatter = $image_formatter;

		add_action( 'notionwpsync/import_record_after', array( $this, 'add_metas' ), 10, 4 );
		add_action( 'notionwpsync/metabox_mapping_wordpress_after', array( $this, 'add_mapping_template' ) );
	}

	/**
	 * Handle post meta importing.
	 *
	 * @param Notion_WP_Sync_Abstract_Importer $importer Importer.
	 * @param array                            $fields Fields.
	 * @param Notion_WP_Sync_Abstract_Model    $record The Notion object.
	 * @param int                              $post_id The post id.
	 */
	public function add_metas( $importer, $fields, $record, $post_id ) {
		$mapped_fields = $this->get_destination_mapping( $importer, $fields );
		foreach ( $mapped_fields as $mapped_field ) {
			$notion_field = $fields[ $mapped_field['notion'] ];
			if ( ! ( $notion_field instanceof Notion_WP_Sync_Field_Interface ) ) {
				continue;
			}
			// Get meta value.
			$value = $this->format( $notion_field, $mapped_field, $importer, $post_id );
			// Get meta key.
			$key = '';
			if ( '_thumbnail_id' === $mapped_field['wordpress'] ) {
				$key = $mapped_field['wordpress'];
			} elseif ( ! empty( $mapped_field['options']['name'] ) ) {
				$key = $mapped_field['options']['name'];
			}
			// Save meta.
			if ( ! empty( $key ) ) {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Output admin extra settings HTML
	 */
	public function add_mapping_template() {
		static $field_cache_buster;
		if ( ! isset( $field_cache_buster ) ) {
			$field_cache_buster = 0;
		}
		++$field_cache_buster;
		// phpcs:ignore ?>
		<template x-if="field.wordpress === 'meta::custom_field' && <?php echo $field_cache_buster ?> > 0">
			<div class="notionwpsync-field form-required">
				<label for="customfield[]">
					<span><?php esc_html_e( 'Custom Field', 'wp-sync-for-notion' ); ?></span>
					<span class="notionwpsync-required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php esc_html_e( '(required)', 'wp-sync-for-notion' ); ?></span>
				</label>
				<input x-model="field.options.name" type="text" name="customfield[]" class="regular-text ltr">
			</div>
		</template>
		<?php
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
			'custom_field',
		);

		if ( post_type_supports( $post_type, 'thumbnail' ) ) {
			$features[] = '_thumbnail_id';
		}

		return $features;
	}

	/**
	 * Assign fields to mapping group
	 */
	protected function get_group() {
		return array(
			'slug' => 'post',
		);
	}

	/**
	 * Get mapping fields
	 */
	protected function get_mapping_fields() {
		return array(
			array(
				'value'                 => '_thumbnail_id',
				'label'                 => __( 'Featured Image', 'wp-sync-for-notion' ),
				'enabled'               => true,
				'supported_value_types' => array( Notion_WP_Sync_Support_Files_Value::class ),
			),
			array(
				'value'                 => 'custom_field',
				'label'                 => __( 'Custom Field... (Pro version)', 'wp-sync-for-notion' ),
				'enabled'               => false,
				'allow_multiple'        => true,
				'supported_value_types' => array(
					Notion_WP_Sync_Support_String_Value::class,
					Notion_WP_Sync_Support_HTML_Value::class,
					Notion_WP_Sync_Support_Files_Value::class,
					Notion_WP_Sync_Support_Float_Value::class,
					Notion_WP_Sync_Support_Boolean_Value::class,
				),
			),
		);
	}

	/**
	 * Format imported value
	 *
	 * @param Notion_WP_Sync_Field_Interface   $notion_field The Notion field.
	 * @param array                            $mapped_field Mapped field config.
	 * @param Notion_WP_Sync_Abstract_Importer $importer Importer.
	 * @param int                              $post_id The post id.
	 *
	 * @TODO: double check return value?
	 * @return int|mixed|null
	 */
	protected function format( $notion_field, $mapped_field, $importer, $post_id ) {
		$destination = $mapped_field['wordpress'];

		if ( '_thumbnail_id' === $destination ) {
			$value = $this->image_formatter->format( $notion_field, $importer, $post_id );
		} else {
			$notion_field_class = get_class( $notion_field );
			$default_type       = call_user_func( $notion_field_class . '::get_default_value_type' );
			$value              = $notion_field->get_value(
				$default_type,
				array(
					'post_id'  => $post_id,
					'importer' => $importer,
				)
			);

			if ( is_bool( $value ) ) {
				// Convert boolean to 0|1.
				$value = (int) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			}
		}

		return $value;
	}
}
