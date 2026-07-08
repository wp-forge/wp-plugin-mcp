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
$test_post_statuses   = array(
	'publish'       => 'Published',
	'future'        => 'Scheduled',
	'draft'         => 'Draft',
	'pending'       => 'Pending',
	'private'       => 'Private',
	'trash'         => 'Trash',
	'inherit'       => 'Inherit',
	'mcp-reviewing' => 'MCP Reviewing',
);
$test_taxonomies      = array(
	'category'  => array(
		'name'        => 'category',
		'label'       => 'Categories',
		'description' => '',
		'public'      => true,
		'hierarchical' => true,
		'object_type' => array( 'post' ),
		'rest_base'   => 'categories',
	),
	'post_tag'  => array(
		'name'        => 'post_tag',
		'label'       => 'Tags',
		'description' => '',
		'public'      => true,
		'hierarchical' => false,
		'object_type' => array( 'post' ),
		'rest_base'   => 'tags',
	),
	'mcp_genre' => array(
		'name'        => 'mcp_genre',
		'label'       => 'Genres',
		'description' => 'Custom taxonomy registered by another plugin.',
		'public'      => true,
		'hierarchical' => false,
		'object_type' => array( 'mcp_book' ),
		'rest_base'   => 'mcp-genres',
	),
);
$test_rest_routes     = array(
	'/wp/v2/types'                => array(
		array(
			'methods' => array(
				'GET' => true,
			),
			'args'    => array(),
		),
	),
	'/wp/v2/posts/(?P<id>[\d]+)' => array(
		array(
			'methods' => array(
				'GET'    => true,
				'POST'   => true,
				'PUT'    => true,
				'PATCH'  => true,
				'DELETE' => true,
			),
			'args'    => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
	),
	'/mcp/wp-forge'               => array(
		array(
			'methods' => array(
				'POST' => true,
			),
			'args'    => array(),
		),
	),
);
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
	'mcp_book'      => array(
		'name'                => 'mcp_book',
		'label'               => 'Books',
		'description'         => 'Custom books.',
		'public'              => true,
		'hierarchical'        => false,
		'show_in_rest'        => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'_builtin'            => false,
		'rest_base'           => 'mcp-books',
		'supports'            => array( 'title' => true, 'editor' => true ),
		'taxonomies'          => array( 'mcp_genre' ),
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

if ( ! function_exists( 'get_post_stati' ) ) {
	function get_post_stati( $args = array(), $output = 'names' ) {
		global $test_post_statuses;
		unset( $args );

		if ( 'objects' === $output ) {
			return array_map(
				static function ( $label, $name ) {
					return (object) array(
						'name'  => $name,
						'label' => $label,
					);
				},
				$test_post_statuses,
				array_keys( $test_post_statuses )
			);
		}

		return array_keys( $test_post_statuses );
	}
}

if ( ! function_exists( 'get_taxonomies' ) ) {
	function get_taxonomies( $args = array(), $output = 'names' ) {
		global $test_taxonomies;
		unset( $args );

		$taxonomies = array();
		foreach ( $test_taxonomies as $slug => $taxonomy ) {
			$taxonomies[ $slug ] = (object) $taxonomy;
		}

		return 'objects' === $output ? $taxonomies : array_keys( $taxonomies );
	}
}

if ( ! function_exists( 'get_editable_roles' ) ) {
	function get_editable_roles() {
		return array(
			'administrator' => array( 'name' => 'Administrator' ),
			'editor'        => array( 'name' => 'Editor' ),
			'mcp_manager'   => array( 'name' => 'MCP Manager' ),
		);
	}
}

if ( ! function_exists( 'get_allowed_mime_types' ) ) {
	function get_allowed_mime_types() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'mcp'          => 'application/x-mcp-test',
		);
	}
}

