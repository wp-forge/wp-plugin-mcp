<?php
/**
 * Content management MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers content and custom post type tools.
 */
trait ContentManagementTools {
	/**
	 * Content and custom post type abilities.
	 *
	 * @return void
	 */
	private function add_content_abilities() {
		$search_schema = $this->schema(
			array(
				'search'   => $this->string_prop( 'Search term.' ),
				'page'     => $this->int_prop( 'Page number.', 1 ),
				'per_page' => $this->int_prop( 'Items per page.', 10 ),
				'status'   => $this->string_prop( 'Post status.', 'publish' ),
			)
		);
		$get_schema    = $this->schema( array( 'id' => $this->int_prop( 'Item ID.' ) ), array( 'id' ) );
		$write_schema  = $this->schema(
			array(
				'title'   => $this->string_prop( 'Title.' ),
				'content' => $this->string_prop( 'Content.' ),
				'excerpt' => $this->string_prop( 'Excerpt.' ),
				'status'  => $this->string_prop( 'Status.', 'draft' ),
			)
		);
		$update_schema = $write_schema;
		$update_schema['properties']['id'] = $this->int_prop( 'Item ID.' );
		$update_schema['required']         = array( 'id' );

		foreach ( array( 'post' => 'post', 'page' => 'page' ) as $post_type => $singular ) {
			$plural = 'post' === $post_type ? 'posts' : 'pages';
			$label  = ucfirst( $singular );

			$this->add_ability( self::INTERNAL_PREFIX . $plural . '-search', 'Search ' . ucfirst( $plural ), 'Search and filter WordPress ' . $plural . ' with pagination', $search_schema, function ( $params ) use ( $post_type ) {
				return $this->query_posts( $post_type, $params );
			} );
			$this->add_ability( self::INTERNAL_PREFIX . 'get-' . $singular, 'Get ' . $label, 'Get a WordPress ' . $singular . ' by ID', $get_schema, function ( $params ) use ( $post_type ) {
				return $this->get_post_item( (int) $params['id'], $post_type );
			} );
			$this->add_ability( self::INTERNAL_PREFIX . 'add-' . $singular, 'Add ' . $label, 'Add a new WordPress ' . $singular, $write_schema, function ( $params ) use ( $post_type ) {
				return $this->insert_post_item( $post_type, $params );
			}, false );
			$this->add_ability( self::INTERNAL_PREFIX . 'update-' . $singular, 'Update ' . $label, 'Update a WordPress ' . $singular . ' by ID', $update_schema, function ( $params ) use ( $post_type ) {
				return $this->update_post_item( (int) $params['id'], $post_type, $params );
			}, false );
			$this->add_ability( self::INTERNAL_PREFIX . 'delete-' . $singular, 'Delete ' . $label, 'Delete a WordPress ' . $singular . ' by ID', $get_schema, function ( $params ) use ( $post_type ) {
				return $this->delete_post_item( (int) $params['id'], $post_type );
			}, false );
		}

		$cpt_schema = $search_schema;
		$cpt_schema['properties']['post_type'] = $this->string_prop( 'Custom post type name.' );
		$cpt_schema['required'] = array( 'post_type' );

		$cpt_get_schema = $this->schema(
			array(
				'post_type' => $this->string_prop( 'Custom post type name.' ),
				'id'        => $this->int_prop( 'Item ID.' ),
			),
			array( 'post_type', 'id' )
		);
		$cpt_write_schema = $write_schema;
		$cpt_write_schema['properties']['post_type'] = $this->string_prop( 'Custom post type name.' );
		$cpt_write_schema['required'] = array( 'post_type' );
		$cpt_update_schema = $cpt_write_schema;
		$cpt_update_schema['properties']['id'] = $this->int_prop( 'Item ID.' );
		$cpt_update_schema['required'] = array( 'post_type', 'id' );

		$this->add_ability( self::INTERNAL_PREFIX . 'list-post-types', 'List Post Types', 'List all registered WordPress post types (built-in and custom)', $this->schema(), function () {
			return $this->list_post_types();
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'cpt-search', 'Search Custom Post Type', 'Search and filter content items within a custom post type with pagination', $cpt_schema, function ( $params ) {
			return $this->query_posts( $params['post_type'], $params );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-cpt', 'Get Custom Post Type Item', 'Get a single content item from a custom post type by ID', $cpt_get_schema, function ( $params ) {
			return $this->get_post_item( (int) $params['id'], $params['post_type'] );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'add-cpt', 'Add Custom Post Type Item', 'Create a new content item within an existing custom post type', $cpt_write_schema, function ( $params ) {
			return $this->insert_post_item( $params['post_type'], $params );
		}, false );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-cpt', 'Update Custom Post Type Item', 'Update an existing content item in a custom post type by ID', $cpt_update_schema, function ( $params ) {
			return $this->update_post_item( (int) $params['id'], $params['post_type'], $params );
		}, false );
		$this->add_ability( self::INTERNAL_PREFIX . 'delete-cpt', 'Delete Custom Post Type Item', 'Permanently delete a content item from a custom post type by ID', $cpt_get_schema, function ( $params ) {
			return $this->delete_post_item( (int) $params['id'], $params['post_type'] );
		}, false );
	}
}
