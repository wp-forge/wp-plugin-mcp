<?php
/**
 * Global styles domain for WordPress MCP abilities.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Ability\Domains;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides global styles domain behavior.
 */
trait GlobalStyles {
	/**
	 * Get global styles.
	 *
	 * @param int $id Global styles post ID.
	 * @return mixed
	 */
	private function get_global_styles( $id ) {
		return $this->get_content_item( $id, 'wp_global_styles' );
	}

	/**
	 * Update global styles.
	 *
	 * @param int                 $id Global styles post ID.
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function update_global_styles( $id, $params ) {
		$content = array();
		foreach ( array( 'settings', 'styles' ) as $key ) {
			if ( isset( $params[ $key ] ) ) {
				$content[ $key ] = $params[ $key ];
			}
		}

		return $this->update_content_item( $id, 'wp_global_styles', array( 'content' => wp_json_encode( $content ) ) );
	}

	/**
	 * Get active global styles.
	 *
	 * @return mixed
	 */
	private function get_active_global_styles() {
		$id = $this->get_active_global_styles_id();
		return $id ? $this->get_global_styles( $id ) : Response::error( 'Active global styles were not found.', 404 );
	}

	/**
	 * Get active global styles ID.
	 *
	 * @return int
	 */
	private function get_active_global_styles_id() {
		if ( class_exists( 'WP_Theme_JSON_Resolver' ) && method_exists( 'WP_Theme_JSON_Resolver', 'get_user_global_styles_post_id' ) ) {
			return (int) \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		}

		if ( function_exists( 'get_posts' ) ) {
			$posts = get_posts( array( 'post_type' => 'wp_global_styles', 'posts_per_page' => 1, 'post_status' => 'publish' ) );
			return $posts ? (int) $posts[0]->ID : 0;
		}

		return 0;
	}
}
