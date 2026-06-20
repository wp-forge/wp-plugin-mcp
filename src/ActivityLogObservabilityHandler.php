<?php
/**
 * MCP adapter observability handler for the activity log.
 *
 * @package WP_Forge
 */

namespace WP_Forge;

use WP\MCP\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records MCP tool call observability events in the plugin activity log.
 */
class ActivityLogObservabilityHandler implements McpObservabilityHandlerInterface {
	/**
	 * Activity logger.
	 *
	 * @var ActivityLogger
	 */
	private $activity_logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->activity_logger = new ActivityLogger();
	}

	/**
	 * Record an MCP adapter observability event.
	 *
	 * @param string     $event Event name.
	 * @param array      $tags Event tags.
	 * @param float|null $duration_ms Duration in milliseconds.
	 * @return void
	 */
	public function record_event( string $event, array $tags = array(), ?float $duration_ms = null ): void {
		if ( 'mcp.request' !== $event || 'tools/call' !== ( $tags['method'] ?? '' ) ) {
			return;
		}

		$tool_name = $tags['tool_name'] ?? '';
		if ( ! is_string( $tool_name ) || '' === $tool_name ) {
			return;
		}

		$status = isset( $tags['status'] ) && is_string( $tags['status'] ) ? $tags['status'] : '';

		$this->activity_logger->log_tool_call(
			array(
				'tool_name'   => $tool_name,
				'status'      => $status,
				'status_code' => 'error' === $status ? 500 : 200,
				'duration_ms' => null === $duration_ms ? 0 : (int) ceil( $duration_ms ),
				'session_id'  => isset( $tags['session_id'] ) && is_string( $tags['session_id'] ) ? $tags['session_id'] : '',
			)
		);
	}
}
