<?php
/**
 * Manages people Notion property type.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_People_Field class.
 */
class Notion_WP_Sync_People_Field extends Notion_WP_Sync_Abstract_Field {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected static $type = 'people';

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
	protected $filter_type = 'people';

	/**
	 * {@inheritDoc}
	 */
	public static function register() {
		// Add Object people fields.
		add_filter( 'notionwpsync/notion-model/fields', self::class . '::add_object_people_fields', 10, 2 );
		add_filter( 'notionwpsync/importer/page', self::class . '::populate_object_people_fields', 10, 2 );

		// Add subfields.
		add_filter( 'notionwpsync/notion-model/fields', self::class . '::add_sub_fields', 10, 2 );
	}

	/**
	 * Register Notion field sub fields.
	 *
	 * @param array  $properties_objects Field properties.
	 * @param object $data Notion object data (page / database).
	 *
	 * @return array
	 */
	public static function add_sub_fields( $properties_objects, $data ) {
		$properties_objects = array_reduce(
			$properties_objects,
			function ( $result, $properties_object ) use ( $data ) {
				if ( $properties_object instanceof Notion_WP_Sync_Field_Interface && $properties_object->get_type() === 'people' ) {
					$user = null;
					// Don't expect values here.
					if ( 'database' === $data->object ) {
						$user = (object) array(

							'id'         => '',
							'name'       => '',
							'avatar_url' => '',
							'color'      => '',

						);
					} else {
						$raw_value = $properties_object->get_raw_value();
						if ( is_array( $raw_value ) && count( $raw_value ) > 0 ) {
							$user = $raw_value[0];
						}
					}

					$sub_fields = self::get_sub_fields_from_user( $properties_object->get_id(), $properties_object->get_name(), $user );
					$result     = array_merge( $result, array_values( $sub_fields ) );
				} else {
					$result[] = $properties_object;
				}

				return $result;
			},
			array()
		);

		return $properties_objects;
	}

	/**
	 * Generate people field subfields from Notion user.
	 *
	 * @param string $prop_id Property id.
	 * @param string $prop_name Property name / label.
	 * @param object $user User retrieved from Notion API.
	 *
	 * @return array
	 */
	protected static function get_sub_fields_from_user( $prop_id, $prop_name, $user ) {
		$sub_fields                                   = array();
		$sub_fields[ $prop_id . '.user_id' ]          = new Notion_WP_Sync_Generic_Text_Field(
			(object) array(
				'id'               => $prop_id . '.user_id',
				'type'             => 'nws_generic_text',
				/* translators: %S the field name */
				'name'             => sprintf( __( '%s (id)', 'wp-sync-for-notion' ), $prop_name ),
				'nws_generic_text' => $user->id ?? '',
			)
		);
		$sub_fields[ $prop_id . '.user_name' ]        = new Notion_WP_Sync_Generic_Text_Field(
			(object) array(
				'id'               => $prop_id . '.user_name',
				'type'             => 'nws_generic_text',
				/* translators: %s the field name */
				'name'             => sprintf( __( '%s (name)', 'wp-sync-for-notion' ), $prop_name ),
				'nws_generic_text' => $user->name ?? '',
			)
		);
		$sub_fields[ $prop_id . '.user_avatar_url' ]  = new Notion_WP_Sync_Generic_Text_Field(
			(object) array(
				'id'               => $prop_id . '.user_avatar_url',
				'type'             => 'nws_generic_text',
				/* translators: %s the field name */
				'name'             => sprintf( __( '%s (avatar url)', 'wp-sync-for-notion' ), $prop_name ),
				'nws_generic_text' => $user->avatar_url ?? '',
			)
		);
		$sub_fields[ $prop_id . '.user_avatar_file' ] = new Notion_WP_Sync_Files_Field(
			(object) array(
				'id'    => $prop_id . '.user_avatar_file',
				'type'  => 'files',
				/* translators: the field name */
				'name'  => sprintf( __( '%s (avatar file: will import the file)', 'wp-sync-for-notion' ), $prop_name ),
				'files' => array(
					(object) array(
						'id'   => 'user_avatar_file',
						'name' => $user && isset( $user->name ) ? sanitize_title( $user->name ) : '',
						'file' => (object) array(
							'url' => $user->avatar_url ?? '',
						),
					),
				),
			)
		);

		$sub_fields[ $prop_id . '.user_email' ] = new Notion_WP_Sync_Generic_Text_Field(
			(object) array(
				'id'                => $prop_id . '.user_email',
				'type'              => 'nws_generic_email',
				/* translators: %s the field name */
				'name'              => sprintf( __( '%s (email)', 'wp-sync-for-notion' ), $prop_name ),
				'nws_generic_email' => $user->person->email ?? '',
			)
		);

		return $sub_fields;
	}

