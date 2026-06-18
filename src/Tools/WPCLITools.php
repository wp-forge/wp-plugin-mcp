<?php
/**
 * WP-CLI MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers WP-CLI tools.
 */
trait WPCLITools {
	/**
	 * WP-CLI abilities.
	 *
	 * @return void
	 */
	private function add_wp_cli_abilities() {
		$this->add_ability( self::INTERNAL_PREFIX . 'run-wp-cli-command', 'Run WP-CLI Command', 'Run a WP-CLI command when WP-CLI execution is explicitly enabled and available', $this->schema(
			array(
				'args'    => array(
					'type'        => 'array',
					'description' => 'WP-CLI arguments without the leading wp, such as ["plugin", "list", "--format=json"].',
					'items'       => array( 'type' => 'string' ),
				),
				'timeout' => $this->int_prop( 'Maximum seconds to wait for the command.', 30 ),
			),
			array( 'args' )
		), function ( $params ) {
			return $this->run_wp_cli_command( $params );
		}, false, 'manage_options' );
	}

	/**
	 * Run a WP-CLI command.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function run_wp_cli_command( $params ) {
		if ( ! $this->is_wp_cli_execution_enabled() ) {
			return Response::error( 'WP-CLI execution is disabled. Define WP_FORGE_MCP_ENABLE_WP_CLI as true or enable the wp_forge_mcp_enable_wp_cli filter to use this tool.', 403 );
		}

		if ( ! function_exists( 'proc_open' ) ) {
			return Response::error( 'WP-CLI execution requires proc_open to be available.', 500 );
		}

		if ( empty( $params['args'] ) || ! is_array( $params['args'] ) ) {
			return Response::error( 'WP-CLI args must be a non-empty array of strings.', 400 );
		}

		$args = array();
		foreach ( $params['args'] as $arg ) {
			if ( ! is_scalar( $arg ) ) {
				return Response::error( 'WP-CLI args must be strings.', 400 );
			}
			$args[] = (string) $arg;
		}

		$binary = $this->get_wp_cli_binary();
		if ( ! $binary ) {
			return Response::error( 'WP-CLI is not available to the web server user.', 404 );
		}

		$timeout = isset( $params['timeout'] ) ? max( 1, min( 120, (int) $params['timeout'] ) ) : 30;
		$command = array_merge( array( $binary, '--path=' . ABSPATH ), $args );
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( $command, $descriptors, $pipes, ABSPATH );
		if ( ! is_resource( $process ) ) {
			return Response::error( 'Could not start WP-CLI.', 500 );
		}

		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );

		$stdout = '';
		$stderr = '';
		$started = time();
		$timed_out = false;

		do {
			$stdout .= stream_get_contents( $pipes[1] );
			$stderr .= stream_get_contents( $pipes[2] );
			$status = proc_get_status( $process );

			if ( $status['running'] && time() - $started >= $timeout ) {
				$timed_out = true;
				proc_terminate( $process );
				break;
			}

			if ( $status['running'] ) {
				usleep( 100000 );
			}
		} while ( $status['running'] );

		$stdout .= stream_get_contents( $pipes[1] );
		$stderr .= stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit_code = proc_close( $process );

		return array(
			'command'   => implode( ' ', array_map( array( $this, 'quote_wp_cli_arg' ), $command ) ),
			'exit_code' => $timed_out ? null : $exit_code,
			'timed_out' => $timed_out,
			'stdout'    => $stdout,
			'stderr'    => $stderr,
		);
	}

	/**
	 * Check whether WP-CLI execution is enabled.
	 *
	 * @return bool
	 */
	private function is_wp_cli_execution_enabled() {
		$enabled = defined( 'WP_FORGE_MCP_ENABLE_WP_CLI' ) && WP_FORGE_MCP_ENABLE_WP_CLI;

		if ( function_exists( 'apply_filters' ) ) {
			$enabled = (bool) apply_filters( 'wp_forge_mcp_enable_wp_cli', $enabled );
		}

		return $enabled;
	}

	/**
	 * Locate a WP-CLI binary.
	 *
	 * @return string
	 */
	private function get_wp_cli_binary() {
		$candidates = array();

		if ( defined( 'WP_FORGE_MCP_WP_CLI_PATH' ) && WP_FORGE_MCP_WP_CLI_PATH ) {
			$candidates[] = WP_FORGE_MCP_WP_CLI_PATH;
		}

		$env_path = getenv( 'WP_CLI_PATH' );
		if ( $env_path ) {
			$candidates[] = $env_path;
		}

		$candidates[] = 'wp';

		foreach ( $candidates as $candidate ) {
			if ( 'wp' === $candidate || is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Quote a WP-CLI argument for display.
	 *
	 * @param string $arg Argument.
	 * @return string
	 */
	private function quote_wp_cli_arg( $arg ) {
		return false === strpos( $arg, ' ' ) ? $arg : escapeshellarg( $arg );
	}
}
