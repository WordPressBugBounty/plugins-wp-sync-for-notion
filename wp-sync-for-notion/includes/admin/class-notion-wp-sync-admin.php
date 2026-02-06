<?php
/**
 * Manages admin pages registration.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

use Notion_Wp_Sync\Notion_WP_Sync_Options;

require_once NOTION_WP_SYNC_PLUGIN_DIR . 'includes/admin/class-notion-wp-sync-admin-connections-list.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'includes/admin/class-notion-wp-sync-admin-connection.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'includes/admin/metaboxes/class-notion-wp-sync-metabox-field-mapping.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'includes/admin/metaboxes/class-notion-wp-sync-metabox-global-settings.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'includes/admin/metaboxes/class-notion-wp-sync-metabox-importer-settings.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'includes/admin/metaboxes/class-notion-wp-sync-metabox-sync-settings.php';
require_once NOTION_WP_SYNC_PLUGIN_DIR . 'includes/admin/metaboxes/class-notion-wp-sync-metabox-import-infos.php';

/**
 * Admin
 */
class Notion_WP_Sync_Admin {
	/**
	 * Plugin settings
	 *
	 * @var Notion_WP_Sync_Options
	 */
	protected $options;
	/**
	 * Constructor
	 *
	 * @param Notion_WP_Sync_Options $options Plugin settings.
	 */
	public function __construct( $options ) {
		$this->options = $options;

		add_action( 'admin_menu', array( $this, 'add_menu' ), 9 );
		add_action( 'admin_menu', array( $this, 'add_doc_menu' ), 11 );
		add_action( 'in_admin_header', array( $this, 'in_admin_header' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_styles_scripts' ) );

		add_filter( 'plugin_action_links_' . NOTION_WP_SYNC_BASENAME, array( $this, 'plugin_action_links' ) );

		new Notion_WP_Sync_Admin_Connections_List();
		new Notion_WP_Sync_Admin_Connection();
	}

	/**
	 * Add menu
	 */
	public function add_menu() {
		add_menu_page(
			__( 'WP Sync for Notion', 'wp-sync-for-notion' ),
			__( 'WP Sync for Notion', 'wp-sync-for-notion' ),
			apply_filters( 'notionwpsync/manage_options_capability', 'manage_options' ),
			'edit.php?post_type=nwpsync-connection',
			false,
			'data:image/svg+xml;base64,data:image/svg+xml;base64,data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxODMuOTkgMTE4Ij48cGF0aCBkPSJNNTAuNjYgOTAuMjQgMjUuMDEgNzUuNThjLS42Mi0uMzYtLjk5LTEuMDEtLjk5LTEuNzJWNDQuMTNjMC0uNzEuMzgtMS4zNi45OS0xLjcybDI2Ljk4LTE1LjQ0Uzk3LjEgNTMuODMgMTMxLjA2LjkzYy0zOC43MyAzNi44My03MS42OSA1LjgzLTc4LS4zNi0uNjQtLjYyLTEuNjItLjc1LTIuMzktLjMxTC45OSAyOC42N2MtLjYyLjM2LS45OSAxLjAxLS45OSAxLjcydjU3LjJjMCAuNzEuMzggMS4zNi45OSAxLjcybDQ5LjM2IDI4LjIzYy45Mi41MyAyLjA5LjI0IDIuNjctLjY0Qzg2Ljg2IDY2LjA4IDEzMS45OCA5MSAxMzEuOTggOTFjLTM5LjAyLTM1Ljc1LTcyLjY2LTcuMDctNzguOTQtMS4wNS0uNjQuNjEtMS42MS43NC0yLjM4LjI5WiIgZmlsbD0iI0ZGRiIgb3BhY2l0eT0iMC44Ii8+PHBhdGggZD0iTTEzMC45NiAxLjA4Qzk3LjEyIDUxLjkgNTIgMjYuOTggNTIgMjYuOThjMzkuMDIgMzUuNzUgNzIuNjYgNy4wNyA3OC45NCAxLjA1LjY0LS42MSAxLjYxLS43NCAyLjM4LS4yOWwyNS42NSAxNC42NmMuNjIuMzYuOTkgMS4wMS45OSAxLjcydjI5LjczYzAgLjcxLS4zOCAxLjM2LS45OSAxLjcybC0yNi45OCAxNS40NHMtNDUtMjcuMDEtNzguOTYgMjUuOWMzOC43My0zNi44MyA3MS41OS01LjY5IDc3LjkxLjUxLjY0LjYyIDEuNjIuNzUgMi4zOS4zMWw0OS42NS0yOC40Yy42Mi0uMzYuOTktMS4wMS45OS0xLjcyVjMwLjM4YzAtLjcxLS4zOC0xLjM2LS45OS0xLjcyTDEzMy42My40M2MtLjkyLS41My0yLjA5LS4yNC0yLjY3LjY0WiIgZmlsbD0iI0ZGRiIgLz48L3N2Zz4K'
		);
		add_submenu_page(
			'edit.php?post_type=nwpsync-connection',
			__( 'All Connections', 'wp-sync-for-notion' ),
			__( 'All Connections', 'wp-sync-for-notion' ),
			apply_filters( 'notionwpsync/manage_options_capability', 'manage_options' ),
			'edit.php?post_type=nwpsync-connection'
		);
		add_submenu_page(
			'edit.php?post_type=nwpsync-connection',
			__( 'Add New', 'wp-sync-for-notion' ),
			__( 'Add New', 'wp-sync-for-notion' ),
			apply_filters( 'notionwpsync/manage_options_capability', 'manage_options' ),
			'post-new.php?post_type=nwpsync-connection'
		);
	}

	/**
	 * Add documentation link to menu
	 */
	public function add_doc_menu() {
		add_submenu_page(
			'edit.php?post_type=nwpsync-connection',
			__( 'Documentation', 'wp-sync-for-notion' ),
			__( 'Documentation', 'wp-sync-for-notion' ),
			apply_filters( 'notionwpsync/manage_options_capability', 'manage_options' ),
			'https://wpconnect.co/notion-wp-sync-documentation/'
		);
	}

	/**
	 * Display plugin header
	 */
	public function in_admin_header() {
		$screen = get_current_screen();
		if ( 'nwpsync-connection' === $screen->post_type ) {
			$view = include NOTION_WP_SYNC_PLUGIN_DIR . 'views/header.php';
			$view();
		}
	}

	/**
	 * Register admin styles and scripts
	 */
	public function register_styles_scripts() {
		/**
		 * TODO: load only on our pages.
		 */
		wp_enqueue_style( 'notion-wp-sync-admin-select2', plugins_url( 'assets/css/select2.min.css', NOTION_WP_SYNC_PLUGIN_FILE ), false, NOTION_WP_SYNC_VERSION );
		wp_enqueue_style( 'notion-wp-sync-admin', plugins_url( 'assets/css/admin-page.css', NOTION_WP_SYNC_PLUGIN_FILE ), array( 'notion-wp-sync-admin-select2' ), NOTION_WP_SYNC_VERSION );
	}

	/**
	 * Show action links on the plugin screen
	 *
	 * @param string[] $actions     An array of plugin action links. By default this can include
	 *                              'activate', 'deactivate', and 'delete'. With Multisite active
	 *                              this can also include 'network_active' and 'network_only' items.
	 *
	 * @return mixed
	 */
	public function plugin_action_links( $actions ) {
		return array_merge(
			$actions,
			array(
				'upgrade' => '<a href="https://wpconnect.co/notion-wordpress-integration/#pricing-plan" target="_blank"><b>' . esc_html__( 'Upgrade to Pro Version', 'wp-sync-for-notion' ) . '</b></a>',
			)
		);
	}
}