	/**
	 * Register empty fake fields for special people properties like Created by & Last edited by.
	 * They will be populated later. @see populate_object_people_fields.
	 *
	 * @param array  $properties_objects Field properties.
	 * @param object $data Notion object data (page / database).
	 *
	 * @return array
	 */
	public static function add_object_people_fields( $properties_objects, $data ) {
		array_unshift(
			$properties_objects,
			new Notion_WP_Sync_People_Field(
				(object) array(
					'id'     => '__notionwpsync_created_by',
					'type'   => 'people',
					'name'   => __( 'Created by', 'wp-sync-for-notion' ),
					'people' => array(
						(object) array(
							'id' => $data->created_by->id,
						),
					),
				)
			),
			new Notion_WP_Sync_People_Field(
				(object) array(
					'id'     => '__notionwpsync_last_edited_by',
					'type'   => 'people',
					'name'   => __( 'Last edited by', 'wp-sync-for-notion' ),
					'people' => array(
						(object) array(
							'id' => $data->last_edited_by->id,
						),
					),
				)
			)
		);
		return $properties_objects;
	}

	/**
	 * Populate pages people fields.
	 *
	 * @param Notion_WP_Sync_Page_Model        $page Page.
	 * @param Notion_WP_Sync_Abstract_Importer $importer Importer.
	 *
	 * @return Notion_WP_Sync_Page_Model
	 */
	public static function populate_object_people_fields( $page, $importer ) {
		$person_fields = array(
			'__notionwpsync_created_by'     => __( 'Created by', 'wp-sync-for-notion' ),
			'__notionwpsync_last_edited_by' => __( 'Last edited by', 'wp-sync-for-notion' ),
		);

		foreach ( $person_fields as $person_field_id => $person_field_label ) {
			// Get all $person_field fields (from the current page + potentially ones from relations fields).
			$person_field_fields = array_filter(
				$page->get_fields(),
				function ( $field ) use ( $person_field_id ) {
					return strpos( $field->get_id(), $person_field_id . '.user_id' ) !== false && $field instanceof Notion_WP_Sync_Generic_Text_Field;
				}
			);

			// For each person field, retrieve the related user from Notion and replace the field/subfields.
			foreach ( $person_field_fields as $person_field_field ) {
				$new_person_field_raw_value = null;

				$user_id = $person_field_field->get_value( Notion_WP_Sync_Support_String_Value::class );
				if ( ! empty( $user_id ) ) {
					try {
						$user                       = $importer->get_api_client()->get_user( $user_id );
						$new_person_field_raw_value = $user;
					} catch ( \Throwable $exception ) {
						if ( $exception->getCode() === 404 ) {
							$importer->log( sprintf( 'Can’t retrieve user with id %s in page %s; the user does not exist or you don’t have permission to read its information ; Full Notion error message: %s.', $user_id, $page->get_id(), $exception->getMessage() ) );
						} else {
							$importer->log( $exception->getMessage() );
						}
					}
				}

				if ( $new_person_field_raw_value ) {
					$fields              = $page->get_fields();
					$person_field_prefix = str_replace( '.user_id', '', $person_field_field->get_id() );
					// Remove $person_field && subfields from fields.
					$fields = array_filter(
						$fields,
						function ( $field ) use ( $person_field_prefix ) {
							return strpos( $field->get_id(), $person_field_prefix ) !== 0;
						}
					);

					// Add ones with proper data.
					$page->set_fields(
						$fields + self::get_sub_fields_from_user( $person_field_prefix, $person_field_label, $new_person_field_raw_value )
					);
				}
			}
		}

		return $page;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $params Extra params.
	 *
	 * @return string
	 */
	public function get_string_value( $params ): string {
		$options = $this->data->select->options ?? array();
		if ( empty( $options ) ) {
			return '';
		}
		return $options[0]->name;
	}
}
