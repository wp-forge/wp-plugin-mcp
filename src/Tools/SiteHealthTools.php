<?php
/**
 * Site health MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers site health tools.
 */
trait SiteHealthTools {
	/**
	 * Site health abilities.
	 *
	 * @return void
	 */
	private function add_site_health_abilities() {
		$this->add_ability( self::INTERNAL_PREFIX . 'get-site-health-info', 'Get Site Health Info', 'Get WordPress Site Health debug information', $this->schema(), function () {
			return $this->get_site_health_info();
		}, true, 'view_site_health_checks' );

		$this->add_ability( self::INTERNAL_PREFIX . 'list-site-health-tests', 'List Site Health Tests', 'List available WordPress Site Health tests', $this->schema(), function () {
			return $this->list_site_health_tests();
		}, true, 'view_site_health_checks' );
	}

	/**
	 * Get site health debug info.
	 *
	 * @return mixed
	 */
	private function get_site_health_info() {
		if ( ! class_exists( 'WP_Debug_Data' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/class-wp-debug-data.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		}

		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			return Response::error( 'This ability requires a WordPress Site Health runtime.', 500 );
		}

		return \WP_Debug_Data::debug_data();
	}

	/**
	 * List site health tests.
	 *
	 * @return mixed
	 */
	private function list_site_health_tests() {
		if ( ! class_exists( 'WP_Site_Health' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/class-wp-site-health.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}

		if ( ! class_exists( 'WP_Site_Health' ) ) {
			return Response::error( 'This ability requires a WordPress Site Health runtime.', 500 );
		}

		$site_health = \WP_Site_Health::get_instance();
		return $site_health->get_tests();
	}
}
