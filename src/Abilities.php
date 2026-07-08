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
use WP_Forge\Ability\Concerns\Permissions as AbilityPermissions;
use WP_Forge\Ability\Concerns\SchemaHelpers as AbilitySchemaHelpers;
use WP_Forge\Ability\Concerns\Validation as AbilityValidation;
use WP_Forge\Ability\Domains\Content as ContentDomain;
use WP_Forge\Ability\Domains\GlobalStyles as GlobalStylesDomain;
use WP_Forge\Ability\Domains\Media as MediaDomain;
use WP_Forge\Ability\Domains\RestExecution as RestExecutionDomain;
use WP_Forge\Ability\Domains\Site as SiteDomain;
use WP_Forge\Ability\Domains\TaxonomyTerms as TaxonomyTermDomain;
use WP_Forge\Ability\Domains\Users as UserDomain;

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
	use AbilitySchemaHelpers;
	use AbilityPermissions;
	use AbilityValidation;
	use ContentDomain;
	use TaxonomyTermDomain;
	use MediaDomain;
	use UserDomain;
	use SiteDomain;
	use GlobalStylesDomain;
	use RestExecutionDomain;

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
}
