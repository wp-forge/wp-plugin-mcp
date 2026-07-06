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
$test_post_types      = array(
	'post'          => array(
		'name'                => 'post',
		'label'               => 'Posts',
		'description'         => 'Posts.',
		'public'              => true,
		'hierarchical'        => false,
		'show_in_rest'        => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'_builtin'            => true,
		'rest_base'           => 'posts',
		'supports'            => array( 'title' => true, 'editor' => true, 'author' => true, 'thumbnail' => true, 'excerpt' => true, 'comments' => true ),
		'taxonomies'          => array( 'category', 'post_tag' ),
	),
	'page'          => array(
		'name'                => 'page',
		'label'               => 'Pages',
		'description'         => 'Pages.',
		'public'              => true,
		'hierarchical'        => true,
		'show_in_rest'        => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'_builtin'            => true,
		'rest_base'           => 'pages',
		'supports'            => array( 'title' => true, 'editor' => true, 'author' => true, 'thumbnail' => true, 'page-attributes' => true ),
		'taxonomies'          => array(),
	),
	'nav_menu_item' => array(
		'name'                => 'nav_menu_item',
		'label'               => 'Navigation Menu Items',
		'description'         => 'Navigation menu items.',
		'public'              => false,
		'hierarchical'        => false,
		'show_in_rest'        => false,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'_builtin'            => true,
		'rest_base'           => '',
		'supports'            => array(),
		'taxonomies'          => array(),
	),
);

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

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( $args = array(), $output = 'names' ) {
		global $test_post_types;
		$matches = array();

		foreach ( $test_post_types as $slug => $post_type ) {
			foreach ( $args as $key => $value ) {
				if ( ! array_key_exists( $key, $post_type ) || $post_type[ $key ] !== $value ) {
					continue 2;
				}
			}

			$matches[ $slug ] = (object) $post_type;
		}

		return 'objects' === $output ? $matches : array_keys( $matches );
	}
}

if ( ! function_exists( 'get_all_post_type_supports' ) ) {
	function get_all_post_type_supports( $post_type ) {
		global $test_post_types;
		return isset( $test_post_types[ $post_type ] ) ? $test_post_types[ $post_type ]['supports'] : array();
	}
}

