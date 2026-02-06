<?php
/**
 * Basic dependency injection container.
 *
 * Loosely based on https://github.com/kanian/containerx/.
 * + psr/container but with support to PHP 7.0.
 *
 * @package Notion_Wp_Sync
 */

namespace Notion_Wp_Sync;

/**
 * Notion_WP_Sync_Services class.
 */
class Notion_WP_Sync_Services {
	/**
	 * Notion_WP_Sync_Services instance
	 *
	 * @var Notion_WP_Sync_Services $instance
	 */
	private static $instance;

	/**
	 * Returns Notion_WP_Sync_Services instance
	 *
	 * @return Notion_WP_Sync_Services
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registered dependencies
	 *
	 * @var array
	 */
	protected $instances = array();

	/**
	 * Sets a dependency.
	 *
	 * @param string $dependency_key Dependency key.
	 * @param mixed  $dependency_object Dependency object.
	 * @return void
	 */
	public function set( $dependency_key, $dependency_object = null ) {
		$this->instances[ $dependency_key ] = $dependency_object;
	}

	/**
	 * Finds a dependency by its identifier and returns it.
	 *
	 * @param string $dependency Identifier of the dependency to look for.
	 *
	 * @throws \Exception  "{$dependency} not found".
	 *
	 * @return mixed dependency.
	 */
	public function get( $dependency ) {
		if ( ! $this->has( $dependency ) ) {
			throw new \Exception( esc_html( $dependency . ' not found' ) );
		}
		return $this->instances[ $dependency ];
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * `has($dependency)` returning true does not mean that `get($dependency)` will not throw an exception.
	 *
	 * @param string $dependency Identifier of the entry to look for.
	 *
	 * @return boolean
	 */
	public function has( $dependency ) {
		return isset( $this->instances[ $dependency ] );
	}

	/**
	 * Removes an entry from the container
	 *
	 * @param string $dependency Identifier of the entry to look for.
	 *
	 * @return void
	 */
	public function unset( $dependency ) {
		unset( $this->instances[ $dependency ] );
	}
}
