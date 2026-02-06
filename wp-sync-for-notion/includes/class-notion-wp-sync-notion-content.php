<?php
/**
 * Manages shortcode content storage as "ntwpsync-content" post type.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Notion_Content.
 */
class Notion_WP_Sync_Notion_Content {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'notion_wp_sync_content', self::class . '::notion_content_shortcode' );
		add_filter( 'manage_ntwpsync-content_posts_columns', self::class . '::admin_table_columns', 10, 1 );
		add_action( 'manage_ntwpsync-content_posts_custom_column', self::class . '::admin_table_columns_html', 10, 2 );
		add_action( 'rest_api_init', self::class . '::register_notion_record_id_meta' );
	}

	/**
	 * Register "notion_content_shortcode" shortcode.
	 * This shortcode display the content of the "ntwpsync-content" post type defined by the "id" attribute.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public static function notion_content_shortcode( $atts ) {
		if ( ! isset( $atts['id'] ) ) {
			return '';
		}

		$query = new \WP_Query(
			array(
				'post_type'  => 'ntwpsync-content',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					array(
						'key'   => '_notion_wp_sync_record_id',
						'value' => $atts['id'],
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		return apply_filters( 'the_content', $query->posts[0]->post_content );
	}

	/**
	 * Register "ntwpsync-content" post type.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			'ntwpsync-content',
			array(
				'labels'          => array(
					'name'          => __( 'Notion content', 'wp-sync-for-notion' ),
					'singular_name' => __( 'Notion content', 'wp-sync-for-notion' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=nwpsync-connection',
				'menu_position'   => 0,
				'show_in_rest'    => true,
				'supports'        => array( 'title', 'editor', 'author', 'excerpt', 'thumbnail', 'custom-fields' ),
				'capability_type' => 'post',
				'capabilities'    => array(
					'create_posts' => false,
				),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Add shortcode columns to the Notion content table.
	 *
	 * @param string[] $columns An associative array of column headings.
	 *
	 * @return mixed
	 */
	public static function admin_table_columns( $columns ) {
		$columns['shortcode'] = __( 'Shortcode', 'wp-sync-for-notion' );

		return $columns;
	}

	/**
	 * Render the shortcode column added by admin_table_columns.
	 *
	 * @param string $column_name The name of the column to display.
	 * @param int    $post_id The current post ID.
	 *
	 * @return void
	 */
	public static function admin_table_columns_html( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'shortcode':
				echo esc_html( '[notion_wp_sync_content id="' . esc_attr( get_post_meta( $post_id, '_notion_wp_sync_record_id', true ) ) . '"]' );
				break;
		}
	}

	/**
	 * Register "_notion_wp_sync_record_id" field in Rest API.
	 *
	 * @return void
	 */
	public static function register_notion_record_id_meta() {
		register_rest_field(
			'ntwpsync-content',
			'_notion_wp_sync_record_id',
			array(
				'get_callback' => self::class . '::get_notion_record_id_meta',
				'schema'       => array(
					'description' => 'Notion record id',
					'type'        => 'string',
				),
			)
		);
	}

	/**
	 * Populate the field "_notion_wp_sync_record_id" in Rest API.
	 *
	 * @param array            $post Post.
	 * @param string           $field_name Field name.
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return mixed
	 */
	public static function get_notion_record_id_meta( $post, $field_name, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return get_post_meta( $post['id'], $field_name, true );
	}
}
