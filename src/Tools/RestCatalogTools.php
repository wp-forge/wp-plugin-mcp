<?php
/**
 * REST catalog MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST catalog tools.
 */
trait RestCatalogTools {
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
}
