<?php
/**
 * Lightweight unit tests for WordPress MCP.
 *
 * @package WP_Forge
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WP_FORGE_MCP_VERSION', '0.1.0' );

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

use WP_Forge\Abilities;
use WP_Forge\Plugin;

$registered_abilities = array();
$added_actions        = array();
$added_filters        = array();

if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $name, $args ) {
		global $registered_abilities;
		$registered_abilities[ $name ] = $args;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return 'do_not_allow' !== $capability;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback ) {
		global $added_actions;
		$added_actions[ $hook_name ] = $callback;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback ) {
		global $added_filters;
		$added_filters[ $hook_name ] = $callback;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( $file );
	}
}

if ( ! defined( 'WP_FORGE_MCP_FILE' ) ) {
	define( 'WP_FORGE_MCP_FILE', dirname( __DIR__ ) . '/wp-plugin-mcp.php' );
}

$tests_run = 0;

function assert_true( $condition, $message ) {
	global $tests_run;
	++$tests_run;

	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function assert_same( $expected, $actual, $message ) {
	assert_true( $expected === $actual, $message . ' Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
}

$abilities = new Abilities();
$all       = $abilities->list_abilities();
$names     = array_column( $all, 'name' );

assert_same( 69, count( $all ), 'Expected the WordPress ability catalog.' );
assert_true( in_array( 'wp-forge-posts-search', $names, true ), 'Expected posts search ability.' );
assert_true( in_array( 'wp-forge-get-site-info', $names, true ), 'Expected site info ability.' );
assert_true( in_array( 'wp-forge-run-api-function', $names, true ), 'Expected REST runner ability.' );
assert_true( ! in_array( 'wp-forge-wc-products-search', $names, true ), 'WooCommerce abilities should not be registered.' );

$expected_named_tools = array(
	'wp-forge-posts-search',
	'wp-forge-get-post',
	'wp-forge-add-post',
	'wp-forge-update-post',
	'wp-forge-delete-post',
	'wp-forge-list-taxonomies',
	'wp-forge-list-taxonomy-terms',
	'wp-forge-get-taxonomy-term',
	'wp-forge-save-taxonomy-term',
	'wp-forge-delete-taxonomy-term',
	'wp-forge-pages-search',
	'wp-forge-get-page',
	'wp-forge-add-page',
	'wp-forge-update-page',
	'wp-forge-delete-page',
	'wp-forge-list-media',
	'wp-forge-get-media',
	'wp-forge-get-media-file',
	'wp-forge-upload-media',
	'wp-forge-update-media',
	'wp-forge-delete-media',
	'wp-forge-search-media',
	'wp-forge-list-post-types',
	'wp-forge-cpt-search',
	'wp-forge-get-cpt',
	'wp-forge-add-cpt',
	'wp-forge-update-cpt',
	'wp-forge-delete-cpt',
	'wp-forge-users-search',
	'wp-forge-get-user',
	'wp-forge-add-user',
	'wp-forge-update-user',
	'wp-forge-delete-user',
	'wp-forge-get-general-settings',
	'wp-forge-update-general-settings',
	'wp-forge-get-site-info',
	'wp-forge-list-plugins',
	'wp-forge-install-plugin',
	'wp-forge-activate-plugin',
	'wp-forge-deactivate-plugin',
	'wp-forge-uninstall-plugin',
	'wp-forge-list-themes',
	'wp-forge-install-theme',
	'wp-forge-activate-theme',
	'wp-forge-delete-theme',
	'wp-forge-list-options',
	'wp-forge-get-option',
	'wp-forge-update-option',
	'wp-forge-delete-option',
	'wp-forge-list-comments',
	'wp-forge-get-comment',
	'wp-forge-add-comment',
	'wp-forge-update-comment',
	'wp-forge-delete-comment',
	'wp-forge-approve-comment',
	'wp-forge-spam-comment',
	'wp-forge-get-site-health-info',
	'wp-forge-list-site-health-tests',
	'wp-forge-get-error-log-path',
	'wp-forge-read-error-log',
	'wp-forge-run-wp-cli-command',
	'wp-forge-get-global-styles',
	'wp-forge-update-global-styles',
	'wp-forge-get-active-global-styles',
	'wp-forge-get-active-global-styles-id',
	'wp-forge-get-active-theme',
);

foreach ( $expected_named_tools as $expected_name ) {
	assert_true( in_array( $expected_name, $names, true ), 'Expected WordPress tool: ' . $expected_name );
}

foreach ( $names as $name ) {
	assert_true( 0 === strpos( $name, 'wp-forge-' ), 'Every ability should use the wp-forge namespace.' );
	assert_true( false === strpos( $name, 'woocommerce-' ), 'WooCommerce-native tools should not be exposed.' );
}

$filtered = $abilities->list_abilities( array( 'name_prefix' => 'wp-forge-posts' ) );
assert_same( array( 'wp-forge-posts-search' ), array_column( $filtered, 'name' ), 'Prefix filtering should find post search only.' );

$schema = $abilities->get_schema( 'wp-forge-add-post' );
assert_same( 'wp-forge-add-post', $schema['name'], 'Schema lookup should accept MCP tool names.' );
assert_same( false, $schema['annotations']['readOnlyHint'], 'Add post should be marked writable.' );

$direct_tools = $abilities->list_tools();
$direct_tool_names = array_column( $direct_tools, 'name' );
assert_same( 69, count( $direct_tools ), 'Expected all abilities to be exposed as direct MCP tools.' );
assert_true( in_array( 'wp-forge-posts-search', $direct_tool_names, true ), 'Direct tool list should include posts search.' );
assert_true( in_array( 'wp-forge-get-active-theme', $direct_tool_names, true ), 'Direct tool list should include active theme.' );
assert_true( ! in_array( 'wp-forge-list-abilities', $direct_tool_names, true ), 'Gateway list tool should not be exposed.' );
assert_true( ! in_array( 'wp-forge-get-ability-schema', $direct_tool_names, true ), 'Gateway schema tool should not be exposed.' );
assert_true( ! in_array( 'wp-forge-call-ability', $direct_tool_names, true ), 'Gateway call tool should not be exposed.' );

$site_info_tool = array_values( array_filter( $direct_tools, static function ( $tool ) {
	return 'wp-forge-get-site-info' === $tool['name'];
} ) )[0];
assert_true( $site_info_tool['inputSchema']['properties'] instanceof stdClass, 'No-argument tool properties should serialize as a JSON object.' );
assert_same( true, $site_info_tool['annotations']['readOnlyHint'], 'Read-only tools should use the MCP readOnlyHint annotation.' );

$missing_plugin_runtime = $abilities->call( 'wp-forge-list-plugins', array() );
assert_same( 'error', $missing_plugin_runtime['status'], 'Plugin tools should report missing runtime in unit tests.' );
assert_same( 500, $missing_plugin_runtime['statusCode'], 'Missing WordPress plugin runtime should be a server-side ability error.' );

$missing_theme_runtime = $abilities->call( 'wp-forge-list-themes', array() );
assert_same( 'error', $missing_theme_runtime['status'], 'Theme tools should report missing runtime in unit tests.' );
assert_same( 500, $missing_theme_runtime['statusCode'], 'Missing WordPress runtime should be a server-side ability error.' );

$disabled_wp_cli = $abilities->call( 'wp-forge-run-wp-cli-command', array( 'args' => array( 'plugin', 'list' ) ) );
assert_same( 'error', $disabled_wp_cli['status'], 'WP-CLI tool should be disabled by default.' );
assert_same( 403, $disabled_wp_cli['statusCode'], 'Disabled WP-CLI tool should return a permission-style error.' );

$missing_runtime = $abilities->call( 'wp-forge-posts-search', array() );
assert_same( 'error', $missing_runtime['status'], 'WordPress-dependent ability should report missing runtime in unit tests.' );
assert_same( 500, $missing_runtime['statusCode'], 'Missing WordPress runtime should be a server-side ability error.' );

$wp_ability_names = $abilities->get_wordpress_ability_names();
assert_same( 69, count( $wp_ability_names ), 'Expected all abilities to be available for the MCP adapter.' );
assert_true( in_array( 'wp-forge/posts-search', $wp_ability_names, true ), 'Adapter ability list should use WordPress ability names.' );

$abilities->register_wordpress_abilities();
assert_same( 69, count( $registered_abilities ), 'Expected every ability to be registered with the WordPress Abilities API.' );
assert_true( isset( $registered_abilities['wp-forge/posts-search'] ), 'Posts search should be registered with the WordPress Abilities API.' );
assert_same( 'Search and filter WordPress posts with pagination', $registered_abilities['wp-forge/posts-search']['description'], 'Registered ability should preserve descriptions.' );
assert_same( true, $registered_abilities['wp-forge/posts-search']['meta']['show_in_rest'], 'Registered abilities should be exposed through the Abilities REST API.' );
assert_same( true, $registered_abilities['wp-forge/posts-search']['meta']['annotations']['readonly'], 'Read-only abilities should use core ability annotations.' );
assert_same( true, $registered_abilities['wp-forge/posts-search']['permission_callback'](), 'Permission callback should allow users with the ability capability.' );

$registered_result = $registered_abilities['wp-forge/posts-search']['execute_callback']( array() );
assert_same( 'error', $registered_result['status'], 'Registered ability callback should dispatch to the catalog.' );
assert_same( 500, $registered_result['statusCode'], 'Registered ability callback should return the ability response.' );

$plugin = Plugin::instance();
$plugin->init();
assert_true( isset( $added_actions['plugins_loaded'] ), 'Plugin should bootstrap the MCP adapter during plugins_loaded.' );
assert_true( isset( $added_actions['wp_abilities_api_init'] ), 'Plugin should register abilities during wp_abilities_api_init.' );
assert_true( isset( $added_actions['mcp_adapter_init'] ), 'Plugin should create the MCP server during mcp_adapter_init.' );
assert_true( ! isset( $added_actions['rest_api_init'] ), 'Plugin should not register its own MCP REST route.' );

$adapter = new class() {
	public $args;

	public function create_server() {
		$this->args = func_get_args();
	}
};

$plugin->create_mcp_server( $adapter );
assert_same( 'wp-forge', $adapter->args[0], 'Adapter server ID should be stable.' );
assert_same( 'mcp', $adapter->args[1], 'Adapter server should keep the existing REST namespace.' );
assert_same( 'wp-forge', $adapter->args[2], 'Adapter server should keep the existing REST route.' );
assert_same( 'WordPress MCP', $adapter->args[3], 'Adapter server should preserve the server name.' );
assert_same( 69, count( $adapter->args[9] ), 'Adapter server should expose every registered ability.' );
assert_true( in_array( 'wp-forge/posts-search', $adapter->args[9], true ), 'Adapter server should expose posts search.' );

echo 'Tests passed: ' . $tests_run . PHP_EOL;
