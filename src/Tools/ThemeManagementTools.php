<?php
/**
 * Theme management MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers theme management tools.
 */
trait ThemeManagementTools {
	/**
	 * Theme management abilities.
	 *
	 * @return void
	 */
	private function add_theme_abilities() {
		$stylesheet_schema = $this->schema(
			array(
				'stylesheet' => $this->string_prop( 'Theme stylesheet directory name.' ),
			),
			array( 'stylesheet' )
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'list-themes', 'List Themes', 'List installed WordPress themes and their activation state', $this->schema(), function () {
			return $this->list_themes();
		}, true, 'switch_themes' );

		$this->add_ability( self::INTERNAL_PREFIX . 'install-theme', 'Install Theme', 'Install a WordPress theme from the WordPress.org theme directory by slug', $this->schema(
			array(
				'slug' => $this->string_prop( 'WordPress.org theme slug, such as twentytwentyfive.' ),
			),
			array( 'slug' )
		), function ( $params ) {
			return $this->install_theme( $params['slug'] );
		}, false, 'install_themes' );

		$this->add_ability( self::INTERNAL_PREFIX . 'activate-theme', 'Activate Theme', 'Activate an installed WordPress theme by stylesheet directory name', $stylesheet_schema, function ( $params ) {
			return $this->activate_theme_tool( $params['stylesheet'] );
		}, false, 'switch_themes' );

		$this->add_ability( self::INTERNAL_PREFIX . 'delete-theme', 'Delete Theme', 'Delete an installed WordPress theme by stylesheet directory name', $stylesheet_schema, function ( $params ) {
			return $this->delete_theme_tool( $params['stylesheet'] );
		}, false, 'delete_themes' );
	}

	/**
	 * List installed themes.
	 *
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function list_themes() {
		if ( ! function_exists( 'wp_get_themes' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$active = function_exists( 'wp_get_theme' ) ? wp_get_theme()->get_stylesheet() : '';
		$themes = array();

		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$themes[] = array(
				'stylesheet'  => $stylesheet,
				'name'        => $theme->get( 'Name' ),
				'version'     => $theme->get( 'Version' ),
				'author'      => wp_strip_all_tags( $theme->get( 'Author' ) ),
				'description' => $theme->get( 'Description' ),
				'template'    => $theme->get_template(),
				'active'      => $stylesheet === $active,
			);
		}

		return $themes;
	}

	/**
	 * Install a theme from WordPress.org.
	 *
	 * @param string $slug Theme slug.
	 * @return array<string,mixed>
	 */
	private function install_theme( $slug ) {
		if ( ! defined( 'ABSPATH' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		if ( ! function_exists( 'themes_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$slug = sanitize_key( $slug );
		$api  = themes_api( 'theme_information', array( 'slug' => $slug ) );

		if ( is_wp_error( $api ) ) {
			return Response::error( $api->get_error_message(), 400 );
		}

		$skin = new \Automatic_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return Response::error( $result->get_error_message(), 400 );
		}

		if ( is_wp_error( $skin->result ) ) {
			return Response::error( $skin->result->get_error_message(), 400 );
		}

		if ( ! $result ) {
			return Response::error( 'Theme installation failed.', 400 );
		}

		return array(
			'slug'      => $slug,
			'name'      => isset( $api->name ) ? $api->name : $slug,
			'installed' => true,
			'activated' => false,
		);
	}

	/**
	 * Activate a theme.
	 *
	 * @param string $stylesheet Stylesheet.
	 * @return array<string,mixed>
	 */
	private function activate_theme_tool( $stylesheet ) {
		if ( ! function_exists( 'wp_get_theme' ) || ! function_exists( 'switch_theme' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$stylesheet = sanitize_key( $stylesheet );
		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return Response::error( 'Theme not found: ' . $stylesheet, 404 );
		}

		switch_theme( $stylesheet );

		return array(
			'stylesheet' => $stylesheet,
			'active'     => wp_get_theme()->get_stylesheet() === $stylesheet,
		);
	}

	/**
	 * Delete a theme.
	 *
	 * @param string $stylesheet Stylesheet.
	 * @return array<string,mixed>
	 */
	private function delete_theme_tool( $stylesheet ) {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}
		if ( ! function_exists( 'delete_theme' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		$stylesheet = sanitize_key( $stylesheet );
		if ( wp_get_theme()->get_stylesheet() === $stylesheet ) {
			return Response::error( 'The active theme cannot be deleted.', 400 );
		}

		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return Response::error( 'Theme not found: ' . $stylesheet, 404 );
		}

		$result = delete_theme( $stylesheet );
		if ( is_wp_error( $result ) ) {
			return Response::error( $result->get_error_message(), 400 );
		}

		return array(
			'stylesheet' => $stylesheet,
			'deleted'    => (bool) $result,
		);
	}
}
