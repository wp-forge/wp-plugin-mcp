<?php
/**
 * Site management MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers user, settings, site info, and theme tools.
 */
trait SiteManagementTools {
	/**
	 * Site management abilities.
	 *
	 * @return void
	 */
	private function add_site_abilities() {
		$this->add_ability( self::INTERNAL_PREFIX . 'users-search', 'Search Users', 'Search and filter WordPress users with pagination', $this->schema(
			array(
				'search'   => $this->string_prop( 'Search term.' ),
				'role'     => $this->string_prop( 'User role.' ),
				'page'     => $this->int_prop( 'Page number.', 1 ),
				'per_page' => $this->int_prop( 'Users per page.', 10 ),
			)
		), function ( $params ) {
			return $this->search_users( $params );
		}, true, 'list_users' );

		$user_get_schema = $this->schema( array( 'id' => $this->int_prop( 'User ID.' ) ), array( 'id' ) );
		$user_write_schema = $this->schema(
			array(
				'username'   => $this->string_prop( 'Username.' ),
				'email'      => $this->string_prop( 'Email address.' ),
				'password'   => $this->string_prop( 'Password.' ),
				'first_name' => $this->string_prop( 'First name.' ),
				'last_name'  => $this->string_prop( 'Last name.' ),
				'role'       => $this->string_prop( 'Role.' ),
			),
			array( 'username', 'email', 'password' )
		);
		$user_update_schema = $user_write_schema;
		$user_update_schema['properties']['id'] = $this->int_prop( 'User ID.' );
		$user_update_schema['required'] = array( 'id' );

		$this->add_ability( self::INTERNAL_PREFIX . 'get-user', 'Get User', 'Get a WordPress user by ID', $user_get_schema, function ( $params ) {
			return $this->get_user( (int) $params['id'] );
		}, true, 'list_users' );
		$this->add_ability( self::INTERNAL_PREFIX . 'add-user', 'Add User', 'Add a new WordPress user', $user_write_schema, function ( $params ) {
			return $this->insert_user( $params );
		}, false, 'create_users' );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-user', 'Update User', 'Update a WordPress user by ID', $user_update_schema, function ( $params ) {
			return $this->update_user( (int) $params['id'], $params );
		}, false, 'edit_users' );
		$this->add_ability( self::INTERNAL_PREFIX . 'delete-user', 'Delete User', 'Delete a WordPress user by ID', $user_get_schema, function ( $params ) {
			return $this->delete_user( (int) $params['id'] );
		}, false, 'delete_users' );

		$settings_schema = $this->schema(
			array(
				'blogname'        => $this->string_prop( 'Site title.' ),
				'blogdescription' => $this->string_prop( 'Tagline.' ),
				'admin_email'     => $this->string_prop( 'Administration email address.' ),
				'timezone_string' => $this->string_prop( 'Timezone.' ),
				'date_format'     => $this->string_prop( 'Date format.' ),
				'time_format'     => $this->string_prop( 'Time format.' ),
				'start_of_week'   => $this->int_prop( 'Start of week.' ),
			)
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'get-general-settings', 'Get General Settings', 'Get WordPress general site settings', $this->schema(), function () {
			return $this->get_general_settings();
		}, true, 'manage_options' );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-general-settings', 'Update General Settings', 'Update WordPress general site settings', $settings_schema, function ( $params ) {
			return $this->update_general_settings( $params );
		}, false, 'manage_options' );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-site-info', 'Get Site Info', 'Get detailed site information', $this->schema(), function () {
			return $this->get_site_info();
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-active-theme', 'Get Active Theme', 'Get the active theme information', $this->schema(), function () {
			return $this->get_active_theme();
		} );
	}
}
