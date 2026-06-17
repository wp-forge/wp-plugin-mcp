<?php
/**
 * Plugin Name: WordPress MCP
 * Plugin URI: https://github.com/wp-forge/wp-plugin-mcp
 * Description: A friendly Model Context Protocol endpoint for WordPress sites.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: WP Scholar
 * License: GPL-2.0-or-later
 * Text Domain: wp-plugin-mcp
 *
 * @package WP_Forge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_FORGE_MCP_VERSION', '0.1.0' );
define( 'WP_FORGE_MCP_FILE', __FILE__ );
define( 'WP_FORGE_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_FORGE_MCP_URL', plugin_dir_url( __FILE__ ) );

$autoload = WP_FORGE_MCP_DIR . 'vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
} else {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WordPress MCP needs Composer dependencies. Run composer install in the plugin directory.', 'wp-plugin-mcp' ) . '</p></div>';
		}
	);
	return;
}

WP_Forge\Plugin::instance()->init();
