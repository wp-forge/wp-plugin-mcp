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
use WP_Forge\Server;

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

$server = new Server( $abilities );

$needs_session = $server->handle(
	array(
		'jsonrpc' => '2.0',
		'id'      => 1,
		'method'  => 'tools/list',
	)
);
assert_same( -32000, $needs_session['error']['code'], 'tools/list should require initialization.' );

$initialized = $server->handle(
	array(
		'jsonrpc' => '2.0',
		'id'      => 2,
		'method'  => 'initialize',
		'params'  => array(
			'protocolVersion' => '2025-06-18',
			'capabilities'    => array(),
			'clientInfo'      => array( 'name' => 'tests', 'version' => '1.0' ),
		),
	)
);
assert_same( 'WordPress MCP', $initialized['result']['serverInfo']['name'], 'Initialize should identify the server.' );
assert_true( ! empty( $initialized['_session_id'] ), 'Initialize should return a session ID.' );

$tools = $server->handle(
	array(
		'jsonrpc' => '2.0',
		'id'      => 3,
		'method'  => 'tools/list',
	),
	$initialized['_session_id']
);
$tool_names = array_column( $tools['result']['tools'], 'name' );
assert_same( 69, count( $tool_names ), 'tools/list should expose every WordPress tool directly.' );
assert_true( in_array( 'wp-forge-posts-search', $tool_names, true ), 'tools/list should expose posts search directly.' );
assert_true( in_array( 'wp-forge-get-site-info', $tool_names, true ), 'tools/list should expose site info directly.' );
assert_true( ! in_array( 'wp-forge-call-ability', $tool_names, true ), 'tools/list should not expose a gateway call tool.' );

$called = $server->handle(
	array(
		'jsonrpc' => '2.0',
		'id'      => 4,
		'method'  => 'tools/call',
		'params'  => array(
			'name'      => 'wp-forge-posts-search',
			'arguments' => array(),
		),
	),
	$initialized['_session_id']
);
assert_same( 'error', $called['result']['structuredContent']['status'], 'Direct tool calls should dispatch to the named WordPress tool.' );
assert_same( 500, $called['result']['structuredContent']['statusCode'], 'Direct tool call should return the ability response.' );

echo 'Tests passed: ' . $tests_run . PHP_EOL;
