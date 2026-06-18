<?php
/**
 * Main plugin bootstrap.
 *
 * @package WP_Forge
 */

namespace WP_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires WordPress hooks for the MCP plugin.
 */
class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * MCP server.
	 *
	 * @var Server
	 */
	private $server;

	/**
	 * Activity logger.
	 *
	 * @var ActivityLogger
	 */
	private $activity_logger;

	/**
	 * Admin screen.
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
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
		$abilities             = new Abilities();
		$this->activity_logger = new ActivityLogger();
		$this->server          = new Server( $abilities, $this->activity_logger );
		$this->admin           = new Admin( $this->activity_logger );
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this->activity_logger, 'maybe_create_table' ) );
		add_action( 'rest_api_init', array( $this->server, 'register_routes' ) );
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WP_FORGE_MCP_FILE ), array( $this->admin, 'add_settings_link' ) );
	}

	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		$logger = new ActivityLogger();
		$logger->create_table();
	}
}
