<?php
/**
 * Ability catalog for WordPress MCP.
 *
 * @package WP_Forge
 */

namespace WP_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers wp-forge abilities and dispatches calls.
 */
class Abilities {
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
			'annotations'  => array( 'readonly' => (bool) $read_only ),
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
		$this->add_media_abilities();
		$this->add_site_abilities();
		$this->add_style_abilities();
		$this->add_rest_catalog_abilities();
	}

	/**
	 * Content and custom post type abilities.
	 *
	 * @return void
	 */
	private function add_content_abilities() {
		$search_schema = $this->schema(
			array(
				'search'   => $this->string_prop( 'Search term.' ),
				'page'     => $this->int_prop( 'Page number.', 1 ),
				'per_page' => $this->int_prop( 'Items per page.', 10 ),
				'status'   => $this->string_prop( 'Post status.', 'publish' ),
			)
		);
		$get_schema    = $this->schema( array( 'id' => $this->int_prop( 'Item ID.' ) ), array( 'id' ) );
		$write_schema  = $this->schema(
			array(
				'title'   => $this->string_prop( 'Title.' ),
				'content' => $this->string_prop( 'Content.' ),
				'excerpt' => $this->string_prop( 'Excerpt.' ),
				'status'  => $this->string_prop( 'Status.', 'draft' ),
			)
		);
		$update_schema = $write_schema;
		$update_schema['properties']['id'] = $this->int_prop( 'Item ID.' );
		$update_schema['required']         = array( 'id' );

		foreach ( array( 'post' => 'post', 'page' => 'page' ) as $post_type => $singular ) {
			$plural = 'post' === $post_type ? 'posts' : 'pages';
			$label  = ucfirst( $singular );

			$this->add_ability( self::INTERNAL_PREFIX . $plural . '-search', 'Search ' . ucfirst( $plural ), 'Search and filter WordPress ' . $plural . ' with pagination', $search_schema, function ( $params ) use ( $post_type ) {
				return $this->query_posts( $post_type, $params );
			} );
			$this->add_ability( self::INTERNAL_PREFIX . 'get-' . $singular, 'Get ' . $label, 'Get a WordPress ' . $singular . ' by ID', $get_schema, function ( $params ) use ( $post_type ) {
				return $this->get_post_item( (int) $params['id'], $post_type );
			} );
			$this->add_ability( self::INTERNAL_PREFIX . 'add-' . $singular, 'Add ' . $label, 'Add a new WordPress ' . $singular, $write_schema, function ( $params ) use ( $post_type ) {
				return $this->insert_post_item( $post_type, $params );
			}, false );
			$this->add_ability( self::INTERNAL_PREFIX . 'update-' . $singular, 'Update ' . $label, 'Update a WordPress ' . $singular . ' by ID', $update_schema, function ( $params ) use ( $post_type ) {
				return $this->update_post_item( (int) $params['id'], $post_type, $params );
			}, false );
			$this->add_ability( self::INTERNAL_PREFIX . 'delete-' . $singular, 'Delete ' . $label, 'Delete a WordPress ' . $singular . ' by ID', $get_schema, function ( $params ) use ( $post_type ) {
				return $this->delete_post_item( (int) $params['id'], $post_type );
			}, false );
		}

		$cpt_schema = $search_schema;
		$cpt_schema['properties']['post_type'] = $this->string_prop( 'Custom post type name.' );
		$cpt_schema['required'] = array( 'post_type' );

		$cpt_get_schema = $this->schema(
			array(
				'post_type' => $this->string_prop( 'Custom post type name.' ),
				'id'        => $this->int_prop( 'Item ID.' ),
			),
			array( 'post_type', 'id' )
		);
		$cpt_write_schema = $write_schema;
		$cpt_write_schema['properties']['post_type'] = $this->string_prop( 'Custom post type name.' );
		$cpt_write_schema['required'] = array( 'post_type' );
		$cpt_update_schema = $cpt_write_schema;
		$cpt_update_schema['properties']['id'] = $this->int_prop( 'Item ID.' );
		$cpt_update_schema['required'] = array( 'post_type', 'id' );

		$this->add_ability( self::INTERNAL_PREFIX . 'list-post-types', 'List Post Types', 'List all registered WordPress post types (built-in and custom)', $this->schema(), function () {
			return $this->list_post_types();
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'cpt-search', 'Search Custom Post Type', 'Search and filter content items within a custom post type with pagination', $cpt_schema, function ( $params ) {
			return $this->query_posts( $params['post_type'], $params );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-cpt', 'Get Custom Post Type Item', 'Get a single content item from a custom post type by ID', $cpt_get_schema, function ( $params ) {
			return $this->get_post_item( (int) $params['id'], $params['post_type'] );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'add-cpt', 'Add Custom Post Type Item', 'Create a new content item within an existing custom post type', $cpt_write_schema, function ( $params ) {
			return $this->insert_post_item( $params['post_type'], $params );
		}, false );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-cpt', 'Update Custom Post Type Item', 'Update an existing content item in a custom post type by ID', $cpt_update_schema, function ( $params ) {
			return $this->update_post_item( (int) $params['id'], $params['post_type'], $params );
		}, false );
		$this->add_ability( self::INTERNAL_PREFIX . 'delete-cpt', 'Delete Custom Post Type Item', 'Permanently delete a content item from a custom post type by ID', $cpt_get_schema, function ( $params ) {
			return $this->delete_post_item( (int) $params['id'], $params['post_type'] );
		}, false );
	}

	/**
	 * Taxonomy abilities.
	 *
	 * @return void
	 */
	private function add_taxonomy_abilities() {
		$write_schema = $this->schema(
			array(
				'name'        => $this->string_prop( 'Term name.' ),
				'slug'        => $this->string_prop( 'Term slug.' ),
				'description' => $this->string_prop( 'Description.' ),
			),
			array( 'name' )
		);
		$update_schema = $write_schema;
		$update_schema['properties']['id'] = $this->int_prop( 'Term ID.' );
		$update_schema['required'] = array( 'id' );
		$delete_schema = $this->schema( array( 'id' => $this->int_prop( 'Term ID.' ) ), array( 'id' ) );

		$this->add_taxonomy_group( 'category', 'categories', 'category', 'Category', $write_schema, $update_schema, $delete_schema );
		$this->add_taxonomy_group( 'post_tag', 'tags', 'tag', 'Tag', $write_schema, $update_schema, $delete_schema );
	}

	/**
	 * Register abilities for one taxonomy.
	 *
	 * @param string              $taxonomy Taxonomy name.
	 * @param string              $slug Tool slug.
	 * @param string              $singular Singular tool slug.
	 * @param string              $label Label.
	 * @param array<string,mixed> $write_schema Write schema.
	 * @param array<string,mixed> $update_schema Update schema.
	 * @param array<string,mixed> $delete_schema Delete schema.
	 * @return void
	 */
	private function add_taxonomy_group( $taxonomy, $slug, $singular, $label, $write_schema, $update_schema, $delete_schema ) {
		$this->add_ability( self::INTERNAL_PREFIX . 'list-' . $slug, 'List ' . $label . 's', 'List all WordPress post ' . strtolower( $label ) . 's', $this->schema(), function () use ( $taxonomy ) {
			return $this->list_terms( $taxonomy );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'add-' . $singular, 'Add ' . $label, 'Add a new WordPress post ' . strtolower( $label ), $write_schema, function ( $params ) use ( $taxonomy ) {
			return $this->insert_term( $taxonomy, $params );
		}, false );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-' . $singular, 'Update ' . $label, 'Update a WordPress post ' . strtolower( $label ), $update_schema, function ( $params ) use ( $taxonomy ) {
			return $this->update_term( $taxonomy, (int) $params['id'], $params );
		}, false );
		$this->add_ability( self::INTERNAL_PREFIX . 'delete-' . $singular, 'Delete ' . $label, 'Delete a WordPress post ' . strtolower( $label ), $delete_schema, function ( $params ) use ( $taxonomy ) {
			return $this->delete_term( $taxonomy, (int) $params['id'] );
		}, false );
	}

	/**
	 * Media abilities.
	 *
	 * @return void
	 */
	private function add_media_abilities() {
		$list_schema = $this->schema(
			array(
				'search'    => $this->string_prop( 'Search term.' ),
				'mime_type' => $this->string_prop( 'MIME type filter.' ),
				'page'      => $this->int_prop( 'Page number.', 1 ),
				'per_page'  => $this->int_prop( 'Items per page.', 10 ),
			)
		);
		$id_schema = $this->schema( array( 'id' => $this->int_prop( 'Media item ID.' ) ), array( 'id' ) );
		$update_schema = $this->schema(
			array(
				'id'          => $this->int_prop( 'Media item ID.' ),
				'title'       => $this->string_prop( 'Title.' ),
				'caption'     => $this->string_prop( 'Caption.' ),
				'description' => $this->string_prop( 'Description.' ),
				'alt_text'    => $this->string_prop( 'Alt text.' ),
			),
			array( 'id' )
		);
		$upload_schema = $this->schema(
			array(
				'filename' => $this->string_prop( 'File name.' ),
				'mime_type' => $this->string_prop( 'MIME type.' ),
				'base64'   => $this->string_prop( 'Base64-encoded file contents.' ),
				'title'    => $this->string_prop( 'Title.' ),
			),
			array( 'filename', 'base64' )
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'list-media', 'List Media', 'List WordPress media items with pagination and filtering', $list_schema, function ( $params ) {
			return $this->query_posts( 'attachment', array_merge( array( 'status' => 'inherit' ), $params ) );
		}, true, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-media', 'Get Media', 'Get a WordPress media item by ID', $id_schema, function ( $params ) {
			return $this->get_post_item( (int) $params['id'], 'attachment' );
		}, true, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-media-file', 'Get Media File', 'Get the actual file content of a WordPress media item', $id_schema, function ( $params ) {
			return $this->get_media_file( (int) $params['id'] );
		}, true, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'upload-media', 'Upload Media', 'Upload a new media file to WordPress', $upload_schema, function ( $params ) {
			return $this->upload_media( $params );
		}, false, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-media', 'Update Media', 'Update a WordPress media item', $update_schema, function ( $params ) {
			return $this->update_media( (int) $params['id'], $params );
		}, false, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'delete-media', 'Delete Media', 'Delete a WordPress media item permanently', $id_schema, function ( $params ) {
			return $this->delete_post_item( (int) $params['id'], 'attachment' );
		}, false, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'search-media', 'Search Media', 'Search WordPress media by title, caption, or description', $list_schema, function ( $params ) {
			return $this->query_posts( 'attachment', array_merge( array( 'status' => 'inherit' ), $params ) );
		}, true, 'upload_files' );
	}

	/**
	 * Site management abilities.
	 *
	 * @return void
	 */
	private function add_site_abilities() {
		$this->add_ability( self::INTERNAL_PREFIX . 'users-search', 'Search Users', 'Search and filter WordPress users with pagination', $this->schema(
			array(
				'search'   => $this->string_prop( 'Search term.' ),
				'role'     => $this->string_prop( 'User role.' ),
				'page'     => $this->int_prop( 'Page number.', 1 ),
				'per_page' => $this->int_prop( 'Users per page.', 10 ),
			)
		), function ( $params ) {
			return $this->search_users( $params );
		}, true, 'list_users' );

		$user_get_schema = $this->schema( array( 'id' => $this->int_prop( 'User ID.' ) ), array( 'id' ) );
		$user_write_schema = $this->schema(
			array(
				'username'   => $this->string_prop( 'Username.' ),
				'email'      => $this->string_prop( 'Email address.' ),
				'password'   => $this->string_prop( 'Password.' ),
				'first_name' => $this->string_prop( 'First name.' ),
				'last_name'  => $this->string_prop( 'Last name.' ),
				'role'       => $this->string_prop( 'Role.' ),
			),
			array( 'username', 'email', 'password' )
		);
		$user_update_schema = $user_write_schema;
		$user_update_schema['properties']['id'] = $this->int_prop( 'User ID.' );
		$user_update_schema['required'] = array( 'id' );

		$this->add_ability( self::INTERNAL_PREFIX . 'get-user', 'Get User', 'Get a WordPress user by ID', $user_get_schema, function ( $params ) {
			return $this->get_user( (int) $params['id'] );
		}, true, 'list_users' );
		$this->add_ability( self::INTERNAL_PREFIX . 'add-user', 'Add User', 'Add a new WordPress user', $user_write_schema, function ( $params ) {
			return $this->insert_user( $params );
		}, false, 'create_users' );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-user', 'Update User', 'Update a WordPress user by ID', $user_update_schema, function ( $params ) {
			return $this->update_user( (int) $params['id'], $params );
		}, false, 'edit_users' );
		$this->add_ability( self::INTERNAL_PREFIX . 'delete-user', 'Delete User', 'Delete a WordPress user by ID', $user_get_schema, function ( $params ) {
			return $this->delete_user( (int) $params['id'] );
		}, false, 'delete_users' );

		$settings_schema = $this->schema(
			array(
				'blogname'        => $this->string_prop( 'Site title.' ),
				'blogdescription' => $this->string_prop( 'Tagline.' ),
				'admin_email'     => $this->string_prop( 'Administration email address.' ),
				'timezone_string' => $this->string_prop( 'Timezone.' ),
				'date_format'     => $this->string_prop( 'Date format.' ),
				'time_format'     => $this->string_prop( 'Time format.' ),
				'start_of_week'   => $this->int_prop( 'Start of week.' ),
			)
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'get-general-settings', 'Get General Settings', 'Get WordPress general site settings', $this->schema(), function () {
			return $this->get_general_settings();
		}, true, 'manage_options' );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-general-settings', 'Update General Settings', 'Update WordPress general site settings', $settings_schema, function ( $params ) {
			return $this->update_general_settings( $params );
		}, false, 'manage_options' );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-site-info', 'Get Site Info', 'Get detailed site information', $this->schema(), function () {
			return $this->get_site_info();
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-active-theme', 'Get Active Theme', 'Get the active theme information', $this->schema(), function () {
			return $this->get_active_theme();
		} );
	}

	/**
	 * Global styles abilities.
	 *
	 * @return void
	 */
	private function add_style_abilities() {
		$id_schema = $this->schema( array( 'id' => $this->int_prop( 'Global styles post ID.' ) ), array( 'id' ) );
		$update_schema = $this->schema(
			array(
				'id'       => $this->int_prop( 'Global styles post ID.' ),
				'settings' => array( 'type' => 'object', 'description' => 'theme.json settings object.' ),
				'styles'   => array( 'type' => 'object', 'description' => 'theme.json styles object.' ),
			),
			array( 'id' )
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'get-global-styles', 'Get Global Styles', 'Get a global styles configuration by ID', $id_schema, function ( $params ) {
			return $this->get_global_styles( (int) $params['id'] );
		}, true, 'edit_theme_options' );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-global-styles', 'Update Global Styles', 'Update a global styles configuration', $update_schema, function ( $params ) {
			return $this->update_global_styles( (int) $params['id'], $params );
		}, false, 'edit_theme_options' );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-active-global-styles', 'Get Active Global Styles', 'Get the currently active global styles for the current theme', $this->schema(), function () {
			return $this->get_active_global_styles();
		}, true, 'edit_theme_options' );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-active-global-styles-id', 'Get Active Global Styles ID', 'Get the active global styles ID', $this->schema(), function () {
			return array( 'id' => $this->get_active_global_styles_id() );
		}, true, 'edit_theme_options' );
	}

	/**
	 * REST catalog abilities.
	 *
	 * @return void
	 */
	private function add_rest_catalog_abilities() {
		$this->add_ability( self::INTERNAL_PREFIX . 'list-api-functions', 'List API Functions', 'List available WordPress REST API endpoints that support CRUD', $this->schema(
			array(
				'namespace' => $this->string_prop( 'REST namespace, such as wp/v2.' ),
				'methods'   => array(
					'type'        => 'array',
					'description' => 'HTTP methods to include.',
					'items'       => array( 'type' => 'string', 'enum' => array( 'GET', 'POST', 'PATCH', 'DELETE' ) ),
				),
				'search'    => $this->string_prop( 'Route search term.' ),
			)
		), function ( $params ) {
			return $this->list_api_functions( $params );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-function-details', 'Get Function Details', 'Get detailed metadata for a specific REST API route and HTTP method', $this->schema(
			array(
				'route'  => $this->string_prop( 'REST route.' ),
				'method' => $this->string_prop( 'HTTP method.' ),
			),
			array( 'route', 'method' )
		), function ( $params ) {
			return $this->get_function_details( $params['route'], $params['method'] );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'run-api-function', 'Run API Function', 'Execute a REST API request by route, method, and parameters', $this->schema(
			array(
				'route'      => $this->string_prop( 'REST route.' ),
				'method'     => $this->string_prop( 'HTTP method.' ),
				'parameters' => array( 'type' => 'object', 'description' => 'Request parameters.' ),
			),
			array( 'route', 'method' )
		), function ( $params ) {
			return $this->run_api_function( $params['route'], $params['method'], isset( $params['parameters'] ) ? $params['parameters'] : array() );
		}, false );
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
			'properties'           => $properties,
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
