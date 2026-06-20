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
	const SERVER_ID      = 'wp-forge';
	const REST_NAMESPACE = 'mcp';
	const REST_ROUTE     = 'wp-forge';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Ability registry.
	 *
	 * @var Abilities
	 */
	private $abilities;

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
		$this->abilities       = new Abilities();
		$this->activity_logger = new ActivityLogger();
		$this->admin           = new Admin( $this->activity_logger );
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this->activity_logger, 'maybe_create_table' ) );
		add_action( 'plugins_loaded', array( $this, 'bootstrap_mcp_adapter' ) );
		add_action( 'wp_abilities_api_init', array( $this->abilities, 'register_wordpress_abilities' ) );
		add_action( 'mcp_adapter_init', array( $this, 'create_mcp_server' ) );
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

	/**
	 * Initialize the WordPress MCP adapter when it is available.
	 *
	 * @return void
	 */
	public function bootstrap_mcp_adapter() {
		if ( class_exists( '\WP\MCP\Core\McpAdapter' ) && method_exists( '\WP\MCP\Core\McpAdapter', 'instance' ) ) {
			\WP\MCP\Core\McpAdapter::instance();
		}
	}

	/**
	 * Create the MCP server using the WordPress core MCP adapter.
	 *
	 * @param object $adapter MCP adapter instance.
	 * @return void
	 */
	public function create_mcp_server( $adapter ) {
		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
			return;
		}

		$adapter->create_server(
			self::SERVER_ID,
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			__( 'WordPress MCP', 'wp-plugin-mcp' ),
			__( 'A friendly Model Context Protocol endpoint for WordPress sites.', 'wp-plugin-mcp' ),
			defined( 'WP_FORGE_MCP_VERSION' ) ? WP_FORGE_MCP_VERSION : '0.1.0',
			array(
			\WP\MCP\Transport\HttpTransport::class,
			),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			ActivityLogObservabilityHandler::class,
			$this->abilities->get_wordpress_ability_names(),
			array(),
			array()
		);
	}
}
