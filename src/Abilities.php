<?php
/**
 * Ability catalog for WordPress MCP.
 *
 * @package WP_Forge
 */

namespace WP_Forge;

use WP_Forge\Tools\ContentManagementTools;
use WP_Forge\Tools\CommentManagementTools;
use WP_Forge\Tools\ErrorLogTools;
use WP_Forge\Tools\GlobalStylesTools;
use WP_Forge\Tools\MediaTools;
use WP_Forge\Tools\OptionManagementTools;
use WP_Forge\Tools\PluginManagementTools;
use WP_Forge\Tools\RestCatalogTools;
use WP_Forge\Tools\SiteManagementTools;
use WP_Forge\Tools\SiteHealthTools;
use WP_Forge\Tools\TaxonomyTools;
use WP_Forge\Tools\ThemeManagementTools;
use WP_Forge\Tools\WPCLITools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers wp-forge abilities and dispatches calls.
 */
class Abilities {
	use ContentManagementTools;
	use CommentManagementTools;
	use ErrorLogTools;
	use GlobalStylesTools;
	use MediaTools;
	use OptionManagementTools;
	use PluginManagementTools;
	use RestCatalogTools;
	use SiteManagementTools;
	use SiteHealthTools;
	use TaxonomyTools;
	use ThemeManagementTools;
	use WPCLITools;

	const INTERNAL_PREFIX = 'wp-forge/';
	const TOOL_PREFIX     = 'wp-forge-';

	/**
	 * Ability definitions.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $abilities = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_default_abilities();
	}

	/**
	 * List abilities with optional filtering.
	 *
	 * @param array<string,mixed> $filters Filter arguments.
	 * @return array<int,array<string,mixed>>
	 */
	public function list_abilities( $filters = array() ) {
		$search      = isset( $filters['search'] ) ? strtolower( (string) $filters['search'] ) : '';
		$name_prefix = isset( $filters['name_prefix'] ) ? $this->normalize_tool_name( (string) $filters['name_prefix'] ) : '';
		$items       = array();

		foreach ( $this->abilities as $name => $ability ) {
			$tool_name = $this->ability_to_tool_name( $name );

			if ( $name_prefix && 0 !== strpos( $tool_name, $name_prefix ) ) {
				continue;
			}

			if ( $search ) {
				$haystack = strtolower( $tool_name . ' ' . $ability['label'] . ' ' . $ability['description'] );
				if ( false === strpos( $haystack, $search ) ) {
					continue;
				}
			}

			$items[] = array(
				'name'        => $tool_name,
				'label'       => $ability['label'],
				'description' => $ability['description'],
				'annotations' => $ability['annotations'],
			);
		}

		return $items;
	}

	/**
	 * List all registered abilities as top-level MCP tools.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_tools() {
		$tools = array();

		foreach ( $this->abilities as $name => $ability ) {
			$tools[] = array(
				'name'        => $this->ability_to_tool_name( $name ),
				'description' => $ability['description'],
				'inputSchema' => $this->resolve_input_schema( $ability['input_schema'] ),
				'annotations' => $ability['annotations'],
			);
		}

		return $tools;
	}

	/**
	 * Get an ability schema by MCP tool name or internal ability name.
	 *
	 * @param string $name Ability name.
	 * @return array<string,mixed>|null
	 */
	public function get_schema( $name ) {
		$internal = $this->tool_to_ability_name( $name );

		if ( ! isset( $this->abilities[ $internal ] ) ) {
			return null;
		}

		$ability = $this->abilities[ $internal ];

		return array(
			'name'         => $this->ability_to_tool_name( $internal ),
			'label'        => $ability['label'],
			'description'  => $ability['description'],
			'input_schema' => $this->resolve_input_schema( $ability['input_schema'] ),
			'annotations'  => $ability['annotations'],
		);
	}

	/**
	 * Get WordPress ability names for the MCP adapter server.
	 *
	 * @return array<int,string>
	 */
	public function get_wordpress_ability_names() {
		return array_keys( $this->abilities );
	}