if ( ! function_exists( 'rest_get_server' ) ) {
	function rest_get_server() {
		global $test_rest_routes;

		return new class( $test_rest_routes ) {
			private $routes;

			public function __construct( $routes ) {
				$this->routes = $routes;
			}

			public function get_routes() {
				return $this->routes;
			}
		};
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
$test_post_statuses['mcp-late-review'] = 'MCP Late Review';
$test_post_types['mcp_movie']          = array(
	'name'                => 'mcp_movie',
	'label'               => 'Movies',
	'description'         => 'Custom movies registered after ability construction.',
	'public'              => true,
	'hierarchical'        => false,
	'show_in_rest'        => true,
	'show_ui'             => true,
	'show_in_menu'        => true,
	'show_in_nav_menus'   => true,
	'exclude_from_search' => false,
	'publicly_queryable'  => true,
	'_builtin'            => false,
	'rest_base'           => 'mcp-movies',
	'supports'            => array( 'title' => true ),
	'taxonomies'          => array( 'mcp_genre' ),
);
$all       = $abilities->list_abilities();
$names     = array_column( $all, 'name' );

assert_same( 53, count( $all ), 'Expected the WordPress ability catalog.' );
assert_true( in_array( 'wp-forge-content-search', $names, true ), 'Expected content search ability.' );
assert_true( in_array( 'wp-forge-site-info-get', $names, true ), 'Expected site info ability.' );
assert_true( in_array( 'wp-forge-api-function-run', $names, true ), 'Expected REST runner ability.' );
assert_true( ! in_array( 'wp-forge-wc-products-search', $names, true ), 'WooCommerce abilities should not be registered.' );

$expected_named_tools = array(
	'wp-forge-post-type-list',
	'wp-forge-content-search',
	'wp-forge-content-get',
	'wp-forge-content-save',
	'wp-forge-content-delete',
	'wp-forge-taxonomy-list',
	'wp-forge-taxonomy-term-list',
	'wp-forge-taxonomy-term-get',
	'wp-forge-taxonomy-term-save',
	'wp-forge-taxonomy-term-delete',
	'wp-forge-media-list',
	'wp-forge-media-get',
	'wp-forge-media-file-get',
	'wp-forge-media-upload',
	'wp-forge-media-update',
	'wp-forge-media-delete',
	'wp-forge-media-search',
	'wp-forge-user-search',
	'wp-forge-user-get',
	'wp-forge-user-save',
	'wp-forge-user-delete',
	'wp-forge-general-settings-get',
	'wp-forge-general-settings-save',
	'wp-forge-site-info-get',
	'wp-forge-plugin-list',
	'wp-forge-plugin-install',
	'wp-forge-plugin-activate',
	'wp-forge-plugin-deactivate',
	'wp-forge-plugin-uninstall',
	'wp-forge-theme-list',
	'wp-forge-theme-install',
	'wp-forge-theme-activate',
	'wp-forge-theme-delete',
	'wp-forge-option-list',
	'wp-forge-option-get',
	'wp-forge-option-save',
	'wp-forge-option-delete',
	'wp-forge-comment-list',
	'wp-forge-comment-get',
	'wp-forge-comment-save',
	'wp-forge-comment-delete',
	'wp-forge-site-health-info-get',
	'wp-forge-site-health-test-list',
	'wp-forge-error-log-read',
	'wp-forge-wp-cli-command-run',
	'wp-forge-global-styles-get',
	'wp-forge-global-styles-update',
	'wp-forge-global-styles-active-get',
	'wp-forge-global-styles-active-id-get',
	'wp-forge-theme-active-get',
);

foreach ( $expected_named_tools as $expected_name ) {
	assert_true( in_array( $expected_name, $names, true ), 'Expected WordPress tool: ' . $expected_name );
}

foreach ( $names as $name ) {
	assert_true( 0 === strpos( $name, 'wp-forge-' ), 'Every ability should use the wp-forge namespace.' );
	assert_true( false === strpos( $name, 'woocommerce-' ), 'WooCommerce-native tools should not be exposed.' );
}

$filtered = $abilities->list_abilities( array( 'name_prefix' => 'wp-forge-content-' ) );
assert_same( array( 'wp-forge-content-search', 'wp-forge-content-get', 'wp-forge-content-save', 'wp-forge-content-delete' ), array_column( $filtered, 'name' ), 'Prefix filtering should find content tools.' );

$schema = $abilities->get_schema( 'wp-forge-content-save' );
assert_same( 'wp-forge-content-save', $schema['name'], 'Schema lookup should accept MCP tool names.' );
assert_same( false, $schema['annotations']['readOnlyHint'], 'Save content should be marked writable.' );
assert_true( in_array( 'mcp_movie', $schema['input_schema']['properties']['post_type']['enum'], true ), 'Save content schema should include post types registered after ability construction.' );
assert_true( in_array( 'mcp-reviewing', $schema['input_schema']['properties']['status']['enum'], true ), 'Save content schema should include custom post statuses.' );
assert_true( in_array( 'mcp-late-review', $schema['input_schema']['properties']['status']['enum'], true ), 'Save content schema should resolve post statuses lazily.' );

$search_schema = $abilities->get_schema( 'wp-forge-content-search' );
assert_true( in_array( 'any', $search_schema['input_schema']['properties']['status']['enum'], true ), 'Search content schema should expose the WordPress any pseudo-status.' );
assert_true( in_array( 'mcp_genre', $search_schema['input_schema']['properties']['taxonomy_query']['properties']['taxonomy']['enum'], true ), 'Search content schema should include custom taxonomies.' );

$taxonomy_schema = $abilities->get_schema( 'wp-forge-taxonomy-term-save' );
assert_true( in_array( 'mcp_genre', $taxonomy_schema['input_schema']['properties']['taxonomy']['enum'], true ), 'Taxonomy term schema should include plugin-registered taxonomies.' );

$user_schema = $abilities->get_schema( 'wp-forge-user-save' );
assert_true( in_array( 'mcp_manager', $user_schema['input_schema']['properties']['role']['enum'], true ), 'User schema should include custom editable roles.' );

$media_schema = $abilities->get_schema( 'wp-forge-media-upload' );
assert_true( in_array( 'application/x-mcp-test', $media_schema['input_schema']['properties']['mime_type']['enum'], true ), 'Media schema should include custom allowed MIME types.' );

$comment_schema = $abilities->get_schema( 'wp-forge-comment-save' );
assert_true( ! isset( $comment_schema['input_schema']['properties']['status']['enum'] ), 'Comment status schema should stay flexible when custom statuses cannot be reliably discovered.' );

$api_list_schema = $abilities->get_schema( 'wp-forge-api-function-list' );
$api_methods     = $api_list_schema['input_schema']['properties']['methods']['items']['enum'];
assert_true( in_array( 'PUT', $api_methods, true ), 'REST list schema should include PUT when a REST route supports it.' );
assert_true( ! in_array( 'OPTIONS', $api_methods, true ), 'REST list schema should be derived from registered route methods.' );

$api_details_schema = $abilities->get_schema( 'wp-forge-api-function-details-get' );
assert_same(
	$api_methods,
	$api_details_schema['input_schema']['properties']['method']['enum'],
	'REST details schema should use the same route-derived methods as list.'
);

$api_run_schema = $abilities->get_schema( 'wp-forge-api-function-run' );
assert_same(
	$api_methods,
	$api_run_schema['input_schema']['properties']['method']['enum'],
	'REST runner schema should use the same route-derived methods as list.'
);

$invalid_search_status = $abilities->call( 'wp-forge-content-search', array( 'post_type' => 'post', 'status' => 'mcp-missing-status' ) );
assert_same( 'error', $invalid_search_status['status'], 'Search content should reject unknown post statuses before querying.' );
assert_same( 400, $invalid_search_status['statusCode'], 'Unknown query post statuses should be a client error.' );

$invalid_save_status = $abilities->call( 'wp-forge-content-save', array( 'post_type' => 'post', 'title' => 'Invalid status', 'status' => 'mcp-missing-status' ) );
assert_same( 'error', $invalid_save_status['status'], 'Save content should reject unknown post statuses before writing.' );
assert_same( 400, $invalid_save_status['statusCode'], 'Unknown write post statuses should be a client error.' );

$invalid_any_save_status = $abilities->call( 'wp-forge-content-save', array( 'post_type' => 'post', 'title' => 'Invalid status', 'status' => 'any' ) );
assert_same( 'error', $invalid_any_save_status['status'], 'Save content should reject the any pseudo-status.' );
assert_same( 400, $invalid_any_save_status['statusCode'], 'The any pseudo-status should be rejected for writes.' );

$invalid_role = $abilities->call( 'wp-forge-user-save', array( 'username' => 'bad_role', 'email' => 'bad-role@example.com', 'password' => 'password', 'role' => 'mcp_missing_role' ) );
assert_same( 'error', $invalid_role['status'], 'Save user should reject unknown roles before writing.' );
assert_same( 400, $invalid_role['statusCode'], 'Unknown roles should be a client error.' );

$invalid_mime = $abilities->call( 'wp-forge-media-upload', array( 'filename' => 'bad.bin', 'base64' => base64_encode( 'bad' ), 'mime_type' => 'application/x-missing-mcp' ) );
assert_same( 'error', $invalid_mime['status'], 'Upload media should reject unsupported MIME types before writing.' );
assert_same( 400, $invalid_mime['statusCode'], 'Unsupported MIME types should be a client error.' );

$post_types_schema = $abilities->get_schema( 'wp-forge-post-type-list' );
assert_same( 'boolean', $post_types_schema['input_schema']['properties']['public']['type'], 'Post type list should expose public filtering.' );

$direct_tools = $abilities->list_tools();
$direct_tool_names = array_column( $direct_tools, 'name' );
assert_same( 53, count( $direct_tools ), 'Expected all abilities to be exposed as direct MCP tools.' );
assert_true( in_array( 'wp-forge-content-search', $direct_tool_names, true ), 'Direct tool list should include content search.' );
assert_true( in_array( 'wp-forge-theme-active-get', $direct_tool_names, true ), 'Direct tool list should include active theme.' );
assert_true( ! in_array( 'wp-forge-list-abilities', $direct_tool_names, true ), 'Gateway list tool should not be exposed.' );
assert_true( ! in_array( 'wp-forge-get-ability-schema', $direct_tool_names, true ), 'Gateway schema tool should not be exposed.' );
assert_true( ! in_array( 'wp-forge-call-ability', $direct_tool_names, true ), 'Gateway call tool should not be exposed.' );

$site_info_tool = array_values( array_filter( $direct_tools, static function ( $tool ) {
	return 'wp-forge-site-info-get' === $tool['name'];
} ) )[0];
assert_true( $site_info_tool['inputSchema']['properties'] instanceof stdClass, 'No-argument tool properties should serialize as a JSON object.' );
assert_same( true, $site_info_tool['annotations']['readOnlyHint'], 'Read-only tools should use the MCP readOnlyHint annotation.' );

$content_delete_tool = array_values( array_filter( $direct_tools, static function ( $tool ) {
	return 'wp-forge-content-delete' === $tool['name'];
} ) )[0];
assert_same( false, $content_delete_tool['annotations']['readOnlyHint'], 'Delete tools should not use the MCP readOnlyHint annotation.' );
assert_same( true, $content_delete_tool['annotations']['destructiveHint'], 'Delete tools should use the MCP destructiveHint annotation.' );
assert_same( true, $content_delete_tool['annotations']['idempotentHint'], 'Delete tools should use the MCP idempotentHint annotation.' );

$missing_plugin_runtime = $abilities->call( 'wp-forge-plugin-list', array() );
assert_same( 'error', $missing_plugin_runtime['status'], 'Plugin tools should report missing runtime in unit tests.' );
assert_same( 500, $missing_plugin_runtime['statusCode'], 'Missing WordPress plugin runtime should be a server-side ability error.' );

$missing_theme_runtime = $abilities->call( 'wp-forge-theme-list', array() );
assert_same( 'error', $missing_theme_runtime['status'], 'Theme tools should report missing runtime in unit tests.' );
assert_same( 500, $missing_theme_runtime['statusCode'], 'Missing WordPress runtime should be a server-side ability error.' );

$disabled_wp_cli = $abilities->call( 'wp-forge-wp-cli-command-run', array( 'args' => array( 'plugin', 'list' ) ) );
assert_same( 'error', $disabled_wp_cli['status'], 'WP-CLI tool should be disabled by default.' );
assert_same( 403, $disabled_wp_cli['statusCode'], 'Disabled WP-CLI tool should return a permission-style error.' );

$missing_runtime = $abilities->call( 'wp-forge-content-search', array( 'post_type' => 'post' ) );
assert_same( 'error', $missing_runtime['status'], 'WordPress-dependent ability should report missing runtime in unit tests.' );
assert_same( 500, $missing_runtime['statusCode'], 'Missing WordPress runtime should be a server-side ability error.' );

$post_types = $abilities->call( 'wp-forge-post-type-list', array() );
assert_same( 'success', $post_types['status'], 'Post type list should use the available WordPress runtime.' );
assert_same( array( 'post_types' ), array_keys( $post_types['message'] ), 'Post type list should return a structured envelope.' );
assert_same( 'post', $post_types['message']['post_types'][0]['slug'], 'Post type list should expose slugs.' );
assert_same( false, $post_types['message']['post_types'][0]['hierarchical'], 'Post type list should expose hierarchical status.' );
assert_same( array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ), $post_types['message']['post_types'][0]['supports'], 'Post type list should expose supported features.' );
assert_same( array( 'category', 'post_tag' ), $post_types['message']['post_types'][0]['taxonomies'], 'Post type list should expose supported taxonomies.' );
assert_same( 'posts', $post_types['message']['post_types'][0]['rest_base'], 'Post type list should expose REST base.' );

$public_hierarchical_post_types = $abilities->call( 'wp-forge-post-type-list', array( 'public' => true, 'hierarchical' => true ) );
assert_same( array( 'page' ), array_column( $public_hierarchical_post_types['message']['post_types'], 'slug' ), 'Post type list should pass filters through to get_post_types().' );

$put_api_functions = $abilities->call(
	'wp-forge-api-function-list',
	array(
		'namespace' => 'wp/v2',
		'methods'   => array( 'PUT' ),
	)
);
assert_same( 'success', $put_api_functions['status'], 'REST function list should accept route-derived PUT filters.' );
assert_same(
	array( 'PUT' ),
	array_values( array_unique( array_column( $put_api_functions['message'], 'method' ) ) ),
	'REST function list should return PUT routes when supported.'
);

$put_api_details = $abilities->call(
	'wp-forge-api-function-details-get',
	array(
		'route'  => '/wp/v2/posts/(?P<id>[\d]+)',
		'method' => 'PUT',
	)
);
assert_same( 'success', $put_api_details['status'], 'REST function details should accept PUT when the route supports it.' );
assert_same( 'PUT', $put_api_details['message']['method'], 'REST function details should preserve the supported PUT method.' );

$mcp_api_functions = $abilities->call(
	'wp-forge-api-function-list',
	array(
		'methods' => array( 'POST' ),
		'search'  => '/mcp/wp-forge',
	)
);
assert_same( array(), $mcp_api_functions['message'], 'REST function list should not expose the MCP transport route.' );

$wp_ability_names = $abilities->get_wordpress_ability_names();
assert_same( 53, count( $wp_ability_names ), 'Expected all abilities to be available for the MCP adapter.' );
assert_true( in_array( 'wp-forge/content-search', $wp_ability_names, true ), 'Adapter ability list should use WordPress ability names.' );

$abilities->register_wordpress_abilities();
assert_same( 53, count( $registered_abilities ), 'Expected every ability to be registered with the WordPress Abilities API.' );
assert_true( isset( $registered_abilities['wp-forge/content-search'] ), 'Content search should be registered with the WordPress Abilities API.' );
assert_same( 'Search and filter content for any registered post type', $registered_abilities['wp-forge/content-search']['description'], 'Registered ability should preserve descriptions.' );
assert_same( true, $registered_abilities['wp-forge/content-search']['meta']['show_in_rest'], 'Registered abilities should be exposed through the Abilities REST API.' );
assert_same( true, $registered_abilities['wp-forge/content-search']['meta']['annotations']['readonly'], 'Read-only abilities should use core ability annotations.' );
assert_same( false, $registered_abilities['wp-forge/content-search']['meta']['annotations']['destructive'], 'Read-only abilities should not be marked destructive.' );
assert_same( true, $registered_abilities['wp-forge/content-search']['meta']['annotations']['idempotent'], 'Read-only abilities should be marked idempotent.' );
assert_same( false, $registered_abilities['wp-forge/content-save']['meta']['annotations']['readonly'], 'Save abilities should not be marked read-only.' );
assert_same( false, $registered_abilities['wp-forge/content-save']['meta']['annotations']['destructive'], 'Create-or-update abilities should not be marked destructive by default.' );
assert_same( false, $registered_abilities['wp-forge/content-save']['meta']['annotations']['idempotent'], 'Create-or-update abilities should not be marked idempotent by default.' );
assert_same( true, $registered_abilities['wp-forge/content-delete']['meta']['annotations']['destructive'], 'Delete abilities should be marked destructive.' );
assert_same( true, $registered_abilities['wp-forge/content-delete']['meta']['annotations']['idempotent'], 'Delete abilities should be marked effect-idempotent.' );
assert_same( true, $registered_abilities['wp-forge/general-settings-save']['meta']['annotations']['idempotent'], 'State-setting save abilities should be marked idempotent.' );
assert_same( false, $registered_abilities['wp-forge/plugin-activate']['meta']['annotations']['destructive'], 'Activation should not be marked destructive.' );
assert_same( true, $registered_abilities['wp-forge/plugin-activate']['meta']['annotations']['idempotent'], 'Activation should be marked idempotent.' );
assert_same( true, $registered_abilities['wp-forge/plugin-uninstall']['meta']['annotations']['destructive'], 'Uninstall should be marked destructive.' );
assert_same(
	$api_methods,
	$registered_abilities['wp-forge/api-function-run']['input_schema']['properties']['method']['enum'],
	'Registered WordPress REST runner ability should use route-derived methods.'
);
assert_same( true, $registered_abilities['wp-forge/content-search']['permission_callback'](), 'Permission callback should allow users with the ability capability.' );

$registered_result = $registered_abilities['wp-forge/content-search']['execute_callback']( array( 'post_type' => 'post' ) );
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
assert_same( 53, count( $adapter->args[9] ), 'Adapter server should expose every registered ability.' );
assert_true( in_array( 'wp-forge/content-search', $adapter->args[9], true ), 'Adapter server should expose content search.' );

echo 'Tests passed: ' . $tests_run . PHP_EOL;
