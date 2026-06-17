<?php
/**
 * Plugin Name: WordPress MCP
 * Plugin URI: https://github.com/wpscholar/wp-plugin-mcp
 * Description: A friendly Model Context Protocol endpoint for WordPress sites.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: WP Scholar
 * License: GPL-2.0-or-later
 * Text Domain: wp-plugin-mcp
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_FORGE_MCP_VERSION', '0.1.0' );
define( 'WP_FORGE_MCP_FILE', __FILE__ );
define( 'WP_FORGE_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_FORGE_MCP_URL', plugin_dir_url( __FILE__ ) );

require_once WP_FORGE_MCP_DIR . 'includes/class-wp-forge-mcp-response.php';
require_once WP_FORGE_MCP_DIR . 'includes/class-wp-forge-mcp-abilities.php';
require_once WP_FORGE_MCP_DIR . 'includes/class-wp-forge-mcp-server.php';
require_once WP_FORGE_MCP_DIR . 'includes/class-wp-forge-mcp-admin.php';
require_once WP_FORGE_MCP_DIR . 'includes/class-wp-forge-mcp-plugin.php';

WP_Forge_MCP_Plugin::instance()->init();
