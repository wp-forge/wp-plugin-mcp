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
		$list_post_types_schema = $this->schema(
			array(
				'public'              => $this->bool_prop( 'Filter by public post types.' ),
				'hierarchical'        => $this->bool_prop( 'Filter by hierarchical support.' ),
				'show_in_rest'        => $this->bool_prop( 'Filter by REST API exposure.' ),
				'show_ui'             => $this->bool_prop( 'Filter by admin UI visibility.' ),
				'show_in_menu'        => $this->bool_prop( 'Filter by admin menu visibility.' ),
				'show_in_nav_menus'   => $this->bool_prop( 'Filter by nav menu visibility.' ),
				'exclude_from_search' => $this->bool_prop( 'Filter by search exclusion.' ),
				'publicly_queryable'  => $this->bool_prop( 'Filter by public queryability.' ),
				'_builtin'            => $this->bool_prop( 'Filter by built-in status.' ),
			)
		);
		$content_id_schema      = function () {
			return $this->schema(
			array(
				'post_type' => $this->enum_string_prop( 'Registered post type slug.', $this->get_registered_post_type_slugs() ),
				'id'        => $this->int_prop( 'Content item ID.' ),
				'slug'      => $this->string_prop( 'Content item slug. Used only when id is omitted.' ),
				'fields'    => array(
					'type'        => 'array',
					'description' => 'Optional list of response fields to return.',
					'items'       => array( 'type' => 'string' ),
				),
			),
			array( 'post_type' )
			);
		};
		$date_query_schema      = array(
			'type'                 => 'object',
			'description'          => 'Filter by content dates.',
			'properties'           => array(
				'after'  => $this->string_prop( 'ISO 8601 date after which content was created.' ),
				'before' => $this->string_prop( 'ISO 8601 date before which content was created.' ),
			),
			'additionalProperties' => false,
		);
		$taxonomies_schema      = array(
			'type'                 => 'object',
			'description'          => 'Taxonomy assignments keyed by taxonomy slug.',
			'additionalProperties' => array(
				'type'  => 'array',
				'items' => array( 'type' => array( 'integer', 'string' ) ),
			),
		);
		$meta_schema            = array(
			'type'                 => 'object',
			'description'          => 'Post meta values keyed by meta key.',
			'additionalProperties' => array( 'type' => array( 'array', 'object', 'string', 'number', 'integer', 'boolean', 'null' ) ),
		);
		$search_content_schema  = function () use ( $date_query_schema ) {
			$taxonomy_query_schema = array(
				'type'                 => 'object',
				'description'          => 'Filter by a taxonomy registered to the post type.',
				'properties'           => array(
					'taxonomy' => $this->enum_string_prop( 'Registered taxonomy slug.', $this->get_registered_taxonomy_slugs() ),
					'terms'    => array(
						'type'        => 'array',
						'description' => 'Term IDs, slugs, or names.',
						'items'       => array( 'type' => array( 'integer', 'string' ) ),
					),
				),
				'additionalProperties' => false,
			);

			return $this->schema(
			array(
				'post_type'      => $this->enum_string_prop( 'Registered post type slug.', $this->get_registered_post_type_slugs() ),
				'query'          => $this->string_prop( 'Free-text search term.' ),
				'status'         => $this->enum_string_prop( 'Registered post status or the WordPress query any pseudo-status.', $this->get_registered_post_status_names( true ), 'publish' ),
				'author'         => $this->int_prop( 'Author user ID.' ),
				'taxonomy_query' => $taxonomy_query_schema,
				'date_query'     => $date_query_schema,
				'orderby'        => $this->string_prop( 'Sort field: date, title, modified, or menu_order.', 'date' ),
				'order'          => $this->string_prop( 'Sort direction: asc or desc.', 'desc' ),
				'page'           => $this->int_prop( 'Page number.', 1 ),
				'per_page'       => $this->int_prop( 'Items per page.', 10 ),
			),
			array( 'post_type' )
			);
		};
		$save_content_schema    = function () use ( $taxonomies_schema, $meta_schema ) {
			return $this->schema(
			array(
				'post_type'         => $this->enum_string_prop( 'Registered post type slug.', $this->get_registered_post_type_slugs() ),
				'id'                => $this->int_prop( 'Content item ID. Omit to create a new item.' ),
				'title'             => $this->string_prop( 'Title. Required when creating content.' ),
				'content'           => $this->string_prop( 'Content.' ),
				'excerpt'           => $this->string_prop( 'Excerpt.' ),
				'status'            => $this->enum_string_prop( 'Registered post status.', $this->get_registered_post_status_names(), 'draft' ),
				'author'            => $this->int_prop( 'Author user ID.' ),
				'parent_id'         => $this->int_prop( 'Parent content ID. Only valid for hierarchical post types.' ),
				'taxonomies'        => $taxonomies_schema,
				'meta'              => $meta_schema,
				'featured_media_id' => $this->int_prop( 'Featured media attachment ID.' ),
			),
			array( 'post_type' )
			);
		};
		$delete_content_schema  = function () {
			return $this->schema(
			array(
				'post_type' => $this->enum_string_prop( 'Registered post type slug.', $this->get_registered_post_type_slugs() ),
				'id'        => $this->int_prop( 'Content item ID.' ),
				'force'     => $this->bool_prop( 'Bypass trash and permanently delete.', false ),
			),
			array( 'post_type', 'id' )
			);
		};

		$this->add_ability( self::INTERNAL_PREFIX . 'post-type-list', 'List Post Types', 'List registered WordPress post types with runtime validation metadata', $list_post_types_schema, function ( $params ) {
			return $this->list_post_types( $params );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'content-search', 'Search Content', 'Search and filter content for any registered post type', $search_content_schema, function ( $params ) {
			return $this->search_content( $params );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'content-get', 'Get Content', 'Get a content item from any registered post type by ID or slug', $content_id_schema, function ( $params ) {
			return $this->get_content( $params );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'content-save', 'Save Content', 'Create or update content for any registered post type', $save_content_schema, function ( $params ) {
			return $this->save_content( $params );
		}, false );
		$this->add_ability( self::INTERNAL_PREFIX . 'content-delete', 'Delete Content', 'Delete content from any registered post type', $delete_content_schema, function ( $params ) {
			return $this->delete_content( $params );
		}, false );
	}
}