	/**
	 * Register every wp-forge ability with the WordPress Abilities API.
	 *
	 * @return void
	 */
	public function register_wordpress_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( $this->abilities as $name => $ability ) {
			$capability = isset( $ability['capability'] ) ? $ability['capability'] : 'edit_posts';

			wp_register_ability(
				$name,
				array(
					'label'               => $ability['label'],
					'description'         => $ability['description'],
					'category'            => 'site',
					'input_schema'        => $this->resolve_input_schema( $ability['input_schema'] ),
					'output_schema'       => $this->response_schema(),
					'execute_callback'    => function ( $input ) use ( $name ) {
						return $this->call( $name, is_array( $input ) ? $input : array() );
					},
					'permission_callback' => function () use ( $capability ) {
						return function_exists( 'current_user_can' ) ? current_user_can( $capability ) : false;
					},
					'meta'                => array(
						'annotations'  => $ability['meta_annotations'],
						'show_in_rest' => true,
					),
				)
			);
		}
	}

	/**
	 * Call an ability.
	 *
	 * @param string              $name Ability name.
	 * @param array<string,mixed> $parameters Ability parameters.
	 * @return array<string,mixed>
	 */
	public function call( $name, $parameters = array() ) {
		$internal = $this->tool_to_ability_name( $name );

		if ( ! isset( $this->abilities[ $internal ] ) ) {
			return Response::error( 'Unknown ability: ' . $name, 404 );
		}

		$permission = $this->check_ability_permission( $this->abilities[ $internal ], is_array( $parameters ) ? $parameters : array() );
		if ( true !== $permission ) {
			return $permission;
		}

		$callback = $this->abilities[ $internal ]['callback'];
		$result   = call_user_func( $callback, is_array( $parameters ) ? $parameters : array() );
		$result   = Response::unwrap_wp_error( $result );

		if ( is_array( $result ) && isset( $result['statusCode'], $result['status'], $result['message'] ) ) {
			return $result;
		}

		return Response::success( $result );
	}

	/**
	 * Convert internal ability name to MCP tool name.
	 *
	 * @param string $name Internal ability name.
	 * @return string
	 */
	public function ability_to_tool_name( $name ) {
		return str_replace( '/', '-', $name );
	}

	/**
	 * Convert MCP tool name to internal ability name.
	 *
	 * @param string $name MCP tool name or internal name.
	 * @return string
	 */
	public function tool_to_ability_name( $name ) {
		$name = (string) $name;

		if ( 0 === strpos( $name, self::INTERNAL_PREFIX ) ) {
			return $name;
		}

		if ( 0 === strpos( $name, self::TOOL_PREFIX ) ) {
			return self::INTERNAL_PREFIX . substr( $name, strlen( self::TOOL_PREFIX ) );
		}

		return $name;
	}

	/**
	 * Register an ability.
	 *
	 * @param string              $name Internal ability name.
	 * @param string              $label Human label.
	 * @param string              $description Description.
	 * @param array<string,mixed>|callable $input_schema JSON schema or schema factory.
	 * @param callable            $callback Callback.
	 * @param bool                $read_only Whether ability is read-only.
	 * @param string              $capability Required WordPress capability.
	 * @param array<string,bool>  $annotations Core ability annotation overrides.
	 * @param callable|null       $permission_callback Optional dynamic permission callback.
	 * @return void
	 */
	private function add_ability( $name, $label, $description, $input_schema, $callback, $read_only = true, $capability = 'edit_posts', $annotations = array(), $permission_callback = null ) {
		$core_annotations = $this->core_ability_annotations( $read_only, $annotations );

		$this->abilities[ $name ] = array(
			'label'               => $label,
			'description'         => $description,
			'input_schema'        => $input_schema,
			'callback'            => $callback,
			'annotations'         => $this->mcp_tool_annotations( $core_annotations ),
			'meta_annotations'    => $core_annotations,
			'capability'          => $capability,
			'permission_callback' => $permission_callback,
		);
	}

	/**
	 * Check static and dynamic permissions for an ability call.
	 *
	 * @param array<string,mixed> $ability Ability definition.
	 * @param array<string,mixed> $parameters Ability parameters.
	 * @return true|array<string,mixed>
	 */
	private function check_ability_permission( $ability, $parameters ) {
		$capability = isset( $ability['capability'] ) ? $ability['capability'] : 'edit_posts';
		$allowed    = $this->current_user_can_cap( $capability );

		if ( ! $allowed ) {
			return Response::error( 'Access denied for ability: missing capability ' . $capability, 403 );
		}

		if ( ! empty( $ability['permission_callback'] ) && is_callable( $ability['permission_callback'] ) ) {
			$result = call_user_func( $ability['permission_callback'], $parameters );
			if ( true !== $result ) {
				return is_array( $result ) ? $result : Response::error( 'Access denied for ability.', 403 );
			}
		}

		return true;
	}

	/**
	 * Check a WordPress capability with optional object arguments.
	 *
	 * @param string $capability Capability name.
	 * @param mixed  ...$args Optional map-meta-cap arguments.
	 * @return bool
	 */
	private function current_user_can_cap( $capability ) {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		$args = func_get_args();
		return (bool) call_user_func_array( 'current_user_can', $args );
	}

	/**
	 * Build a consistent permission error response.
	 *
	 * @param string $message Error message.
	 * @return array<string,mixed>
	 */
	private function permission_error( $message ) {
		return Response::error( $message, 403 );
	}

	/**
	 * Get a post type capability name.
	 *
	 * @param mixed  $type Post type object.
	 * @param string $cap Capability property.
	 * @param string $fallback Fallback capability.
	 * @return string
	 */
	private function post_type_cap( $type, $cap, $fallback = 'edit_posts' ) {
		return isset( $type->cap, $type->cap->$cap ) ? (string) $type->cap->$cap : $fallback;
	}

	/**
	 * Check whether the current user can query requested content.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_search_content_request( $params ) {
		if ( empty( $params['post_type'] ) ) {
			return true;
		}

		$post_type_check = $this->validate_post_type( $params['post_type'] );
		if ( isset( $post_type_check['status'] ) && 'error' === $post_type_check['status'] ) {
			return true;
		}

		$type   = $post_type_check['type'];
		$status = isset( $params['status'] ) ? $this->sanitize_key_value( $params['status'] ) : 'publish';
		if ( in_array( $status, array( 'publish', 'inherit' ), true ) ) {
			return true;
		}

		if ( 'private' === $status && $this->current_user_can_cap( $this->post_type_cap( $type, 'read_private_posts' ) ) ) {
			return true;
		}

		if ( $this->current_user_can_cap( $this->post_type_cap( $type, 'edit_posts' ) ) ) {
			return true;
		}

		return $this->permission_error( 'Access denied for the requested content status.' );
	}

	/**
	 * Check whether the current user can read a content request.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_read_content_request( $params ) {
		$post = $this->get_post_for_content_request( $params );
		if ( ! $post ) {
			return true;
		}

		return $this->current_user_can_cap( 'read_post', (int) $post->ID ) ? true : $this->permission_error( 'Access denied for this content item.' );
	}

	/**
	 * Check whether the current user can create or update content.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_save_content_request( $params ) {
		if ( empty( $params['post_type'] ) ) {
			return true;
		}

		$post_type_check = $this->validate_post_type( $params['post_type'] );
		if ( isset( $post_type_check['status'] ) && 'error' === $post_type_check['status'] ) {
			return true;
		}

		$type = $post_type_check['type'];
		if ( ! empty( $params['id'] ) ) {
			$post = function_exists( 'get_post' ) ? get_post( (int) $params['id'] ) : null;
			if ( $post && ! $this->current_user_can_cap( 'edit_post', (int) $post->ID ) ) {
				return $this->permission_error( 'Access denied for editing this content item.' );
			}
		} elseif ( ! $this->current_user_can_cap( $this->post_type_cap( $type, 'create_posts', $this->post_type_cap( $type, 'edit_posts' ) ) ) ) {
			return $this->permission_error( 'Access denied for creating this content type.' );
		}

		if ( ! empty( $params['status'] ) && in_array( $this->sanitize_key_value( $params['status'] ), array( 'publish', 'future', 'private' ), true ) && ! $this->current_user_can_cap( $this->post_type_cap( $type, 'publish_posts' ) ) ) {
			return $this->permission_error( 'Access denied for publishing this content type.' );
		}

		if ( isset( $params['author'] ) && function_exists( 'get_current_user_id' ) && (int) $params['author'] !== (int) get_current_user_id() && ! $this->current_user_can_cap( $this->post_type_cap( $type, 'edit_others_posts' ) ) ) {
			return $this->permission_error( 'Access denied for assigning content to another author.' );
		}

		if ( ! empty( $params['taxonomies'] ) && is_array( $params['taxonomies'] ) ) {
			foreach ( array_keys( $params['taxonomies'] ) as $taxonomy ) {
				$taxonomy_obj = $this->get_taxonomy_object( (string) $taxonomy );
				if ( $taxonomy_obj && ! $this->current_user_can_cap( $this->taxonomy_cap( $taxonomy_obj, 'assign_terms' ) ) ) {
					return $this->permission_error( 'Access denied for assigning terms in taxonomy: ' . (string) $taxonomy );
				}
			}
		}

		if ( ! empty( $params['meta'] ) && is_array( $params['meta'] ) && ! empty( $params['id'] ) && function_exists( 'current_user_can' ) ) {
			foreach ( array_keys( $params['meta'] ) as $meta_key ) {
				if ( ! $this->current_user_can_cap( 'edit_post_meta', (int) $params['id'], (string) $meta_key ) ) {
					return $this->permission_error( 'Access denied for editing post meta: ' . (string) $meta_key );
				}
			}
		}

		return true;
	}

	/**
	 * Check whether the current user can delete content.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_delete_content_request( $params ) {
		if ( empty( $params['id'] ) ) {
			return true;
		}

		return $this->current_user_can_cap( 'delete_post', (int) $params['id'] ) ? true : $this->permission_error( 'Access denied for deleting this content item.' );
	}

	/**
	 * Find a post referenced by a content request.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return mixed|null
	 */
	private function get_post_for_content_request( $params ) {
		if ( ! function_exists( 'get_post' ) || empty( $params['post_type'] ) ) {
			return null;
		}

		if ( ! empty( $params['id'] ) ) {
			return get_post( (int) $params['id'] );
		}

		if ( empty( $params['slug'] ) || ! function_exists( 'get_posts' ) ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => $this->sanitize_key_value( $params['post_type'] ),
				'name'           => (string) $params['slug'],
				'post_status'    => 'any',
				'posts_per_page' => 1,
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * Check whether the current user can read an attachment.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_read_media_request( $params ) {
		return empty( $params['id'] ) || $this->current_user_can_cap( 'read_post', (int) $params['id'] ) ? true : $this->permission_error( 'Access denied for this media item.' );
	}

	/**
	 * Check whether the current user can update an attachment.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_update_media_request( $params ) {
		return empty( $params['id'] ) || $this->current_user_can_cap( 'edit_post', (int) $params['id'] ) ? true : $this->permission_error( 'Access denied for editing this media item.' );
	}

	/**
	 * Check whether the current user can delete an attachment.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_delete_media_request( $params ) {
		return empty( $params['id'] ) || $this->current_user_can_cap( 'delete_post', (int) $params['id'] ) ? true : $this->permission_error( 'Access denied for deleting this media item.' );
	}

	/**
	 * Get a taxonomy object.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return mixed|null
	 */
	private function get_taxonomy_object( $taxonomy ) {
		$taxonomy = $this->sanitize_key_value( $taxonomy );
		return function_exists( 'get_taxonomy' ) ? get_taxonomy( $taxonomy ) : null;
	}

	/**
	 * Get a taxonomy capability name.
	 *
	 * @param mixed  $taxonomy Taxonomy object.
	 * @param string $cap Capability property.
	 * @return string
	 */
	private function taxonomy_cap( $taxonomy, $cap ) {
		$fallbacks = array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		);

		return isset( $taxonomy->cap, $taxonomy->cap->$cap ) ? (string) $taxonomy->cap->$cap : $fallbacks[ $cap ];
	}

	/**
	 * Check whether the current user can list terms for a taxonomy.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_list_terms_request( $params ) {
		if ( empty( $params['taxonomy'] ) ) {
			return true;
		}

		$taxonomy = $this->get_taxonomy_object( (string) $params['taxonomy'] );
		if ( ! $taxonomy ) {
			return true;
		}

		if ( ! empty( $taxonomy->public ) ) {
			return true;
		}

		return $this->current_user_can_cap( $this->taxonomy_cap( $taxonomy, 'manage_terms' ) ) ? true : $this->permission_error( 'Access denied for this taxonomy.' );
	}

	/**
	 * Check whether the current user can create or update a term.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_save_term_request( $params ) {
		if ( empty( $params['taxonomy'] ) ) {
			return true;
		}

		$taxonomy = $this->get_taxonomy_object( (string) $params['taxonomy'] );
		if ( ! $taxonomy ) {
			return true;
		}

		$capability = ! empty( $params['id'] ) ? $this->taxonomy_cap( $taxonomy, 'edit_terms' ) : $this->taxonomy_cap( $taxonomy, 'manage_terms' );
		return $this->current_user_can_cap( $capability ) ? true : $this->permission_error( 'Access denied for saving terms in this taxonomy.' );
	}

	/**
	 * Check whether the current user can delete a term.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_delete_term_request( $params ) {
		if ( empty( $params['taxonomy'] ) ) {
			return true;
		}

		$taxonomy = $this->get_taxonomy_object( (string) $params['taxonomy'] );
		if ( ! $taxonomy ) {
			return true;
		}

		return $this->current_user_can_cap( $this->taxonomy_cap( $taxonomy, 'delete_terms' ) ) ? true : $this->permission_error( 'Access denied for deleting terms in this taxonomy.' );
	}

	/**
	 * Check whether the current user can create or update a user.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_save_user_request( $params ) {
		if ( empty( $params['id'] ) ) {
			if ( ! $this->current_user_can_cap( 'create_users' ) ) {
				return $this->permission_error( 'Access denied for creating users.' );
			}
			if ( isset( $params['role'] ) && ! $this->current_user_can_cap( 'promote_users' ) ) {
				return $this->permission_error( 'Access denied for assigning user roles.' );
			}
			return true;
		}

		$user_id = (int) $params['id'];
		if ( ! $this->current_user_can_cap( 'edit_user', $user_id ) ) {
			return $this->permission_error( 'Access denied for editing this user.' );
		}
		if ( isset( $params['role'] ) && ! $this->current_user_can_cap( 'promote_user', $user_id ) ) {
			return $this->permission_error( 'Access denied for changing this user role.' );
		}

		return true;
	}

	/**
	 * Check whether the current user can delete a user.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_delete_user_request( $params ) {
		if ( empty( $params['id'] ) ) {
			return true;
		}

		$user_id = (int) $params['id'];
		if ( function_exists( 'get_current_user_id' ) && $user_id === (int) get_current_user_id() ) {
			return $this->permission_error( 'Users cannot delete themselves through MCP.' );
		}

		return $this->current_user_can_cap( 'delete_user', $user_id ) ? true : $this->permission_error( 'Access denied for deleting this user.' );
	}

	/**
	 * Check whether an option can be managed through MCP.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_manage_option_request( $params ) {
		$option_name = isset( $params['option_name'] ) ? $this->normalize_option_name( $params['option_name'] ) : '';
		$protected   = array(
			'active_plugins',
			'admin_email',
			'cron',
			'home',
			'siteurl',
			'template',
			'stylesheet',
			'upload_path',
			'users_can_register',
		);

		if ( function_exists( 'apply_filters' ) ) {
			$protected = apply_filters( 'wp_forge_mcp_protected_options', $protected );
		}

		if ( in_array( $option_name, is_array( $protected ) ? $protected : array(), true ) ) {
			return $this->permission_error( 'Option is protected from MCP writes: ' . $option_name );
		}

		return true;
	}

	/**
	 * Check whether a global styles post can be updated.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_update_global_styles_request( $params ) {
		return empty( $params['id'] ) || $this->current_user_can_cap( 'edit_post', (int) $params['id'] ) ? true : $this->permission_error( 'Access denied for editing this global styles post.' );
	}

	/**
	 * Check whether a proxied REST request is eligible to run.
	 *
	 * The target route's own permission_callback is enforced by rest_do_request().
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_run_api_function_request( $params ) {
		$route = isset( $params['route'] ) ? (string) $params['route'] : '';
		if ( '/mcp/wp-forge' === $route ) {
			return $this->permission_error( 'The MCP transport route cannot be proxied through MCP.' );
		}

		return true;
	}

	/**
	 * Check whether a WP-CLI command is allowed.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_run_wp_cli_request( $params ) {
		$args = isset( $params['args'] ) && is_array( $params['args'] ) ? array_values( array_map( 'strval', $params['args'] ) ) : array();
		if ( ! $args ) {
			return true;
		}

		$allowed = array(
			'cache flush',
			'core version',
			'option get',
			'plugin list',
			'post list',
			'theme list',
		);

		if ( function_exists( 'apply_filters' ) ) {
			$allowed = apply_filters( 'wp_forge_mcp_allowed_wp_cli_commands', $allowed, $args );
		}

		if ( true === $allowed ) {
			return true;
		}

		$command = implode( ' ', array_slice( $args, 0, 2 ) );
		return in_array( $command, is_array( $allowed ) ? $allowed : array(), true ) ? true : $this->permission_error( 'WP-CLI command is not allowed through MCP: ' . $command );
	}

	/**
	 * Build WordPress Abilities API annotations for a tool.
	 *
	 * @param bool               $read_only Whether ability is read-only.
	 * @param array<string,bool> $overrides Annotation overrides.
	 * @return array<string,bool>
	 */
	private function core_ability_annotations( $read_only, $overrides = array() ) {
		$annotations = array(
			'readonly'    => (bool) $read_only,
			'destructive' => false,
			'idempotent'  => (bool) $read_only,
		);

		foreach ( array( 'readonly', 'destructive', 'idempotent' ) as $key ) {
			if ( array_key_exists( $key, $overrides ) ) {
				$annotations[ $key ] = (bool) $overrides[ $key ];
			}
		}

		return $annotations;
	}

	/**
	 * Convert core ability annotations to MCP tool hints.
	 *
	 * @param array<string,bool> $annotations Core ability annotations.
	 * @return array<string,bool>
	 */
	private function mcp_tool_annotations( $annotations ) {
		return array(
			'readOnlyHint'    => ! empty( $annotations['readonly'] ),
			'destructiveHint' => ! empty( $annotations['destructive'] ),
			'idempotentHint'  => ! empty( $annotations['idempotent'] ),
		);
	}

	/**
	 * Resolve an ability input schema.
	 *
	 * Some schema fields depend on WordPress runtime registrations that plugins
	 * commonly add during init, so schema factories are evaluated only when the
	 * schema is exposed.
	 *
	 * @param array<string,mixed>|callable $input_schema JSON schema or schema factory.
	 * @return array<string,mixed>
	 */
	private function resolve_input_schema( $input_schema ) {
		return is_callable( $input_schema ) ? call_user_func( $input_schema ) : $input_schema;
	}

	/**
	 * Generic response schema used by all ability wrappers.
	 *
	 * @return array<string,mixed>
	 */
	private function response_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'status'     => array(
					'type'        => 'string',
					'description' => 'The response status.',
				),
				'statusCode' => array(
					'type'        => 'integer',
					'description' => 'The HTTP-style response status code.',
				),
				'message'    => array(
					'description' => 'The ability result payload or an error message.',
					'type'        => array( 'array', 'object', 'string', 'number', 'boolean', 'null' ),
				),
				'data'       => array(
					'description' => 'The ability result data.',
					'type'        => array( 'array', 'object', 'string', 'number', 'boolean', 'null' ),
				),
			),
			'additionalProperties' => true,
		);
	}

	/**
	 * Register all non-WooCommerce abilities.
	 *
	 * @return void
	 */
	private function register_default_abilities() {
		$this->add_content_abilities();
		$this->add_taxonomy_abilities();
		$this->add_media_abilities();
		$this->add_site_abilities();
		$this->add_plugin_abilities();
		$this->add_theme_abilities();
		$this->add_option_abilities();
		$this->add_comment_abilities();
		$this->add_site_health_abilities();
		$this->add_error_log_abilities();
		$this->add_wp_cli_abilities();
		$this->add_style_abilities();
		$this->add_rest_catalog_abilities();
	}

	/**
	 * JSON object schema helper.
	 *
	 * @param array<string,mixed> $properties Properties.
	 * @param array<int,string>   $required Required keys.
	 * @return array<string,mixed>
	 */
	private function schema( $properties = array(), $required = array() ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => $properties ? $properties : new \stdClass(),
			'additionalProperties' => false,
			'default'              => new \stdClass(),
		);

		if ( $required ) {
			$schema['required'] = $required;
		}

		return $schema;
	}

	/**
	 * String schema property.
	 *
	 * @param string      $description Description.
	 * @param string|null $default Default value.
	 * @return array<string,mixed>
	 */
	private function string_prop( $description, $default = null ) {
		$prop = array(
			'type'        => 'string',
			'description' => $description,
		);

		if ( null !== $default ) {
			$prop['default'] = $default;
		}

		return $prop;
	}

	/**
	 * String schema property with a runtime enum when values are discoverable.
	 *
	 * @param string            $description Description.
	 * @param array<int,string> $values Enum values.
	 * @param string|null       $default Default value.
	 * @return array<string,mixed>
	 */
	private function enum_string_prop( $description, $values, $default = null ) {
		$values = array_values( array_unique( array_filter( array_map( 'strval', $values ), 'strlen' ) ) );
		sort( $values );

		$prop = $this->string_prop( $description, $default );
		if ( $values ) {
			$prop['enum'] = $values;
		}

		return $prop;
	}

	/**
	 * Get registered post type slugs.
	 *
	 * @return array<int,string>
	 */
	private function get_registered_post_type_slugs() {
		return function_exists( 'get_post_types' ) ? array_values( get_post_types( array(), 'names' ) ) : array();
	}

	/**
	 * Get registered taxonomy slugs.
	 *
	 * @return array<int,string>
	 */
	private function get_registered_taxonomy_slugs() {
		return function_exists( 'get_taxonomies' ) ? array_values( get_taxonomies( array(), 'names' ) ) : array();
	}

	/**
	 * Get registered post status names.
	 *
	 * @param bool $include_any Include the WP_Query any pseudo-status.
	 * @return array<int,string>
	 */
	private function get_registered_post_status_names( $include_any = false ) {
		$statuses = function_exists( 'get_post_stati' ) ? array_values( get_post_stati( array(), 'names' ) ) : array();

		if ( $include_any ) {
			$statuses[] = 'any';
		}

		return $statuses;
	}

	/**
	 * Get editable role slugs.
	 *
	 * @return array<int,string>
	 */
	private function get_editable_role_slugs() {
		if ( function_exists( 'get_editable_roles' ) ) {
			return array_keys( get_editable_roles() );
		}

		if ( function_exists( 'wp_roles' ) ) {
			$roles = wp_roles();
			if ( isset( $roles->roles ) && is_array( $roles->roles ) ) {
				return array_keys( $roles->roles );
			}
		}

		return array();
	}

	/**
	 * Get allowed MIME types.
	 *
	 * @return array<int,string>
	 */
	private function get_allowed_mime_types() {
		return function_exists( 'get_allowed_mime_types' ) ? array_values( get_allowed_mime_types() ) : array();
	}

	/**
	 * Integer schema property.
	 *
	 * @param string   $description Description.
	 * @param int|null $default Default value.
	 * @return array<string,mixed>
	 */
	private function int_prop( $description, $default = null ) {
		$prop = array(
			'type'        => 'integer',
			'description' => $description,
		);

		if ( null !== $default ) {
			$prop['default'] = $default;
		}

		return $prop;
	}

	/**
	 * Boolean schema property.
	 *
	 * @param string    $description Description.
	 * @param bool|null $default Default value.
	 * @return array<string,mixed>
	 */
	private function bool_prop( $description, $default = null ) {
		$prop = array(
			'type'        => 'boolean',
			'description' => $description,
		);

		if ( null !== $default ) {
			$prop['default'] = $default;
		}

		return $prop;
	}

	/**
	 * Normalize a WordPress key-like value.
	 *
	 * @param mixed $value Value to normalize.
	 * @return string
	 */
	private function sanitize_key_value( $value ) {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( trim( (string) $value ) ) );
	}

	/**
	 * Normalize a name prefix to MCP tool hyphen form.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private function normalize_tool_name( $name ) {
		$name = trim( str_replace( '/', '-', $name ), '-' );
		return $name ? $name : '';
	}

	/**
	 * Ensure WordPress runtime exists.
	 *
	 * @return array<string,mixed>|null
	 */
	private function require_wordpress() {
		if ( ! function_exists( 'get_posts' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return null;
	}

	/**
	 * Search content items.
	 *
	 * @param string              $post_type Post type.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function search_content_items( $post_type, $params ) {
		if ( isset( $params['status'] ) ) {
			$status_check = $this->validate_post_status( $params['status'], 'query' );
			if ( isset( $status_check['status'] ) && 'error' === $status_check['status'] ) {
				return $status_check;
			}
			$params['status'] = $status_check['post_status'];
		}

		if ( isset( $params['mime_type'] ) ) {
			$mime_type_check = $this->validate_mime_type( $params['mime_type'] );
			if ( isset( $mime_type_check['status'] ) && 'error' === $mime_type_check['status'] ) {
				return $mime_type_check;
			}
			$params['mime_type'] = $mime_type_check['mime_type'];
		}

		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => isset( $params['status'] ) ? $params['status'] : 'publish',
			's'              => isset( $params['search'] ) ? $params['search'] : '',
			'paged'          => isset( $params['page'] ) ? max( 1, (int) $params['page'] ) : 1,
			'posts_per_page' => isset( $params['per_page'] ) ? max( 1, min( 100, (int) $params['per_page'] ) ) : 10,
			'perm'           => 'readable',
		);

		if ( isset( $params['mime_type'] ) ) {
			$args['post_mime_type'] = $params['mime_type'];
		}

		return array_map( array( $this, 'format_content_item' ), get_posts( $args ) );
	}

	/**
	 * Search content for any registered post type.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function search_content( $params ) {
		$post_type_check = $this->validate_post_type( $params['post_type'] );
		if ( isset( $post_type_check['status'] ) && 'error' === $post_type_check['status'] ) {
			return $post_type_check;
		}

		if ( isset( $params['status'] ) ) {
			$status_check = $this->validate_post_status( $params['status'], 'query' );
			if ( isset( $status_check['status'] ) && 'error' === $status_check['status'] ) {
				return $status_check;
			}
			$params['status'] = $status_check['post_status'];
		}

		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$args = array(
			'post_type'      => $post_type_check['post_type'],
			'post_status'    => isset( $params['status'] ) ? $params['status'] : 'publish',
			's'              => isset( $params['query'] ) ? $params['query'] : '',
			'paged'          => isset( $params['page'] ) ? max( 1, (int) $params['page'] ) : 1,
			'posts_per_page' => isset( $params['per_page'] ) ? max( 1, min( 100, (int) $params['per_page'] ) ) : 10,
			'orderby'        => $this->normalize_content_orderby( isset( $params['orderby'] ) ? $params['orderby'] : 'date' ),
			'order'          => $this->normalize_sort_order( isset( $params['order'] ) ? $params['order'] : 'desc' ),
			'perm'           => 'readable',
		);

		if ( isset( $params['author'] ) ) {
			$args['author'] = (int) $params['author'];
		}

		if ( ! empty( $params['taxonomy_query'] ) && is_array( $params['taxonomy_query'] ) ) {
			$taxonomy = isset( $params['taxonomy_query']['taxonomy'] ) ? (string) $params['taxonomy_query']['taxonomy'] : '';
			$terms    = isset( $params['taxonomy_query']['terms'] ) && is_array( $params['taxonomy_query']['terms'] ) ? $params['taxonomy_query']['terms'] : array();

			$taxonomy_check = $this->validate_post_type_taxonomy( $post_type_check, $taxonomy );
			if ( isset( $taxonomy_check['status'] ) && 'error' === $taxonomy_check['status'] ) {
				return $taxonomy_check;
			}

			if ( '' === $taxonomy || array() === $terms ) {
				return Response::error( 'taxonomy_query requires a taxonomy and at least one term.', 400 );
			}

			$args['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => $this->taxonomy_terms_are_ids( $terms ) ? 'term_id' : 'slug',
					'terms'    => $terms,
				),
			);
		}

		if ( ! empty( $params['date_query'] ) && is_array( $params['date_query'] ) ) {
			$date_query = array();
			foreach ( array( 'after', 'before' ) as $key ) {
				if ( ! empty( $params['date_query'][ $key ] ) ) {
					$date_query[ $key ] = (string) $params['date_query'][ $key ];
				}
			}
			if ( $date_query ) {
				$args['date_query'] = array( $date_query );
			}
		}

		return array_map( array( $this, 'format_content_item' ), get_posts( $args ) );
	}

	/**
	 * Get content by ID or slug.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function get_content( $params ) {
		$post_type_check = $this->validate_post_type( $params['post_type'] );
		if ( isset( $post_type_check['status'] ) && 'error' === $post_type_check['status'] ) {
			return $post_type_check;
		}

		if ( isset( $params['id'] ) ) {
			$item = $this->get_content_item( (int) $params['id'], $post_type_check['post_type'] );
		} elseif ( ! empty( $params['slug'] ) ) {
			$item = $this->get_content_item_by_slug( $post_type_check['post_type'], (string) $params['slug'] );
		} else {
			return Response::error( 'content_get requires either id or slug.', 400 );
		}

		if ( isset( $item['status'] ) && 'error' === $item['status'] ) {
			return $item;
		}

		return $this->filter_content_fields( $item, isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array() );
	}

	/**
	 * Create or update content.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function save_content( $params ) {
		$post_type_check = $this->validate_post_type( $params['post_type'] );
		if ( isset( $post_type_check['status'] ) && 'error' === $post_type_check['status'] ) {
			return $post_type_check;
		}

		$is_update = isset( $params['id'] );
		if ( ! $is_update && ( ! isset( $params['title'] ) || '' === trim( (string) $params['title'] ) ) ) {
			return Response::error( 'content_save requires title when creating content.', 400 );
		}

		if ( isset( $params['status'] ) ) {
			$status_check = $this->validate_post_status( $params['status'], 'write' );
			if ( isset( $status_check['status'] ) && 'error' === $status_check['status'] ) {
				return $status_check;
			}
			$params['status'] = $status_check['post_status'];
		}

		if ( isset( $params['parent_id'] ) ) {
			$parent_check = $this->validate_content_parent( $post_type_check, (int) $params['parent_id'] );
			if ( isset( $parent_check['status'] ) && 'error' === $parent_check['status'] ) {
				return $parent_check;
			}
		}

		if ( isset( $params['taxonomies'] ) ) {
			$taxonomy_check = $this->validate_content_taxonomies( $post_type_check, $params['taxonomies'] );
			if ( isset( $taxonomy_check['status'] ) && 'error' === $taxonomy_check['status'] ) {
				return $taxonomy_check;
			}
		}

		if ( ! function_exists( 'wp_insert_post' ) || ! function_exists( 'wp_update_post' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$post = $this->content_post_args( $post_type_check['post_type'], $params );

		if ( $is_update ) {
			$existing = get_post( (int) $params['id'] );
			if ( ! $existing ) {
				return Response::error( 'Content item not found.', 404 );
			}
			if ( $post_type_check['post_type'] !== $existing->post_type ) {
				return Response::error( 'Content item ' . (int) $params['id'] . ' is not a ' . $post_type_check['post_type'] . '.', 400 );
			}

			$post['ID'] = (int) $params['id'];
			$id         = Response::unwrap_wp_error( wp_update_post( $post, true ) );
		} else {
			$id = Response::unwrap_wp_error( wp_insert_post( $post, true ) );
		}

		if ( is_array( $id ) && isset( $id['status'] ) && 'error' === $id['status'] ) {
			return $id;
		}

		$this->apply_content_taxonomies( (int) $id, isset( $params['taxonomies'] ) && is_array( $params['taxonomies'] ) ? $params['taxonomies'] : array() );
		$this->apply_content_meta( (int) $id, isset( $params['meta'] ) && is_array( $params['meta'] ) ? $params['meta'] : array() );
		if ( isset( $params['featured_media_id'] ) ) {
			$this->apply_featured_media( (int) $id, (int) $params['featured_media_id'] );
		}

		return $this->get_content_item( (int) $id, $post_type_check['post_type'] );
	}

	/**
	 * Delete content.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function delete_content( $params ) {
		$item = $this->get_content_item( (int) $params['id'], $params['post_type'] );
		if ( isset( $item['status'] ) && 'error' === $item['status'] ) {
			return $item;
		}

		$force  = isset( $params['force'] ) ? (bool) $params['force'] : false;
		$result = function_exists( 'wp_delete_post' ) ? wp_delete_post( (int) $params['id'], $force ) : false;

		return array(
			'id'      => (int) $params['id'],
			'deleted' => (bool) $result,
			'force'   => $force,
		);
	}

	/**
	 * Get a content item.
	 *
	 * @param int    $id Post ID.
	 * @param string $post_type Expected post type.
	 * @return array<string,mixed>
	 */
	private function get_content_item( $id, $post_type ) {
		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$post = get_post( $id );
		if ( ! $post || $post_type !== $post->post_type ) {
			return Response::error( 'Item not found.', 404 );
		}
		if ( function_exists( 'current_user_can' ) && ! $this->current_user_can_cap( 'read_post', (int) $post->ID ) ) {
			return Response::error( 'Access denied for this content item.', 403 );
		}

		return $this->format_content_item( $post );
	}

	/**
	 * Get a content item by slug.
	 *
	 * @param string $post_type Post type.
	 * @param string $slug Post slug.
	 * @return array<string,mixed>
	 */
	private function get_content_item_by_slug( $post_type, $slug ) {
		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'name'           => $slug,
				'post_status'    => 'any',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $posts ) ) {
			return Response::error( 'Item not found.', 404 );
		}
		if ( function_exists( 'current_user_can' ) && ! $this->current_user_can_cap( 'read_post', (int) $posts[0]->ID ) ) {
			return Response::error( 'Access denied for this content item.', 403 );
		}

		return $this->format_content_item( $posts[0] );
	}

	/**
	 * Insert a content item.
	 *
	 * @param string              $post_type Post type.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function insert_content_item( $post_type, $params ) {
		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_title'   => isset( $params['title'] ) ? $params['title'] : '',
				'post_content' => isset( $params['content'] ) ? $params['content'] : '',
				'post_excerpt' => isset( $params['excerpt'] ) ? $params['excerpt'] : '',
				'post_status'  => isset( $params['status'] ) ? $params['status'] : 'draft',
			),
			true
		);

		return Response::unwrap_wp_error( $id );
	}

	/**
	 * Update a content item.
	 *
	 * @param int                 $id Post ID.
	 * @param string              $post_type Post type.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|int
	 */
	private function update_content_item( $id, $post_type, $params ) {
		$item = $this->get_content_item( $id, $post_type );
		if ( isset( $item['status'] ) && 'error' === $item['status'] ) {
			return $item;
		}

		$post = array( 'ID' => $id );
		foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status' ) as $param => $field ) {
			if ( isset( $params[ $param ] ) ) {
				$post[ $field ] = $params[ $param ];
			}
		}

		return Response::unwrap_wp_error( wp_update_post( $post, true ) );
	}

	/**
	 * Delete a content item.
	 *
	 * @param int    $id Post ID.
	 * @param string $post_type Post type.
	 * @return array<string,mixed>|bool
	 */
	private function delete_content_item( $id, $post_type ) {
		$item = $this->get_content_item( $id, $post_type );
		if ( isset( $item['status'] ) && 'error' === $item['status'] ) {
			return $item;
		}

		return (bool) wp_delete_post( $id, true );
	}

	/**
	 * Format a content item.
	 *
	 * @param mixed $post Post object.
	 * @return array<string,mixed>
	 */
	private function format_content_item( $post ) {
		return array(
			'id'                => (int) $post->ID,
			'type'              => $post->post_type,
			'status'            => $post->post_status,
			'title'             => function_exists( 'get_the_title' ) ? get_the_title( $post ) : $post->post_title,
			'slug'              => $post->post_name,
			'link'              => function_exists( 'get_permalink' ) ? get_permalink( $post ) : '',
			'date'              => $post->post_date,
			'modified'          => $post->post_modified,
			'excerpt'           => $post->post_excerpt,
			'content'           => $post->post_content,
			'author'            => isset( $post->post_author ) ? (int) $post->post_author : 0,
			'parent_id'         => isset( $post->post_parent ) ? (int) $post->post_parent : 0,
			'featured_media_id' => function_exists( 'get_post_thumbnail_id' ) ? (int) get_post_thumbnail_id( $post ) : 0,
			'mime_type'         => isset( $post->post_mime_type ) ? $post->post_mime_type : '',
			'source_url'        => function_exists( 'wp_get_attachment_url' ) && 'attachment' === $post->post_type ? wp_get_attachment_url( $post->ID ) : '',
		);
	}

	/**
	 * Validate a post type and return its runtime metadata.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<string,mixed>
	 */
	private function validate_post_type( $post_type ) {
		if ( ! function_exists( 'get_post_types' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$post_type = $this->sanitize_key_value( $post_type );
		$type      = function_exists( 'get_post_type_object' ) ? get_post_type_object( $post_type ) : null;
		if ( ! $type ) {
			$types = get_post_types( array(), 'objects' );
			$type  = isset( $types[ $post_type ] ) ? $types[ $post_type ] : null;
		}

		if ( ! $type ) {
			return Response::error( 'Unknown post type: ' . $post_type, 404 );
		}

		return array(
			'post_type'    => $post_type,
			'type'         => $type,
			'hierarchical' => (bool) $type->hierarchical,
			'taxonomies'   => $this->get_post_type_taxonomies( $post_type, $type ),
		);
	}

	/**
	 * Validate parent assignment for a content type.
	 *
	 * @param array<string,mixed> $post_type_check Post type metadata.
	 * @param int                 $parent_id Parent content ID.
	 * @return array<string,mixed>
	 */
	private function validate_content_parent( $post_type_check, $parent_id ) {
		if ( ! $post_type_check['hierarchical'] ) {
			return Response::error( 'Post type ' . $post_type_check['post_type'] . ' does not support parent_id because it is not hierarchical.', 400 );
		}

		if ( $parent_id > 0 && function_exists( 'get_post' ) ) {
			$parent = get_post( $parent_id );
			if ( ! $parent || $post_type_check['post_type'] !== $parent->post_type ) {
				return Response::error( 'parent_id must reference an existing ' . $post_type_check['post_type'] . ' item.', 400 );
			}
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate a post status for content operations.
	 *
	 * WordPress registers post statuses globally. Core does not expose a reliable
	 * status-to-post-type registry, so post-type-specific rules are left to
	 * wp_insert_post/wp_update_post and any plugin filters they invoke.
	 *
	 * @param mixed  $status Status value.
	 * @param string $context Operation context: query or write.
	 * @return array<string,mixed>
	 */
	private function validate_post_status( $status, $context ) {
		$status = $this->sanitize_key_value( $status );

		if ( '' === $status ) {
			return Response::error( 'Post status cannot be empty.', 400 );
		}

		if ( 'any' === $status ) {
			if ( 'query' === $context ) {
				return array( 'post_status' => $status );
			}

			return Response::error( 'Post status any can only be used when querying content.', 400 );
		}

		if ( function_exists( 'get_post_status_object' ) && get_post_status_object( $status ) ) {
			return array( 'post_status' => $status );
		}

		$statuses = $this->get_registered_post_status_names();
		if ( $statuses && in_array( $status, $statuses, true ) ) {
			return array( 'post_status' => $status );
		}

		if ( $statuses ) {
			return Response::error( 'Unknown post status: ' . $status, 400 );
		}

		return array( 'post_status' => $status );
	}

	/**
	 * Validate a taxonomy against a post type.
	 *
	 * @param array<string,mixed> $post_type_check Post type metadata.
	 * @param string              $taxonomy Taxonomy slug.
	 * @return array<string,mixed>
	 */
	private function validate_post_type_taxonomy( $post_type_check, $taxonomy ) {
		if ( '' === $taxonomy || ! in_array( $taxonomy, $post_type_check['taxonomies'], true ) ) {
			return Response::error( 'Taxonomy ' . $taxonomy . ' is not registered for post type ' . $post_type_check['post_type'] . '.', 400 );
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate taxonomy assignments for a content type.
	 *
	 * @param array<string,mixed> $post_type_check Post type metadata.
	 * @param mixed               $taxonomies Taxonomy assignments.
	 * @return array<string,mixed>
	 */
	private function validate_content_taxonomies( $post_type_check, $taxonomies ) {
		if ( ! is_array( $taxonomies ) ) {
			return Response::error( 'taxonomies must be an object keyed by taxonomy slug.', 400 );
		}

		foreach ( $taxonomies as $taxonomy => $terms ) {
			$taxonomy_check = $this->validate_post_type_taxonomy( $post_type_check, (string) $taxonomy );
			if ( isset( $taxonomy_check['status'] ) && 'error' === $taxonomy_check['status'] ) {
				return $taxonomy_check;
			}

			if ( ! is_array( $terms ) ) {
				return Response::error( 'Taxonomy ' . $taxonomy . ' must be an array of term IDs, slugs, or names.', 400 );
			}
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate a MIME type against the allowed upload MIME type registry.
	 *
	 * @param mixed $mime_type MIME type.
	 * @return array<string,mixed>
	 */
	private function validate_mime_type( $mime_type ) {
		$mime_type = function_exists( 'sanitize_mime_type' ) ? sanitize_mime_type( $mime_type ) : strtolower( trim( (string) $mime_type ) );

		if ( '' === $mime_type ) {
			return Response::error( 'MIME type cannot be empty.', 400 );
		}

		$allowed = $this->get_allowed_mime_types();
		if ( $allowed && ! in_array( $mime_type, $allowed, true ) ) {
			return Response::error( 'Unsupported MIME type: ' . $mime_type, 400 );
		}

		return array( 'mime_type' => $mime_type );
	}

	/**
	 * Validate a user role when the role registry is available.
	 *
	 * @param mixed $role Role slug.
	 * @return array<string,mixed>
	 */
	private function validate_user_role( $role ) {
		$role = $this->sanitize_key_value( $role );

		if ( '' === $role ) {
			return Response::error( 'User role cannot be empty.', 400 );
		}

		$roles = $this->get_editable_role_slugs();
		if ( $roles && ! in_array( $role, $roles, true ) ) {
			return Response::error( 'Unknown user role: ' . $role, 400 );
		}

		return array( 'role' => $role );
	}

	/**
	 * Build wp_insert_post/wp_update_post args for content.
	 *
	 * @param string              $post_type Post type slug.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function content_post_args( $post_type, $params ) {
		$post = array( 'post_type' => $post_type );
		$map  = array(
			'title'     => 'post_title',
			'content'   => 'post_content',
			'excerpt'   => 'post_excerpt',
			'status'    => 'post_status',
			'author'    => 'post_author',
			'parent_id' => 'post_parent',
		);

		foreach ( $map as $param => $field ) {
			if ( isset( $params[ $param ] ) ) {
				$post[ $field ] = in_array( $param, array( 'author', 'parent_id' ), true ) ? (int) $params[ $param ] : $params[ $param ];
			}
		}

		if ( ! isset( $post['post_status'] ) ) {
			$post['post_status'] = 'draft';
		}

		return $post;
	}

	/**
	 * Apply taxonomy assignments to content.
	 *
	 * @param int                 $id Content item ID.
	 * @param array<string,mixed> $taxonomies Taxonomy assignments.
	 * @return void
	 */
	private function apply_content_taxonomies( $id, $taxonomies ) {
		if ( ! function_exists( 'wp_set_object_terms' ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy => $terms ) {
			wp_set_object_terms( $id, $terms, (string) $taxonomy );
		}
	}

	/**
	 * Apply post meta values to content.
	 *
	 * @param int                 $id Content item ID.
	 * @param array<string,mixed> $meta Meta values.
	 * @return void
	 */
	private function apply_content_meta( $id, $meta ) {
		if ( ! function_exists( 'update_post_meta' ) ) {
			return;
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, (string) $key, $value );
		}
	}

	/**
	 * Apply featured media to content.
	 *
	 * @param int $id Content item ID.
	 * @param int $featured_media_id Attachment ID.
	 * @return void
	 */
	private function apply_featured_media( $id, $featured_media_id ) {
		if ( function_exists( 'set_post_thumbnail' ) ) {
			set_post_thumbnail( $id, $featured_media_id );
			return;
		}

		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $id, '_thumbnail_id', $featured_media_id );
		}
	}

	/**
	 * Return only requested content fields.
	 *
	 * @param array<string,mixed> $item Content item.
	 * @param array<int,string>   $fields Field names.
	 * @return array<string,mixed>
	 */
	private function filter_content_fields( $item, $fields ) {
		if ( ! $fields ) {
			return $item;
		}

		$out = array();
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $item ) ) {
				$out[ $field ] = $item[ $field ];
			}
		}

		return $out;
	}

	/**
	 * Normalize search ordering field.
	 *
	 * @param string $orderby Orderby value.
	 * @return string
	 */
	private function normalize_content_orderby( $orderby ) {
		return in_array( $orderby, array( 'date', 'title', 'modified', 'menu_order' ), true ) ? $orderby : 'date';
	}

	/**
	 * Normalize sort direction.
	 *
	 * @param string $order Sort order.
	 * @return string
	 */
	private function normalize_sort_order( $order ) {
		return 'asc' === strtolower( (string) $order ) ? 'ASC' : 'DESC';
	}

	/**
	 * Determine whether taxonomy terms are all IDs.
	 *
	 * @param array<int,mixed> $terms Terms.
	 * @return bool
	 */
	private function taxonomy_terms_are_ids( $terms ) {
		foreach ( $terms as $term ) {
			if ( ! is_int( $term ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * List post types.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function list_post_types( $params = array() ) {
		if ( ! function_exists( 'get_post_types' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$args = array();
		foreach ( array( 'public', 'hierarchical', 'show_in_rest', 'show_ui', 'show_in_menu', 'show_in_nav_menus', 'exclude_from_search', 'publicly_queryable', '_builtin' ) as $filter ) {
			if ( array_key_exists( $filter, $params ) ) {
				$args[ $filter ] = (bool) $params[ $filter ];
			}
		}

		$types = get_post_types( $args, 'objects' );
		$out   = array();
		foreach ( $types as $type ) {
			$out[] = array(
				'slug'         => $type->name,
				'label'        => $type->label,
				'hierarchical' => (bool) $type->hierarchical,
				'public'       => (bool) $type->public,
				'supports'     => $this->get_post_type_supports( $type->name, $type ),
				'taxonomies'   => $this->get_post_type_taxonomies( $type->name, $type ),
				'rest_base'    => isset( $type->rest_base ) ? $type->rest_base : '',
			);
		}

		return array( 'post_types' => $out );
	}

	/**
	 * Get supported features for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @param mixed  $type Post type object.
	 * @return array<int,string>
	 */
	private function get_post_type_supports( $post_type, $type ) {
		if ( function_exists( 'get_all_post_type_supports' ) ) {
			$supports = get_all_post_type_supports( $post_type );
			return is_array( $supports ) ? array_keys( $supports ) : array();
		}

		if ( isset( $type->supports ) && is_array( $type->supports ) ) {
			if ( array() === $type->supports ) {
				return array();
			}

			$is_list = array_keys( $type->supports ) === range( 0, count( $type->supports ) - 1 );
			return array_values( $is_list ? $type->supports : array_keys( $type->supports ) );
		}

		return array();
	}

	/**
	 * Get taxonomies registered for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @param mixed  $type Post type object.
	 * @return array<int,string>
	 */
	private function get_post_type_taxonomies( $post_type, $type ) {
		if ( function_exists( 'get_object_taxonomies' ) ) {
			return array_values( get_object_taxonomies( $post_type, 'names' ) );
		}

		if ( isset( $type->taxonomies ) && is_array( $type->taxonomies ) ) {
			return array_values( $type->taxonomies );
		}

		return array();
	}

	/**
	 * List taxonomy terms.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return mixed
	 */
	private function list_terms( $taxonomy ) {
		if ( ! function_exists( 'get_terms' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return array_map( array( $this, 'format_term' ), get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) );
	}

	/**
	 * Get taxonomy term.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $id Term ID.
	 * @return array<string,mixed>
	 */
	private function get_term_item( $taxonomy, $id ) {
		if ( ! function_exists( 'get_term' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$term = get_term( $id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return Response::error( 'Term not found.', 404 );
		}

		return $this->format_term( $term );
	}

	/**
	 * Insert taxonomy term.
	 *
	 * @param string              $taxonomy Taxonomy.
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function insert_term( $taxonomy, $params ) {
		if ( ! function_exists( 'wp_insert_term' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$args = $this->term_args( $params );
		unset( $args['name'] );

		return Response::unwrap_wp_error( wp_insert_term( $params['name'], $taxonomy, $args ) );
	}

	/**
	 * Update taxonomy term.
	 *
	 * @param string              $taxonomy Taxonomy.
	 * @param int                 $id Term ID.
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function update_term( $taxonomy, $id, $params ) {
		if ( ! function_exists( 'wp_update_term' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return Response::unwrap_wp_error( wp_update_term( $id, $taxonomy, $this->term_args( $params ) ) );
	}

	/**
	 * Delete taxonomy term.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $id Term ID.
	 * @return mixed
	 */
	private function delete_term( $taxonomy, $id ) {
		if ( ! function_exists( 'wp_delete_term' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return Response::unwrap_wp_error( wp_delete_term( $id, $taxonomy ) );
	}

	/**
	 * Build term write arguments.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function term_args( $params ) {
		$args = array();
		foreach ( array( 'name', 'slug', 'description' ) as $key ) {
			if ( isset( $params[ $key ] ) ) {
				$args[ $key ] = $params[ $key ];
			}
		}
		return $args;
	}

	/**
	 * Format term.
	 *
	 * @param mixed $term Term object.
	 * @return array<string,mixed>
	 */
	private function format_term( $term ) {
		return array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'taxonomy'    => $term->taxonomy,
			'description' => $term->description,
			'count'       => (int) $term->count,
		);
	}

	/**
	 * Get media file as base64.
	 *
	 * @param int $id Attachment ID.
	 * @return array<string,mixed>
	 */
	private function get_media_file( $id ) {
		if ( ! function_exists( 'get_attached_file' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			return Response::error( 'File not found.', 404 );
		}

		return array(
			'id'        => $id,
			'filename'  => basename( $file ),
			'mime_type' => function_exists( 'get_post_mime_type' ) ? get_post_mime_type( $id ) : '',
			'base64'    => base64_encode( file_get_contents( $file ) ),
		);
	}

	/**
	 * Upload media.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function upload_media( $params ) {
		if ( isset( $params['mime_type'] ) ) {
			$mime_type_check = $this->validate_mime_type( $params['mime_type'] );
			if ( isset( $mime_type_check['status'] ) && 'error' === $mime_type_check['status'] ) {
				return $mime_type_check;
			}
			$params['mime_type'] = $mime_type_check['mime_type'];
		}

		if ( ! function_exists( 'wp_upload_bits' ) || ! function_exists( 'wp_insert_attachment' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$bits = base64_decode( $params['base64'], true );
		if ( false === $bits ) {
			return Response::error( 'Invalid base64 file contents.', 400 );
		}

		$upload = wp_upload_bits( $params['filename'], null, $bits );
		if ( ! empty( $upload['error'] ) ) {
			return Response::error( $upload['error'], 400 );
		}

		if ( function_exists( 'wp_check_filetype_and_ext' ) ) {
			$filetype = wp_check_filetype_and_ext( $upload['file'], $params['filename'], isset( $params['mime_type'] ) ? $params['mime_type'] : null );
			if ( empty( $filetype['type'] ) || empty( $filetype['ext'] ) ) {
				if ( file_exists( $upload['file'] ) ) {
					unlink( $upload['file'] );
				}
				return Response::error( 'Uploaded file type is not allowed.', 400 );
			}
		}

		$id = wp_insert_attachment(
			array(
				'post_title'     => isset( $params['title'] ) ? $params['title'] : $params['filename'],
				'post_mime_type' => isset( $params['mime_type'] ) ? $params['mime_type'] : $upload['type'],
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		return Response::unwrap_wp_error( $id );
	}

	/**
	 * Update media metadata.
	 *
	 * @param int                 $id Attachment ID.
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function update_media( $id, $params ) {
		$post = array( 'ID' => $id );
		foreach ( array( 'title' => 'post_title', 'caption' => 'post_excerpt', 'description' => 'post_content' ) as $param => $field ) {
			if ( isset( $params[ $param ] ) ) {
				$post[ $field ] = $params[ $param ];
			}
		}
		if ( isset( $params['alt_text'] ) && function_exists( 'update_post_meta' ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $params['alt_text'] );
		}

		return function_exists( 'wp_update_post' ) ? Response::unwrap_wp_error( wp_update_post( $post, true ) ) : Response::error( 'This ability requires a WordPress runtime.', 500 );
	}

	/**
	 * Search users.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function search_users( $params ) {
		if ( ! function_exists( 'get_users' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$args = array(
			'search' => isset( $params['search'] ) ? '*' . $params['search'] . '*' : '',
			'number' => isset( $params['per_page'] ) ? (int) $params['per_page'] : 10,
			'paged'  => isset( $params['page'] ) ? (int) $params['page'] : 1,
		);
		if ( isset( $params['role'] ) ) {
			$role_check = $this->validate_user_role( $params['role'] );
			if ( isset( $role_check['status'] ) && 'error' === $role_check['status'] ) {
				return $role_check;
			}
			$params['role'] = $role_check['role'];
			$args['role'] = $params['role'];
		}

		return array_map( array( $this, 'format_user' ), get_users( $args ) );
	}

	/**
	 * Get user.
	 *
	 * @param int $id User ID.
	 * @return mixed
	 */
	private function get_user( $id ) {
		if ( ! function_exists( 'get_user_by' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$user = get_user_by( 'id', $id );
		return $user ? $this->format_user( $user ) : Response::error( 'User not found.', 404 );
	}

	/**
	 * Save a user.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function save_user( $params ) {
		if ( isset( $params['role'] ) ) {
			$role_check = $this->validate_user_role( $params['role'] );
			if ( isset( $role_check['status'] ) && 'error' === $role_check['status'] ) {
				return $role_check;
			}
			$params['role'] = $role_check['role'];
		}

		if ( isset( $params['id'] ) ) {
			return $this->update_user( (int) $params['id'], $params );
		}

		foreach ( array( 'username', 'email', 'password' ) as $required ) {
			if ( ! isset( $params[ $required ] ) || '' === (string) $params[ $required ] ) {
				return Response::error( 'user_save requires ' . $required . ' when creating a user.', 400 );
			}
		}

		return $this->insert_user( $params );
	}

	/**
	 * Insert user.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function insert_user( $params ) {
		if ( ! function_exists( 'wp_insert_user' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return Response::unwrap_wp_error( wp_insert_user( $this->user_args( $params ) ) );
	}

	/**
	 * Update user.
	 *
	 * @param int                 $id User ID.
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function update_user( $id, $params ) {
		if ( ! function_exists( 'wp_update_user' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$args       = $this->user_args( $params );
		$args['ID'] = $id;
		return Response::unwrap_wp_error( wp_update_user( $args ) );
	}

	/**
	 * Delete user.
	 *
	 * @param int $id User ID.
	 * @return mixed
	 */
	private function delete_user( $id ) {
		if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/user.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return (bool) wp_delete_user( $id );
	}

	/**
	 * User write args.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function user_args( $params ) {
		$map  = array(
			'username'   => 'user_login',
			'email'      => 'user_email',
			'password'   => 'user_pass',
			'first_name' => 'first_name',
			'last_name'  => 'last_name',
			'role'       => 'role',
		);
		$args = array();
		foreach ( $map as $param => $field ) {
			if ( isset( $params[ $param ] ) ) {
				$args[ $field ] = $params[ $param ];
			}
		}
		return $args;
	}

	/**
	 * Format user.
	 *
	 * @param mixed $user User object.
	 * @return array<string,mixed>
	 */
	private function format_user( $user ) {
		return array(
			'id'           => (int) $user->ID,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'roles'        => isset( $user->roles ) ? array_values( $user->roles ) : array(),
		);
	}

	/**
	 * Get general settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_general_settings() {
		if ( ! function_exists( 'get_option' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$keys = array( 'blogname', 'blogdescription', 'admin_email', 'timezone_string', 'date_format', 'time_format', 'start_of_week' );
		$out  = array();
		foreach ( $keys as $key ) {
			$out[ $key ] = get_option( $key );
		}
		return $out;
	}

	/**
	 * Update general settings.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function update_general_settings( $params ) {
		if ( ! function_exists( 'update_option' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		foreach ( array_keys( $this->get_general_settings() ) as $key ) {
			if ( isset( $params[ $key ] ) ) {
				update_option( $key, $params[ $key ] );
			}
		}
		return $this->get_general_settings();
	}

	/**
	 * Get site info.
	 *
	 * @return array<string,mixed>
	 */
	private function get_site_info() {
		if ( ! function_exists( 'get_bloginfo' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => get_bloginfo( 'url' ),
			'wp_version'  => get_bloginfo( 'version' ),
			'admin_email' => function_exists( 'get_option' ) ? get_option( 'admin_email' ) : '',
			'theme'       => $this->get_active_theme(),
			'post_types'  => $this->list_post_types(),
		);
	}

	/**
	 * Get active theme.
	 *
	 * @return array<string,mixed>
	 */
	private function get_active_theme() {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$theme = wp_get_theme();
		return array(
			'name'        => $theme->get( 'Name' ),
			'version'     => $theme->get( 'Version' ),
			'author'      => $theme->get( 'Author' ),
			'stylesheet'  => $theme->get_stylesheet(),
			'template'    => $theme->get_template(),
			'description' => $theme->get( 'Description' ),
		);
	}

	/**
	 * Get global styles.
	 *
	 * @param int $id Global styles post ID.
	 * @return mixed
	 */
	private function get_global_styles( $id ) {
		return $this->get_content_item( $id, 'wp_global_styles' );
	}

	/**
	 * Update global styles.
	 *
	 * @param int                 $id Global styles post ID.
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function update_global_styles( $id, $params ) {
		$content = array();
		foreach ( array( 'settings', 'styles' ) as $key ) {
			if ( isset( $params[ $key ] ) ) {
				$content[ $key ] = $params[ $key ];
			}
		}

		return $this->update_content_item( $id, 'wp_global_styles', array( 'content' => wp_json_encode( $content ) ) );
	}

	/**
	 * Get active global styles.
	 *
	 * @return mixed
	 */
	private function get_active_global_styles() {
		$id = $this->get_active_global_styles_id();
		return $id ? $this->get_global_styles( $id ) : Response::error( 'Active global styles were not found.', 404 );
	}

	/**
	 * Get active global styles ID.
	 *
	 * @return int
	 */
	private function get_active_global_styles_id() {
		if ( class_exists( 'WP_Theme_JSON_Resolver' ) && method_exists( 'WP_Theme_JSON_Resolver', 'get_user_global_styles_post_id' ) ) {
			return (int) \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		}

		if ( function_exists( 'get_posts' ) ) {
			$posts = get_posts( array( 'post_type' => 'wp_global_styles', 'posts_per_page' => 1, 'post_status' => 'publish' ) );
			return $posts ? (int) $posts[0]->ID : 0;
		}

		return 0;
	}

	/**
	 * List REST API functions.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function list_api_functions( $params ) {
		if ( ! function_exists( 'rest_get_server' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$routes = rest_get_server()->get_routes();
		$items  = array();
		foreach ( $routes as $route => $handlers ) {
			if ( '/mcp/wp-forge' === $route ) {
				continue;
			}

			$namespace = $this->route_namespace( $route );
			if ( ! empty( $params['namespace'] ) && trim( $params['namespace'], '/' ) !== $namespace ) {
				continue;
			}
			if ( ! empty( $params['search'] ) && false === stripos( $route, $params['search'] ) ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				if ( empty( $handler['methods'] ) || ! is_array( $handler['methods'] ) ) {
					continue;
				}
				foreach ( array_keys( $handler['methods'] ) as $method ) {
					$method = strtoupper( $method );
					if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
						continue;
					}
					if ( ! empty( $params['methods'] ) && ! in_array( $method, $params['methods'], true ) ) {
						continue;
					}
					$items[] = array(
						'route'     => $route,
						'namespace' => $namespace,
						'method'    => $method,
					);
				}
			}
		}

		return $items;
	}

	/**
	 * Get REST API function details.
	 *
	 * @param string $route Route.
	 * @param string $method Method.
	 * @return mixed
	 */
	private function get_function_details( $route, $method ) {
		if ( ! function_exists( 'rest_get_server' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$routes = rest_get_server()->get_routes();
		if ( empty( $routes[ $route ] ) ) {
			return Response::error( 'REST route not found.', 404 );
		}

		foreach ( $routes[ $route ] as $handler ) {
			if ( ! empty( $handler['methods'][ strtoupper( $method ) ] ) ) {
				return array(
					'route'     => $route,
					'namespace' => $this->route_namespace( $route ),
					'method'    => strtoupper( $method ),
					'args'      => isset( $handler['args'] ) ? $handler['args'] : array(),
				);
			}
		}

		return Response::error( 'REST method not found for route.', 404 );
	}

	/**
	 * Run REST API function.
	 *
	 * @param string              $route Route.
	 * @param string              $method Method.
	 * @param array<string,mixed> $params Request params.
	 * @return mixed
	 */
	private function run_api_function( $route, $method, $params ) {
		if ( '/mcp/wp-forge' === $route ) {
			return Response::error( 'The MCP transport route cannot be called through this tool.', 400 );
		}
		if ( ! class_exists( 'WP_REST_Request' ) || ! function_exists( 'rest_do_request' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$request = new \WP_REST_Request( strtoupper( $method ), $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );
		if ( function_exists( 'rest_get_server' ) ) {
			return rest_get_server()->response_to_data( $response, false );
		}

		return $response;
	}

	/**
	 * Infer REST namespace from route.
	 *
	 * @param string $route Route.
	 * @return string
	 */
	private function route_namespace( $route ) {
		$parts = explode( '/', trim( $route, '/' ) );
		if ( count( $parts ) >= 2 && preg_match( '/^v\d+$/', $parts[1] ) ) {
			return $parts[0] . '/' . $parts[1];
		}
		return isset( $parts[0] ) ? $parts[0] : '';
	}
}
