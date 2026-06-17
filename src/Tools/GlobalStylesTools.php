<?php
/**
 * Global styles MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers global styles tools.
 */
trait GlobalStylesTools {
	/**
	 * Global styles abilities.
	 *
	 * @return void
	 */
	private function add_style_abilities() {
		$id_schema = $this->schema( array( 'id' => $this->int_prop( 'Global styles post ID.' ) ), array( 'id' ) );
		$update_schema = $this->schema(
			array(
				'id'       => $this->int_prop( 'Global styles post ID.' ),
				'settings' => array( 'type' => 'object', 'description' => 'theme.json settings object.' ),
				'styles'   => array( 'type' => 'object', 'description' => 'theme.json styles object.' ),
			),
			array( 'id' )
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'get-global-styles', 'Get Global Styles', 'Get a global styles configuration by ID', $id_schema, function ( $params ) {
			return $this->get_global_styles( (int) $params['id'] );
		}, true, 'edit_theme_options' );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-global-styles', 'Update Global Styles', 'Update a global styles configuration', $update_schema, function ( $params ) {
			return $this->update_global_styles( (int) $params['id'], $params );
		}, false, 'edit_theme_options' );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-active-global-styles', 'Get Active Global Styles', 'Get the currently active global styles for the current theme', $this->schema(), function () {
			return $this->get_active_global_styles();
		}, true, 'edit_theme_options' );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-active-global-styles-id', 'Get Active Global Styles ID', 'Get the active global styles ID', $this->schema(), function () {
			return array( 'id' => $this->get_active_global_styles_id() );
		}, true, 'edit_theme_options' );
	}
}
