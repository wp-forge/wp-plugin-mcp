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
	 *
	 * @param ActivityLogger|null $activity_logger Activity logger.
	 */
	public function __construct( ?ActivityLogger $activity_logger = null ) {
		$this->activity_logger = $activity_logger ?? new ActivityLogger();
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
		$status_code = $this->get_status_code_from_tags( $tags );

		$this->activity_logger->log_tool_call(
			array(
				'tool_name'   => $tool_name,
				'status'      => $status,
				'status_code' => $status_code,
				'duration_ms' => null === $duration_ms ? 0 : (int) ceil( $duration_ms ),
				'session_id'  => isset( $tags['session_id'] ) && is_string( $tags['session_id'] ) ? $tags['session_id'] : '',
			)
		);
	}

	/**
	 * Get an HTTP-like status code from observability tags when one is present.
	 *
	 * @param array<string,mixed> $tags Event tags.
	 * @return int
	 */
	private function get_status_code_from_tags( array $tags ): int {
		foreach ( array( 'status_code', 'statusCode', 'http_status', 'httpStatus', 'response_code', 'responseCode' ) as $key ) {
			if ( ! isset( $tags[ $key ] ) ) {
				continue;
			}

			$status_code = filter_var(
				$tags[ $key ],
				FILTER_VALIDATE_INT,
				array(
					'options' => array(
						'min_range' => 100,
						'max_range' => 599,
					),
				)
			);

			if ( false !== $status_code ) {
				return (int) $status_code;
			}
		}

		return 0;
	}
}
