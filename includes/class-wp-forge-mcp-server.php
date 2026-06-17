<?php
/**
 * MCP JSON-RPC server.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles MCP requests at /wp-json/mcp/wp-forge.
 */
class WP_Forge_MCP_Server {
	const REST_NAMESPACE = 'mcp';
	const REST_ROUTE     = '/wp-forge';
	const SESSION_TTL    = 86400;

	/**
	 * Ability registry.
	 *
	 * @var WP_Forge_MCP_Abilities
	 */
	private $abilities;

	/**
	 * Constructor.
	 *
	 * @param WP_Forge_MCP_Abilities $abilities Ability registry.
	 */
	public function __construct( WP_Forge_MCP_Abilities $abilities ) {
		$this->abilities = $abilities;
	}

	/**
	 * Register REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_rest_request' ),
					'permission_callback' => array( $this, 'can_use_mcp' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_session' ),
					'permission_callback' => array( $this, 'can_use_mcp' ),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'sse_not_available' ),
					'permission_callback' => array( $this, 'can_use_mcp' ),
				),
			)
		);
	}

	/**
	 * Permission check for the MCP endpoint.
	 *
	 * @return bool
	 */
	public function can_use_mcp() {
		return function_exists( 'current_user_can' ) ? current_user_can( 'edit_posts' ) : false;
	}

	/**
	 * Handle a REST POST request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_rest_request( $request ) {
		$body = $request->get_json_params();
		if ( null === $body ) {
			$raw  = $request->get_body();
			$body = json_decode( $raw, true );
		}

		$response = $this->handle( $body, $request->get_header( 'Mcp-Session-Id' ) );

		if ( null === $response ) {
			return new WP_REST_Response( null, 202 );
		}

		$status        = isset( $response['_http_status'] ) ? (int) $response['_http_status'] : 200;
		$session_id    = isset( $response['_session_id'] ) ? $response['_session_id'] : '';
		unset( $response['_http_status'], $response['_session_id'] );

		$rest_response = new WP_REST_Response( $response, $status );
		if ( $session_id ) {
			$rest_response->header( 'Mcp-Session-Id', $session_id );
		}

		return $rest_response;
	}

	/**
	 * Handle a JSON-RPC message or batch.
	 *
	 * @param mixed       $message JSON decoded message.
	 * @param string|null $session_id Session ID.
	 * @return array<string,mixed>|array<int,array<string,mixed>>|null
	 */
	public function handle( $message, $session_id = null ) {
		if ( ! is_array( $message ) ) {
			return $this->json_rpc_error( null, -32700, 'Parse error', 400 );
		}

		if ( $this->is_list( $message ) ) {
			$responses = array();
			foreach ( $message as $item ) {
				$response = $this->handle_single( $item, $session_id );
				if ( null !== $response ) {
					$responses[] = $response;
				}
			}
			return $responses ? $responses : null;
		}

		return $this->handle_single( $message, $session_id );
	}

	/**
	 * Handle one JSON-RPC message.
	 *
	 * @param mixed       $message Message.
	 * @param string|null $session_id Session ID.
	 * @return array<string,mixed>|null
	 */
	private function handle_single( $message, $session_id = null ) {
		if ( ! is_array( $message ) || ! isset( $message['method'] ) ) {
			return $this->json_rpc_error( null, -32600, 'Invalid Request', 400 );
		}

		$id     = array_key_exists( 'id', $message ) ? $message['id'] : null;
		$method = (string) $message['method'];
		$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();

		if ( 'notifications/initialized' === $method ) {
			return null;
		}

		if ( 'initialize' === $method ) {
			$new_session_id = $this->create_session_id();
			$this->store_session( $new_session_id );
			return array(
				'jsonrpc'     => '2.0',
				'id'          => $id,
				'result'      => array(
					'protocolVersion' => isset( $params['protocolVersion'] ) ? $params['protocolVersion'] : '2025-06-18',
					'capabilities'    => array( 'tools' => new stdClass() ),
					'serverInfo'      => array(
						'name'    => 'WordPress MCP',
						'version' => defined( 'WP_FORGE_MCP_VERSION' ) ? WP_FORGE_MCP_VERSION : '0.1.0',
					),
				),
				'_session_id' => $new_session_id,
			);
		}

		if ( ! $this->is_session_valid( $session_id ) ) {
			return $this->json_rpc_error( $id, -32000, 'Invalid or expired session. Send initialize first.', 400 );
		}

		if ( 'tools/list' === $method ) {
			return $this->json_rpc_success( $id, array( 'tools' => $this->gateway_tools() ) );
		}

		if ( 'tools/call' === $method ) {
			if ( empty( $params['name'] ) ) {
				return $this->json_rpc_error( $id, -32602, 'Tool name is required.', 400 );
			}

			$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
			return $this->json_rpc_success( $id, $this->call_gateway_tool( (string) $params['name'], $arguments ) );
		}

		return $this->json_rpc_error( $id, -32601, 'Method not found: ' . $method, 404 );
	}

