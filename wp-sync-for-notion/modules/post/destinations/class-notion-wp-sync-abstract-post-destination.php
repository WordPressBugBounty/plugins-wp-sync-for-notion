<?php
/**
 * Base class to manage post import destination.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Abstract Post Destination
 */
abstract class Notion_WP_Sync_Abstract_Post_Destination extends Notion_WP_Sync_Abstract_Destination {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $module = 'post';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		add_filter( 'notionwpsync/features_by_post_type', array( $this, 'add_features_by_post_type' ), 10, 2 );
	}

	/**
	 * Add field features for each post types
	 *
	 * @param array  $features Features.
	 * @param string $post_type Post type.
	 *
	 * @return array
	 */
	public function add_features_by_post_type( $features, $post_type ) {
		$features[ $this->slug ] = $this->get_features_by_post_type( $post_type );

		return $features;
	}

	/**
	 * Add field features for each post types
	 *
	 * @param string $post_type Post type.
	 *
	 * @return string[]
	 */
	abstract protected function get_features_by_post_type( $post_type );
}
