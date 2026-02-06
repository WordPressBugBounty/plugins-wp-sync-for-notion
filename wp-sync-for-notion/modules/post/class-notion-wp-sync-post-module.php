<?php
/**
 * Class to define post module.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

require_once NOTION_WP_SYNC_PLUGIN_DIR . 'modules/post/class-notion-wp-sync-post-helpers.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'modules/post/class-notion-wp-sync-post-importer.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'modules/post/destinations/class-notion-wp-sync-abstract-post-destination.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'modules/post/destinations/class-notion-wp-sync-post-destination.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'modules/post/destinations/class-notion-wp-sync-post-meta-destination.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'modules/post/destinations/class-notion-wp-sync-taxonomy-destination.php';

/**
 * Post Module
 */
class Notion_WP_Sync_Post_Module extends Notion_WP_Sync_Abstract_Module {
	/**
	 * Module slug
	 *
	 * @var string
	 */
	protected $slug = 'post';

	/**
	 * Module name
	 *
	 * @var string
	 */
	protected $name = 'Post';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'admin_enqueue_scripts', array( $this, 'register_styles_scripts' ) );
		add_filter( 'notionwpsync/get_l10n_strings', array( $this, 'add_l10n_strings' ) );
		add_action( 'notionwpsync/register_destination', array( $this, 'register_destinations' ) );
		add_action( 'notionwpsync/connections_list_type_column', array( $this, 'connections_list_type_column' ) );
	}

	/**
	 * Display post type in admin connection columns.
	 *
	 * @param Notion_WP_Sync_Abstract_Importer $importer The importer.
	 * @return void
	 */
	public function connections_list_type_column( $importer ) {
		if ( $importer->get_module() === $this ) {
			$post_type        = $importer->get_post_type();
			$post_type_object = get_post_type_object( $post_type );
			if ( $post_type_object ) {
				echo '<br>';
				esc_html_e( 'Importing as: ', 'wp-sync-for-notion' );
				$menu_icon = $post_type_object->menu_icon ? $post_type_object->menu_icon : 'dashicons-admin-post';
				echo '<span class="dashicons ' . esc_attr( $menu_icon ) . '"></span> ';
				echo esc_html( $post_type_object->labels->name );
			}
		}
	}

	/**
	 * Register admin styles and scripts
	 */
	public function register_styles_scripts() {
		$screen = get_current_screen();
		if ( is_object( $screen ) && 'nwpsync-connection' === $screen->id ) {
			wp_enqueue_script( 'notion-wp-sync-post-hooks', plugins_url( 'modules/post/assets/js/hooks.js', NOTION_WP_SYNC_PLUGIN_FILE ), array( 'notion-wp-sync-admin' ), NOTION_WP_SYNC_VERSION, false );
		}
	}

	/**
	 * Add module l10n strings
	 *
	 * @param string[] $l10n_strings String translations for JS.
	 */
	public function add_l10n_strings( $l10n_strings ) {
		return array_merge(
			$l10n_strings,
			array(
				'deleteActionConfirmation'   => __( 'You have a Custom Post Type declared using this connection. Are you sure to delete it?', 'wp-sync-for-notion' ),
				'slugErrorMessage'           => __( 'Only lowercase alphanumeric characters, dashes, and underscores are allowed.', 'wp-sync-for-notion' ),
				'allowedCptSlugErrorMessage' => __( 'This slug is already in use, please choose another.', 'wp-sync-for-notion' ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_Post $post The post connection.
	 */
	public function render_settings( $post ) {
		$importer     = Notion_WP_Sync_Helpers::get_importer_by_id( Notion_WP_Sync_Helpers::get_importers(), $post->ID );
		$post_types   = array_filter(
			Notion_WP_Sync_Post_Helpers::get_post_types(),
			function ( $post_type ) use ( $importer ) {
				return ( ! $importer || ( $importer->config()->get( 'post_type' ) !== 'custom' || $importer->config()->get( 'post_type_name' ) !== $post_type['value'] ) ) && 'ntwpsync-content' !== $post_type['value'];
			}
		);
		$post_stati   = Notion_WP_Sync_Post_Helpers::get_post_stati();
		$post_authors = Notion_WP_Sync_Post_Helpers::get_post_authors();
		$view         = require __DIR__ . '/views/settings.php';
		$view( $post_types, $post_stati, $post_authors );
	}


	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_Post $post The post connection.
	 */
	public function get_importer_instance( $post ) {
		return new Notion_Wp_Sync_Post_Importer( $post, $this, Notion_WP_Sync_Services::get_instance()->get( 'notion_api_client_class_factory' ) );
	}

	/**
	 * Register destinations
	 */
	public function register_destinations() {
		new Notion_WP_Sync_Post_Destination();
		new Notion_WP_Sync_Post_Meta_Destination( new Notion_WP_Sync_Image_Formatter() );
		new Notion_WP_Sync_Taxonomy_Destination( new Notion_WP_Sync_Terms_Formatter() );
	}

	/**
	 * Get extra config
	 */
	public function get_extra_config() {
		return array(
			'reservedCptSlugs'   => $this->get_reserved_cpt_slugs(),
			'featuresByPostType' => $this->get_features_by_post_type(),
		);
	}

	/**
	 * Get reserved CPT slugs
	 */
	protected function get_reserved_cpt_slugs() {
		return array_values( get_post_types() );
	}

	/**
	 * Get available features by post type
	 */
	protected function get_features_by_post_type() {
		$features = array();
		foreach ( Notion_WP_Sync_Post_Helpers::get_post_types() as $post_type ) {
			$features[ $post_type['value'] ] = apply_filters(
				'notionwpsync/features_by_post_type',
				array(),
				$post_type['value']
			);
		}
		// Default features for custom post type.
		$features['custom'] = array(
			'post' => array(
				'post_name',
				'post_date',
				'post_title',
				'post_excerpt',
				'post_content',
				'post_author',
				'post_status',
			),
			'meta' => array(
				'_thumbnail_id',
				'custom_field',
			),
		);
		// Default features for ntwpsync-content post type.
		$features['ntwpsync-content'] = array(
			'post' => array(
				'post_title',
				'post_content',
			),
		);
		return $features;
	}
}
