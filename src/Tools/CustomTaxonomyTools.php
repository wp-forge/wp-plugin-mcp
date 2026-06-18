<?php
/**
 * Custom taxonomy MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers custom taxonomy tools.
 */
trait CustomTaxonomyTools {
	/**
	 * Custom taxonomy abilities.
	 *
	 * @return void
	 */
	private function add_custom_taxonomy_abilities() {
		$taxonomy_schema = $this->schema(
			array(
				'taxonomy' => $this->string_prop( 'Taxonomy name.' ),
			),
			array( 'taxonomy' )
		);
		$term_write_schema = $this->schema(
			array(
				'taxonomy'    => $this->string_prop( 'Taxonomy name.' ),
				'name'        => $this->string_prop( 'Term name.' ),
				'slug'        => $this->string_prop( 'Term slug.' ),
				'description' => $this->string_prop( 'Description.' ),
			),
			array( 'taxonomy', 'name' )
		);
		$term_update_schema = $term_write_schema;
		$term_update_schema['properties']['id'] = $this->int_prop( 'Term ID.' );
		$term_update_schema['required'] = array( 'taxonomy', 'id' );
		$term_delete_schema = $this->schema(
			array(
				'taxonomy' => $this->string_prop( 'Taxonomy name.' ),
				'id'       => $this->int_prop( 'Term ID.' ),
			),
			array( 'taxonomy', 'id' )
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'list-taxonomies', 'List Taxonomies', 'List registered WordPress taxonomies', $this->schema(
			array(
				'post_type' => $this->string_prop( 'Filter taxonomies by object type.' ),
			)
		), function ( $params ) {
			return $this->list_taxonomies_tool( $params );
		} );

		$this->add_ability( self::INTERNAL_PREFIX . 'list-taxonomy-terms', 'List Taxonomy Terms', 'List terms for a registered taxonomy', $taxonomy_schema, function ( $params ) {
			return $this->list_custom_taxonomy_terms( $params['taxonomy'] );
		} );

		$this->add_ability( self::INTERNAL_PREFIX . 'add-taxonomy-term', 'Add Taxonomy Term', 'Add a term to a registered taxonomy', $term_write_schema, function ( $params ) {
			return $this->insert_term( $params['taxonomy'], $params );
		}, false, 'manage_categories' );

		$this->add_ability( self::INTERNAL_PREFIX . 'update-taxonomy-term', 'Update Taxonomy Term', 'Update a term in a registered taxonomy', $term_update_schema, function ( $params ) {
			return $this->update_term( $params['taxonomy'], (int) $params['id'], $params );
		}, false, 'manage_categories' );

		$this->add_ability( self::INTERNAL_PREFIX . 'delete-taxonomy-term', 'Delete Taxonomy Term', 'Delete a term from a registered taxonomy', $term_delete_schema, function ( $params ) {
			return $this->delete_term( $params['taxonomy'], (int) $params['id'] );
		}, false, 'manage_categories' );
	}

	/**
	 * List taxonomies.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function list_taxonomies_tool( $params ) {
		if ( ! function_exists( 'get_taxonomies' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$taxonomies = get_taxonomies( array(), 'objects' );
		$out = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! empty( $params['post_type'] ) && ! in_array( $params['post_type'], $taxonomy->object_type, true ) ) {
				continue;
			}

			$out[] = array(
				'name'        => $taxonomy->name,
				'label'       => $taxonomy->label,
				'description' => $taxonomy->description,
				'public'      => (bool) $taxonomy->public,
				'hierarchical' => (bool) $taxonomy->hierarchical,
				'object_type' => array_values( $taxonomy->object_type ),
				'rest_base'   => $taxonomy->rest_base,
			);
		}

		return $out;
	}

	/**
	 * List taxonomy terms.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return mixed
	 */
	private function list_custom_taxonomy_terms( $taxonomy ) {
		if ( ! function_exists( 'taxonomy_exists' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$taxonomy = sanitize_key( $taxonomy );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return Response::error( 'Taxonomy not found: ' . $taxonomy, 404 );
		}

		return $this->list_terms( $taxonomy );
	}
}
