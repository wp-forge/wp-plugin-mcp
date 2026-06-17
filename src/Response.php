<?php
/**
 * Response helpers for WordPress MCP ability calls.
 *
 * @package WP_Forge
 */

namespace WP_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats ability responses consistently for MCP clients.
 */
class Response {
	/**
	 * Build a successful ability response.
	 *
	 * @param mixed $message Ability payload.
	 * @param int   $status_code HTTP-like status code.
	 * @return array<string,mixed>
	 */
	public static function success( $message, $status_code = 200 ) {
		return array(
			'statusCode' => $status_code,
			'status'     => 'success',
			'message'    => $message,
		);
	}

	/**
	 * Build an error ability response.
	 *
	 * @param string $message Error message.
	 * @param int    $status_code HTTP-like status code.
	 * @return array<string,mixed>
	 */
	public static function error( $message, $status_code = 400 ) {
		return array(
			'statusCode' => $status_code,
			'status'     => 'error',
			'message'    => $message,
		);
	}

	/**
	 * Convert WP_Error to a response, when WordPress is available.
	 *
	 * @param mixed $value Possible WP_Error.
	 * @return mixed
	 */
	public static function unwrap_wp_error( $value ) {
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $value ) ) {
			return self::error( $value->get_error_message(), 400 );
		}

		return $value;
	}
}
