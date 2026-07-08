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
		$method_prop = function () {
			return $this->enum_string_prop( 'HTTP method.', $this->get_rest_api_methods() );
		};
		$list_schema = function () use ( $method_prop ) {
			return $this->schema(
				array(
					'namespace' => $this->string_prop( 'REST namespace, such as wp/v2.' ),
					'methods'   => array(
						'type'        => 'array',
						'description' => 'HTTP methods to include.',
						'items'       => $method_prop(),
					),
					'search'    => $this->string_prop( 'Route search term.' ),
				)
			);
		};
		$this->add_ability( self::INTERNAL_PREFIX . 'api-function-list', 'List API Functions', 'List available WordPress REST API endpoints that support CRUD', $list_schema, function ( $params ) {
			return $this->list_api_functions( $params );
		} );
		$details_schema = function () use ( $method_prop ) {
			return $this->schema(
				array(
					'route'  => $this->string_prop( 'REST route.' ),
					'method' => $method_prop(),
				),
				array( 'route', 'method' )
			);
		};
		$this->add_ability( self::INTERNAL_PREFIX . 'api-function-details-get', 'Get Function Details', 'Get detailed metadata for a specific REST API route and HTTP method', $details_schema, function ( $params ) {
			return $this->get_function_details( $params['route'], $params['method'] );
		} );
		$run_schema = function () use ( $method_prop ) {
			return $this->schema(
				array(
					'route'      => $this->string_prop( 'REST route.' ),
					'method'     => $method_prop(),
					'parameters' => array( 'type' => 'object', 'description' => 'Request parameters.' ),
				),
				array( 'route', 'method' )
			);
		};
		$this->add_ability( self::INTERNAL_PREFIX . 'api-function-run', 'Run API Function', 'Execute a REST API request by route, method, and parameters', $run_schema, function ( $params ) {
			return $this->run_api_function( $params['route'], $params['method'], isset( $params['parameters'] ) ? $params['parameters'] : array() );
		}, false, 'read', array(), array( $this, 'can_run_api_function_request' ) );
	}
}