	/**
	 * Call one of the three exposed gateway tools.
	 *
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $arguments Tool arguments.
	 * @return array<string,mixed>
	 */
	private function call_gateway_tool( $tool_name, $arguments ) {
		switch ( $tool_name ) {
			case 'wp-forge-list-abilities':
				$payload = WP_Forge_MCP_Response::success( $this->abilities->list_abilities( $arguments ) );
				break;
			case 'wp-forge-get-ability-schema':
				if ( empty( $arguments['ability_name'] ) ) {
					return $this->tool_error( 'ability_name is required.' );
				}
				$schema = $this->abilities->get_schema( $arguments['ability_name'] );
				$payload = $schema ? WP_Forge_MCP_Response::success( $schema ) : WP_Forge_MCP_Response::error( 'Unknown ability: ' . $arguments['ability_name'], 404 );
				break;
			case 'wp-forge-call-ability':
				if ( empty( $arguments['ability_name'] ) ) {
					return $this->tool_error( 'ability_name is required.' );
				}
				$payload = $this->abilities->call( $arguments['ability_name'], isset( $arguments['parameters'] ) && is_array( $arguments['parameters'] ) ? $arguments['parameters'] : array() );
				break;
			default:
				return $this->tool_error( 'Tool not found: ' . $tool_name );
		}

		return $this->tool_success( $payload );
	}

	/**
	 * Gateway tool schemas.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function gateway_tools() {
		return array(
			array(
				'name'        => 'wp-forge-list-abilities',
				'description' => 'Discover the WordPress abilities available through this MCP server.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'      => array( 'type' => 'string', 'description' => 'Search names, labels, and descriptions.' ),
						'name_prefix' => array( 'type' => 'string', 'description' => 'Filter by ability name prefix, such as wp-forge-posts.' ),
					),
				),
			),
			array(
				'name'        => 'wp-forge-get-ability-schema',
				'description' => 'Get the parameter schema for a WordPress ability.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'ability_name' ),
					'properties' => array(
						'ability_name' => array( 'type' => 'string', 'description' => 'Ability name from wp-forge-list-abilities.' ),
					),
				),
			),
			array(
				'name'        => 'wp-forge-call-ability',
				'description' => 'Run a WordPress ability by name.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'ability_name' ),
					'properties' => array(
						'ability_name' => array( 'type' => 'string', 'description' => 'Ability name from wp-forge-list-abilities.' ),
						'parameters'   => array( 'type' => 'object', 'description' => 'Parameters that match the ability schema.' ),
					),
				),
			),
		);
	}

	/**
	 * Format successful JSON-RPC response.
	 *
	 * @param mixed $id Request ID.
	 * @param mixed $result Result.
	 * @return array<string,mixed>
	 */
	private function json_rpc_success( $id, $result ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Format JSON-RPC error.
	 *
	 * @param mixed  $id Request ID.
	 * @param int    $code Error code.
	 * @param string $message Error message.
	 * @param int    $status HTTP status.
	 * @return array<string,mixed>
	 */
	private function json_rpc_error( $id, $code, $message, $status = 400 ) {
		return array(
			'jsonrpc'      => '2.0',
			'id'           => $id,
			'error'        => array(
				'code'    => $code,
				'message' => $message,
			),
			'_http_status' => $status,
		);
	}

	/**
	 * Format MCP tool success.
	 *
	 * @param array<string,mixed> $payload Structured content.
	 * @return array<string,mixed>
	 */
	private function tool_success( $payload ) {
		return array(
			'content'           => array(
				array(
					'type' => 'text',
					'text' => wp_json_encode( $payload ),
				),
			),
			'structuredContent' => $payload,
		);
	}

	/**
	 * Format MCP tool error.
	 *
	 * @param string $message Message.
	 * @return array<string,mixed>
	 */
	private function tool_error( $message ) {
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError' => true,
		);
	}

	/**
	 * Create a session ID.
	 *
	 * @return string
	 */
	private function create_session_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Persist a session ID when WordPress transients are available.
	 *
	 * @param string $session_id Session ID.
	 * @return void
	 */
	private function store_session( $session_id ) {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'wp_forge_mcp_session_' . $session_id, time(), self::SESSION_TTL );
		}
	}

	/**
	 * Check session validity.
	 *
	 * @param string|null $session_id Session ID.
	 * @return bool
	 */
	private function is_session_valid( $session_id ) {
		if ( ! is_string( $session_id ) || '' === $session_id ) {
			return false;
		}

		if ( function_exists( 'get_transient' ) ) {
			return false !== get_transient( 'wp_forge_mcp_session_' . $session_id );
		}

		return true;
	}

	/**
	 * DELETE handler.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_session() {
		return new WP_REST_Response( null, 204 );
	}

	/**
	 * GET handler until SSE is implemented.
	 *
	 * @return WP_REST_Response
	 */
	public function sse_not_available() {
		return new WP_REST_Response( array( 'message' => 'SSE is not available yet. Use POST for MCP messages.' ), 405 );
	}

	/**
	 * Whether an array is a list.
	 *
	 * @param array<mixed> $array Array.
	 * @return bool
	 */
	private function is_list( $array ) {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $array );
		}

		return array_keys( $array ) === range( 0, count( $array ) - 1 );
	}
}
