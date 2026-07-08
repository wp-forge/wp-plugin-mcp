<?php
/**
 * Site domain for WordPress MCP abilities.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Ability\Domains;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides site domain behavior.
 */
trait Site {
	/**
	 * Get general settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_general_settings() {
		if ( ! function_exists( 'get_option' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$keys = array( 'blogname', 'blogdescription', 'admin_email', 'timezone_string', 'date_format', 'time_format', 'start_of_week' );
		$out  = array();
		foreach ( $keys as $key ) {
			$out[ $key ] = get_option( $key );
		}
		return $out;
	}

	/**
	 * Update general settings.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function update_general_settings( $params ) {
		if ( ! function_exists( 'update_option' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		foreach ( array_keys( $this->get_general_settings() ) as $key ) {
			if ( isset( $params[ $key ] ) ) {
				update_option( $key, $params[ $key ] );
			}
		}
		return $this->get_general_settings();
	}

	/**
	 * Get site info.
	 *
	 * @return array<string,mixed>
	 */
	private function get_site_info() {
		if ( ! function_exists( 'get_bloginfo' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => get_bloginfo( 'url' ),
			'wp_version'  => get_bloginfo( 'version' ),
			'admin_email' => function_exists( 'get_option' ) ? get_option( 'admin_email' ) : '',
			'theme'       => $this->get_active_theme(),
			'post_types'  => $this->list_post_types(),
		);
	}

	/**
	 * Get active theme.
	 *
	 * @return array<string,mixed>
	 */
	private function get_active_theme() {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$theme = wp_get_theme();
		return array(
			'name'        => $theme->get( 'Name' ),
			'version'     => $theme->get( 'Version' ),
			'author'      => $theme->get( 'Author' ),
			'stylesheet'  => $theme->get_stylesheet(),
			'template'    => $theme->get_template(),
			'description' => $theme->get( 'Description' ),
		);
	}
}