if ( ! function_exists( 'get_object_taxonomies' ) ) {
	function get_object_taxonomies( $post_type, $output = 'names' ) {
		global $test_post_types;
		return isset( $test_post_types[ $post_type ] ) ? $test_post_types[ $post_type ]['taxonomies'] : array();
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

assert_same( 58, count( $all ), 'Expected the WordPress ability catalog.' );
assert_true( in_array( 'wp-forge-search-content', $names, true ), 'Expected content search ability.' );
assert_true( in_array( 'wp-forge-get-site-info', $names, true ), 'Expected site info ability.' );
assert_true( in_array( 'wp-forge-run-api-function', $names, true ), 'Expected REST runner ability.' );
assert_true( ! in_array( 'wp-forge-wc-products-search', $names, true ), 'WooCommerce abilities should not be registered.' );

$expected_named_tools = array(
	'wp-forge-list-post-types',
	'wp-forge-search-content',
	'wp-forge-get-content',
	'wp-forge-save-content',
	'wp-forge-delete-content',
	'wp-forge-list-taxonomies',
	'wp-forge-list-taxonomy-terms',
	'wp-forge-get-taxonomy-term',
	'wp-forge-save-taxonomy-term',
	'wp-forge-delete-taxonomy-term',
	'wp-forge-list-media',
	'wp-forge-get-media',
	'wp-forge-get-media-file',
	'wp-forge-upload-media',
	'wp-forge-update-media',
	'wp-forge-delete-media',
	'wp-forge-search-media',
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

$filtered = $abilities->list_abilities( array( 'name_prefix' => 'wp-forge-search' ) );
assert_same( array( 'wp-forge-search-content', 'wp-forge-search-media' ), array_column( $filtered, 'name' ), 'Prefix filtering should find search tools.' );

$schema = $abilities->get_schema( 'wp-forge-save-content' );
assert_same( 'wp-forge-save-content', $schema['name'], 'Schema lookup should accept MCP tool names.' );
assert_same( false, $schema['annotations']['readOnlyHint'], 'Save content should be marked writable.' );

$post_types_schema = $abilities->get_schema( 'wp-forge-list-post-types' );
assert_same( 'boolean', $post_types_schema['input_schema']['properties']['public']['type'], 'Post type list should expose public filtering.' );

$direct_tools = $abilities->list_tools();
$direct_tool_names = array_column( $direct_tools, 'name' );
assert_same( 58, count( $direct_tools ), 'Expected all abilities to be exposed as direct MCP tools.' );
assert_true( in_array( 'wp-forge-search-content', $direct_tool_names, true ), 'Direct tool list should include content search.' );
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

$missing_runtime = $abilities->call( 'wp-forge-search-content', array( 'post_type' => 'post' ) );
assert_same( 'error', $missing_runtime['status'], 'WordPress-dependent ability should report missing runtime in unit tests.' );
assert_same( 500, $missing_runtime['statusCode'], 'Missing WordPress runtime should be a server-side ability error.' );

$post_types = $abilities->call( 'wp-forge-list-post-types', array() );
assert_same( 'success', $post_types['status'], 'Post type list should use the available WordPress runtime.' );
assert_same( array( 'post_types' ), array_keys( $post_types['message'] ), 'Post type list should return a structured envelope.' );
assert_same( 'post', $post_types['message']['post_types'][0]['slug'], 'Post type list should expose slugs.' );
assert_same( false, $post_types['message']['post_types'][0]['hierarchical'], 'Post type list should expose hierarchical status.' );
assert_same( array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ), $post_types['message']['post_types'][0]['supports'], 'Post type list should expose supported features.' );
assert_same( array( 'category', 'post_tag' ), $post_types['message']['post_types'][0]['taxonomies'], 'Post type list should expose supported taxonomies.' );
assert_same( 'posts', $post_types['message']['post_types'][0]['rest_base'], 'Post type list should expose REST base.' );

$public_hierarchical_post_types = $abilities->call( 'wp-forge-list-post-types', array( 'public' => true, 'hierarchical' => true ) );
assert_same( array( 'page' ), array_column( $public_hierarchical_post_types['message']['post_types'], 'slug' ), 'Post type list should pass filters through to get_post_types().' );

$wp_ability_names = $abilities->get_wordpress_ability_names();
assert_same( 58, count( $wp_ability_names ), 'Expected all abilities to be available for the MCP adapter.' );
assert_true( in_array( 'wp-forge/search-content', $wp_ability_names, true ), 'Adapter ability list should use WordPress ability names.' );

$abilities->register_wordpress_abilities();
assert_same( 58, count( $registered_abilities ), 'Expected every ability to be registered with the WordPress Abilities API.' );
assert_true( isset( $registered_abilities['wp-forge/search-content'] ), 'Content search should be registered with the WordPress Abilities API.' );
assert_same( 'Search and filter content for any registered post type', $registered_abilities['wp-forge/search-content']['description'], 'Registered ability should preserve descriptions.' );
assert_same( true, $registered_abilities['wp-forge/search-content']['meta']['show_in_rest'], 'Registered abilities should be exposed through the Abilities REST API.' );
assert_same( true, $registered_abilities['wp-forge/search-content']['meta']['annotations']['readonly'], 'Read-only abilities should use core ability annotations.' );
assert_same( true, $registered_abilities['wp-forge/search-content']['permission_callback'](), 'Permission callback should allow users with the ability capability.' );

$registered_result = $registered_abilities['wp-forge/search-content']['execute_callback']( array( 'post_type' => 'post' ) );
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
assert_same( 58, count( $adapter->args[9] ), 'Adapter server should expose every registered ability.' );
assert_true( in_array( 'wp-forge/search-content', $adapter->args[9], true ), 'Adapter server should expose content search.' );

echo 'Tests passed: ' . $tests_run . PHP_EOL;
