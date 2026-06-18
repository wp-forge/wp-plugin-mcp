<?php
/**
 * Ability catalog for WordPress MCP.
 *
 * @package WP_Forge
 */

namespace WP_Forge;

use WP_Forge\Tools\ContentManagementTools;
use WP_Forge\Tools\CommentManagementTools;
use WP_Forge\Tools\CustomTaxonomyTools;
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers wp-forge abilities and dispatches calls.
 */
class Abilities {
	use ContentManagementTools;
	use CommentManagementTools;
	use CustomTaxonomyTools;
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
				'inputSchema' => $ability['input_schema'],
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
			'input_schema' => $ability['input_schema'],
			'annotations'  => $ability['annotations'],
		);
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

		$capability = isset( $this->abilities[ $internal ]['capability'] ) ? $this->abilities[ $internal ]['capability'] : 'edit_posts';
		if ( function_exists( 'current_user_can' ) && ! current_user_can( $capability ) ) {
			return Response::error( 'Access denied for ability: ' . $this->ability_to_tool_name( $internal ), 403 );
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
	 * @param array<string,mixed> $input_schema JSON schema.
	 * @param callable            $callback Callback.
	 * @param bool                $read_only Whether ability is read-only.
	 * @param string              $capability Required WordPress capability.
	 * @return void
	 */
	private function add_ability( $name, $label, $description, $input_schema, $callback, $read_only = true, $capability = 'edit_posts' ) {
		$this->abilities[ $name ] = array(
			'label'        => $label,
			'description'  => $description,
			'input_schema' => $input_schema,
			'callback'     => $callback,
			'annotations'  => array( 'readOnlyHint' => (bool) $read_only ),
			'capability'   => $capability,
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
		$this->add_custom_taxonomy_abilities();
		$this->add_media_abilities();
		$this->add_site_abilities();
		$this->add_plugin_abilities();
		$this->add_theme_abilities();
		$this->add_option_abilities();
		$this->add_comment_abilities();
		$this->add_site_health_abilities();
		$this->add_error_log_abilities();
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
	 * Query posts.
	 *
	 * @param string              $post_type Post type.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function query_posts( $post_type, $params ) {
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
		);

		if ( isset( $params['mime_type'] ) ) {
			$args['post_mime_type'] = $params['mime_type'];
		}

		return array_map( array( $this, 'format_post' ), get_posts( $args ) );
	}

	/**
	 * Get a post item.
	 *
	 * @param int    $id Post ID.
	 * @param string $post_type Expected post type.
	 * @return array<string,mixed>
	 */
	private function get_post_item( $id, $post_type ) {
		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$post = get_post( $id );
		if ( ! $post || $post_type !== $post->post_type ) {
			return Response::error( 'Item not found.', 404 );
		}

		return $this->format_post( $post );
	}

	/**
	 * Insert post item.
	 *
	 * @param string              $post_type Post type.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function insert_post_item( $post_type, $params ) {
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
	 * Update post item.
	 *
	 * @param int                 $id Post ID.
	 * @param string              $post_type Post type.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|int
	 */
	private function update_post_item( $id, $post_type, $params ) {
		$item = $this->get_post_item( $id, $post_type );
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
	 * Delete post item.
	 *
	 * @param int    $id Post ID.
	 * @param string $post_type Post type.
	 * @return array<string,mixed>|bool
	 */
	private function delete_post_item( $id, $post_type ) {
		$item = $this->get_post_item( $id, $post_type );
		if ( isset( $item['status'] ) && 'error' === $item['status'] ) {
			return $item;
		}

		return (bool) wp_delete_post( $id, true );
	}

	/**
	 * Format a post object.
	 *
	 * @param mixed $post Post object.
	 * @return array<string,mixed>
	 */
	private function format_post( $post ) {
		return array(
			'id'          => (int) $post->ID,
			'type'        => $post->post_type,
			'status'      => $post->post_status,
			'title'       => function_exists( 'get_the_title' ) ? get_the_title( $post ) : $post->post_title,
			'slug'        => $post->post_name,
			'link'        => function_exists( 'get_permalink' ) ? get_permalink( $post ) : '',
			'date'        => $post->post_date,
			'modified'    => $post->post_modified,
			'excerpt'     => $post->post_excerpt,
			'content'     => $post->post_content,
			'mime_type'   => isset( $post->post_mime_type ) ? $post->post_mime_type : '',
			'source_url'  => function_exists( 'wp_get_attachment_url' ) && 'attachment' === $post->post_type ? wp_get_attachment_url( $post->ID ) : '',
		);
	}

	/**
	 * List post types.
	 *
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function list_post_types() {
		if ( ! function_exists( 'get_post_types' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$types = get_post_types( array(), 'objects' );
		$out   = array();
		foreach ( $types as $type ) {
			$out[] = array(
				'name'        => $type->name,
				'label'       => $type->label,
				'description' => $type->description,
				'public'      => (bool) $type->public,
				'rest_base'   => $type->rest_base,
			);
		}
		return $out;
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

		return Response::unwrap_wp_error( wp_insert_term( $params['name'], $taxonomy, $this->term_args( $params ) ) );
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
		foreach ( array( 'slug', 'description' ) as $key ) {
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
		return $this->get_post_item( $id, 'wp_global_styles' );
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

		return $this->update_post_item( $id, 'wp_global_styles', array( 'content' => wp_json_encode( $content ) ) );
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
			return (int) WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
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
