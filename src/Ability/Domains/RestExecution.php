<?php
/**
 * REST execution domain for WordPress MCP abilities.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Ability\Domains;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides rest execution domain behavior.
 */
trait RestExecution {
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

		$routes          = rest_get_server()->get_routes();
		$allowed_methods = $this->get_rest_api_methods_from_routes( $routes );
		$method_filter   = array();
		$items           = array();

		if ( ! empty( $params['methods'] ) && is_array( $params['methods'] ) ) {
			$method_filter = array_values(
				array_unique(
					array_map( 'strtoupper', array_map( 'strval', $params['methods'] ) )
				)
			);
		}
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
					if ( ! in_array( $method, $allowed_methods, true ) ) {
						continue;
					}
					if ( $method_filter && ! in_array( $method, $method_filter, true ) ) {
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
	 * Get supported REST methods from registered route handlers.
	 *
	 * @return array<int,string>
	 */
	private function get_rest_api_methods() {
		if ( ! function_exists( 'rest_get_server' ) ) {
			return array();
		}

		return $this->get_rest_api_methods_from_routes( rest_get_server()->get_routes() );
	}

	/**
	 * Get supported REST methods from route data.
	 *
	 * @param array<string,mixed> $routes REST routes.
	 * @return array<int,string>
	 */
	private function get_rest_api_methods_from_routes( $routes ) {
		$methods = array();

		foreach ( $routes as $route => $handlers ) {
			if ( '/mcp/wp-forge' === $route || ! is_array( $handlers ) ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				if ( empty( $handler['methods'] ) || ! is_array( $handler['methods'] ) ) {
					continue;
				}

				foreach ( array_keys( $handler['methods'] ) as $method ) {
					$method = strtoupper( trim( (string) $method ) );
					if ( '' !== $method ) {
						$methods[] = $method;
					}
				}
			}
		}

		$methods = array_values( array_unique( $methods ) );
		sort( $methods );

		return $methods;
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
