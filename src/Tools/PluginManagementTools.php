<?php
/**
 * Plugin management MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin management tools.
 */
trait PluginManagementTools {
	/**
	 * Plugin management abilities.
	 *
	 * @return void
	 */
	private function add_plugin_abilities() {
		$plugin_file_schema = $this->schema(
			array(
				'plugin_file' => $this->string_prop( 'Plugin file path, such as akismet/akismet.php.' ),
			),
			array( 'plugin_file' )
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'list-plugins', 'List Plugins', 'List installed WordPress plugins and their activation state', $this->schema(), function () {
			return $this->list_plugins();
		}, true, 'activate_plugins' );

		$this->add_ability( self::INTERNAL_PREFIX . 'install-plugin', 'Install Plugin', 'Install a WordPress plugin from the WordPress.org plugin directory by slug', $this->schema(
			array(
				'slug' => $this->string_prop( 'WordPress.org plugin slug, such as akismet.' ),
			),
			array( 'slug' )
		), function ( $params ) {
			return $this->install_plugin( $params['slug'] );
		}, false, 'install_plugins' );

		$this->add_ability( self::INTERNAL_PREFIX . 'activate-plugin', 'Activate Plugin', 'Activate an installed WordPress plugin by plugin file path', $plugin_file_schema, function ( $params ) {
			return $this->activate_plugin_tool( $params['plugin_file'] );
		}, false, 'activate_plugins' );

		$this->add_ability( self::INTERNAL_PREFIX . 'deactivate-plugin', 'Deactivate Plugin', 'Deactivate an active WordPress plugin by plugin file path', $plugin_file_schema, function ( $params ) {
			return $this->deactivate_plugin_tool( $params['plugin_file'] );
		}, false, 'activate_plugins' );

		$this->add_ability( self::INTERNAL_PREFIX . 'uninstall-plugin', 'Uninstall Plugin', 'Deactivate and delete an installed WordPress plugin by plugin file path', $plugin_file_schema, function ( $params ) {
			return $this->uninstall_plugin( $params['plugin_file'] );
		}, false, 'delete_plugins' );
	}

	/**
	 * List installed plugins.
	 *
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function list_plugins() {
		$missing = $this->require_plugin_runtime();
		if ( $missing ) {
			return $missing;
		}

		$plugins = get_plugins();
		$items   = array();

		foreach ( $plugins as $plugin_file => $plugin ) {
			$items[] = array(
				'plugin_file'    => $plugin_file,
				'name'           => isset( $plugin['Name'] ) ? $plugin['Name'] : '',
				'description'    => isset( $plugin['Description'] ) ? wp_strip_all_tags( $plugin['Description'] ) : '',
				'version'        => isset( $plugin['Version'] ) ? $plugin['Version'] : '',
				'author'         => isset( $plugin['Author'] ) ? wp_strip_all_tags( $plugin['Author'] ) : '',
				'plugin_uri'     => isset( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : '',
				'active'         => is_plugin_active( $plugin_file ),
				'network_active' => is_plugin_active_for_network( $plugin_file ),
			);
		}

		return $items;
	}

	/**
	 * Install a plugin from WordPress.org by slug.
	 *
	 * @param string $slug Plugin slug.
	 * @return array<string,mixed>
	 */
	private function install_plugin( $slug ) {
		$missing = $this->require_plugin_runtime();
		if ( $missing ) {
			return $missing;
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$slug = sanitize_key( $slug );
		$api  = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);

		if ( is_wp_error( $api ) ) {
			return Response::error( $api->get_error_message(), 400 );
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return Response::error( $result->get_error_message(), 400 );
		}

		if ( is_wp_error( $skin->result ) ) {
			return Response::error( $skin->result->get_error_message(), 400 );
		}

		if ( ! $result ) {
			return Response::error( 'Plugin installation failed.', 400 );
		}

		return array(
			'slug'       => $slug,
			'name'       => isset( $api->name ) ? $api->name : $slug,
			'plugin'     => $upgrader->plugin_info(),
			'installed'  => true,
			'activated'  => false,
		);
	}

	/**
	 * Activate a plugin.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return array<string,mixed>
	 */
	private function activate_plugin_tool( $plugin_file ) {
		$missing = $this->require_plugin_runtime();
		if ( $missing ) {
			return $missing;
		}

		$plugin_file = $this->normalize_plugin_file( $plugin_file );
		$exists      = $this->plugin_exists( $plugin_file );
		if ( ! $exists ) {
			return Response::error( 'Plugin not found: ' . $plugin_file, 404 );
		}

		$result = activate_plugin( $plugin_file );
		if ( is_wp_error( $result ) ) {
			return Response::error( $result->get_error_message(), 400 );
		}

		return array(
			'plugin_file' => $plugin_file,
			'active'      => is_plugin_active( $plugin_file ),
		);
	}

	/**
	 * Deactivate a plugin.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return array<string,mixed>
	 */
	private function deactivate_plugin_tool( $plugin_file ) {
		$missing = $this->require_plugin_runtime();
		if ( $missing ) {
			return $missing;
		}

		$plugin_file = $this->normalize_plugin_file( $plugin_file );
		if ( ! $this->plugin_exists( $plugin_file ) ) {
			return Response::error( 'Plugin not found: ' . $plugin_file, 404 );
		}

		deactivate_plugins( $plugin_file );

		return array(
			'plugin_file' => $plugin_file,
			'active'      => is_plugin_active( $plugin_file ),
		);
	}

	/**
	 * Uninstall a plugin.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return array<string,mixed>
	 */
	private function uninstall_plugin( $plugin_file ) {
		$missing = $this->require_plugin_runtime();
		if ( $missing ) {
			return $missing;
		}

		$plugin_file = $this->normalize_plugin_file( $plugin_file );
		if ( ! $this->plugin_exists( $plugin_file ) ) {
			return Response::error( 'Plugin not found: ' . $plugin_file, 404 );
		}

		if ( defined( 'WP_FORGE_MCP_FILE' ) && plugin_basename( WP_FORGE_MCP_FILE ) === $plugin_file ) {
			return Response::error( 'WordPress MCP cannot uninstall itself from an MCP request.', 400 );
		}

		deactivate_plugins( $plugin_file );
		$result = delete_plugins( array( $plugin_file ) );

		if ( is_wp_error( $result ) ) {
			return Response::error( $result->get_error_message(), 400 );
		}

		if ( ! $result ) {
			return Response::error( 'Plugin uninstall failed.', 400 );
		}

		return array(
			'plugin_file' => $plugin_file,
			'uninstalled' => true,
		);
	}

	/**
	 * Ensure plugin management functions exist.
	 *
	 * @return array<string,mixed>|null
	 */
	private function require_plugin_runtime() {
		if ( ! defined( 'ABSPATH' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		if ( ! function_exists( 'get_plugins' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			return Response::error( 'This ability requires a WordPress plugin runtime.', 500 );
		}

		return null;
	}

	/**
	 * Normalize plugin file path.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return string
	 */
	private function normalize_plugin_file( $plugin_file ) {
		return plugin_basename( sanitize_text_field( wp_unslash( $plugin_file ) ) );
	}

	/**
	 * Check if a plugin exists.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return bool
	 */
	private function plugin_exists( $plugin_file ) {
		$plugins = get_plugins();
		return isset( $plugins[ $plugin_file ] );
	}
}
