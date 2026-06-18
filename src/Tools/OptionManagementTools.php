<?php
/**
 * Option management MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers option management tools.
 */
trait OptionManagementTools {
	/**
	 * Option management abilities.
	 *
	 * @return void
	 */
	private function add_option_abilities() {
		$option_schema = $this->schema(
			array(
				'option_name' => $this->string_prop( 'Option name.' ),
			),
			array( 'option_name' )
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'list-options', 'List Options', 'List WordPress options by search or prefix', $this->schema(
			array(
				'search'      => $this->string_prop( 'Search term for option names.' ),
				'name_prefix' => $this->string_prop( 'Option name prefix.' ),
				'per_page'    => $this->int_prop( 'Maximum number of options to return.', 50 ),
			)
		), function ( $params ) {
			return $this->list_options( $params );
		}, true, 'manage_options' );

		$this->add_ability( self::INTERNAL_PREFIX . 'get-option', 'Get Option', 'Get a WordPress option value by name', $option_schema, function ( $params ) {
			return $this->get_option_tool( $params['option_name'] );
		}, true, 'manage_options' );

		$this->add_ability( self::INTERNAL_PREFIX . 'update-option', 'Update Option', 'Update a WordPress option value by name', $this->schema(
			array(
				'option_name' => $this->string_prop( 'Option name.' ),
				'value'       => array( 'description' => 'Option value.', 'type' => array( 'string', 'number', 'integer', 'boolean', 'array', 'object', 'null' ) ),
				'autoload'    => $this->string_prop( 'Autoload behavior: yes, no, on, off, auto, auto-on, or auto-off.' ),
			),
			array( 'option_name', 'value' )
		), function ( $params ) {
			return $this->update_option_tool( $params );
		}, false, 'manage_options' );

		$this->add_ability( self::INTERNAL_PREFIX . 'delete-option', 'Delete Option', 'Delete a WordPress option by name', $option_schema, function ( $params ) {
			return $this->delete_option_tool( $params['option_name'] );
		}, false, 'manage_options' );
	}

	/**
	 * List options.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function list_options( $params ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! function_exists( 'maybe_unserialize' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$where = '1=1';
		$args = array();

		if ( ! empty( $params['search'] ) ) {
			$where .= ' AND option_name LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $params['search'] ) . '%';
		}

		if ( ! empty( $params['name_prefix'] ) ) {
			$where .= ' AND option_name LIKE %s';
			$args[] = $wpdb->esc_like( $params['name_prefix'] ) . '%';
		}

		$limit = isset( $params['per_page'] ) ? max( 1, min( 200, (int) $params['per_page'] ) ) : 50;
		$sql = "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE {$where} ORDER BY option_name ASC LIMIT %d";
		$args[] = $limit;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		return array_map( function ( $row ) {
			return array(
				'option_name' => $row['option_name'],
				'value'       => maybe_unserialize( $row['option_value'] ),
				'autoload'    => $row['autoload'],
			);
		}, $rows );
	}

	/**
	 * Get an option.
	 *
	 * @param string $option_name Option name.
	 * @return array<string,mixed>
	 */
	private function get_option_tool( $option_name ) {
		if ( ! function_exists( 'get_option' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$option_name = $this->normalize_option_name( $option_name );
		$value = get_option( $option_name, null );

		return array(
			'option_name' => $option_name,
			'value'       => $value,
			'exists'      => null !== $value,
		);
	}

	/**
	 * Update an option.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function update_option_tool( $params ) {
		if ( ! function_exists( 'update_option' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$option_name = $this->normalize_option_name( $params['option_name'] );
		$autoload = isset( $params['autoload'] ) ? sanitize_key( $params['autoload'] ) : null;
		$result = null === $autoload ? update_option( $option_name, $params['value'] ) : update_option( $option_name, $params['value'], $autoload );

		return array(
			'option_name' => $option_name,
			'updated'     => (bool) $result,
			'value'       => get_option( $option_name ),
		);
	}

	/**
	 * Delete an option.
	 *
	 * @param string $option_name Option name.
	 * @return array<string,mixed>
	 */
	private function delete_option_tool( $option_name ) {
		if ( ! function_exists( 'delete_option' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$option_name = $this->normalize_option_name( $option_name );

		return array(
			'option_name' => $option_name,
			'deleted'     => (bool) delete_option( $option_name ),
		);
	}

	/**
	 * Normalize option names without lowercasing or stripping valid WordPress option characters.
	 *
	 * @param mixed $option_name Option name.
	 * @return string
	 */
	private function normalize_option_name( $option_name ) {
		if ( function_exists( 'wp_unslash' ) ) {
			$option_name = wp_unslash( $option_name );
		}

		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $option_name );
		}

		return trim( (string) $option_name );
	}
}
