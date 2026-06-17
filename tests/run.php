<?php
/**
 * Lightweight unit tests for WordPress MCP.
 *
 * @package WordPressMCP
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WP_FORGE_MCP_VERSION', '0.1.0' );

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-wp-forge-mcp-response.php';
require_once dirname( __DIR__ ) . '/includes/class-wp-forge-mcp-abilities.php';
require_once dirname( __DIR__ ) . '/includes/class-wp-forge-mcp-server.php';

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

$abilities = new WP_Forge_MCP_Abilities();
$all       = $abilities->list_abilities();
$names     = array_column( $all, 'name' );

assert_same( 47, count( $all ), 'Expected the non-WooCommerce ability catalog.' );
assert_true( in_array( 'wp-forge-posts-search', $names, true ), 'Expected posts search ability.' );
assert_true( in_array( 'wp-forge-get-site-info', $names, true ), 'Expected site info ability.' );
assert_true( in_array( 'wp-forge-run-api-function', $names, true ), 'Expected REST runner ability.' );
assert_true( ! in_array( 'wp-forge-wc-products-search', $names, true ), 'WooCommerce abilities should not be registered.' );

foreach ( $names as $name ) {
	assert_true( 0 === strpos( $name, 'wp-forge-' ), 'Every ability should use the wp-forge namespace.' );
	assert_true( false === strpos( $name, 'woocommerce-' ), 'WooCommerce-native tools should not be exposed.' );
}

$filtered = $abilities->list_abilities( array( 'name_prefix' => 'wp-forge-posts' ) );
assert_same( array( 'wp-forge-posts-search' ), array_column( $filtered, 'name' ), 'Prefix filtering should find post search only.' );

$schema = $abilities->get_schema( 'wp-forge-add-post' );
assert_same( 'wp-forge-add-post', $schema['name'], 'Schema lookup should accept MCP tool names.' );
assert_same( false, $schema['annotations']['readonly'], 'Add post should be marked writable.' );

$missing_runtime = $abilities->call( 'wp-forge-posts-search', array() );
assert_same( 'error', $missing_runtime['status'], 'WordPress-dependent ability should report missing runtime in unit tests.' );
assert_same( 500, $missing_runtime['statusCode'], 'Missing WordPress runtime should be a server-side ability error.' );

$server = new WP_Forge_MCP_Server( $abilities );

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
assert_same(
	array( 'wp-forge-list-abilities', 'wp-forge-get-ability-schema', 'wp-forge-call-ability' ),
	$tool_names,
	'tools/list should expose the three gateway tools.'
);

$called = $server->handle(
	array(
		'jsonrpc' => '2.0',
		'id'      => 4,
		'method'  => 'tools/call',
		'params'  => array(
			'name'      => 'wp-forge-list-abilities',
			'arguments' => array( 'search' => 'global styles' ),
		),
	),
	$initialized['_session_id']
);
assert_same( 'success', $called['result']['structuredContent']['status'], 'Gateway list ability should return structured content.' );
assert_true( count( $called['result']['structuredContent']['message'] ) >= 3, 'Global styles search should find style abilities.' );

echo 'Tests passed: ' . $tests_run . PHP_EOL;
