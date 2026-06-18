<?php
/**
 * Error log MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers error log tools.
 */
trait ErrorLogTools {
	/**
	 * Error log abilities.
	 *
	 * @return void
	 */
	private function add_error_log_abilities() {
		$this->add_ability( self::INTERNAL_PREFIX . 'get-error-log-path', 'Get Error Log Path', 'Get the WordPress debug log path used by this site', $this->schema(), function () {
			return array( 'path' => $this->get_debug_log_path() );
		}, true, 'manage_options' );

		$this->add_ability( self::INTERNAL_PREFIX . 'read-error-log', 'Read Error Log', 'Read the tail of the WordPress debug log', $this->schema(
			array(
				'lines' => $this->int_prop( 'Number of log lines to return.', 200 ),
			)
		), function ( $params ) {
			return $this->read_error_log( isset( $params['lines'] ) ? (int) $params['lines'] : 200 );
		}, true, 'manage_options' );
	}

	/**
	 * Get debug log path.
	 *
	 * @return string
	 */
	private function get_debug_log_path() {
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) {
			return WP_DEBUG_LOG;
		}

		return defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/debug.log' : '';
	}

	/**
	 * Read the debug log.
	 *
	 * @param int $lines Lines.
	 * @return array<string,mixed>
	 */
	private function read_error_log( $lines ) {
		$path = $this->get_debug_log_path();
		if ( ! $path ) {
			return Response::error( 'Could not determine the WordPress debug log path.', 500 );
		}

		if ( ! file_exists( $path ) ) {
			return array(
				'path'    => $path,
				'exists'  => false,
				'content' => '',
			);
		}

		if ( ! is_readable( $path ) ) {
			return Response::error( 'The WordPress debug log is not readable.', 403 );
		}

		$lines = max( 1, min( 1000, $lines ) );
		$content = file( $path, FILE_IGNORE_NEW_LINES );
		$tail = array_slice( is_array( $content ) ? $content : array(), -1 * $lines );

		return array(
			'path'    => $path,
			'exists'  => true,
			'lines'   => count( $tail ),
			'content' => implode( "\n", $tail ),
		);
	}
}
