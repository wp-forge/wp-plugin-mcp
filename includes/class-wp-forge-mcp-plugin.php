<?php
/**
 * Main plugin bootstrap.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires WordPress hooks for the MCP plugin.
 */
class WP_Forge_MCP_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var WP_Forge_MCP_Plugin|null
	 */
	private static $instance = null;

	/**
	 * MCP server.
	 *
	 * @var WP_Forge_MCP_Server
	 */
	private $server;

	/**
	 * Admin screen.
	 *
	 * @var WP_Forge_MCP_Admin
	 */
	private $admin;

	/**
	 * Get singleton instance.
	 *
	 * @return WP_Forge_MCP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$abilities    = new WP_Forge_MCP_Abilities();
		$this->server = new WP_Forge_MCP_Server( $abilities );
		$this->admin  = new WP_Forge_MCP_Admin();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this->server, 'register_routes' ) );
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WP_FORGE_MCP_FILE ), array( $this->admin, 'add_settings_link' ) );
	}
}
