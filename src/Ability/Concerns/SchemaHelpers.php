<?php
/**
 * Ability schema helpers for WordPress MCP abilities.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Ability\Concerns;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides schema and annotation behavior.
 */
trait SchemaHelpers {
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
}
