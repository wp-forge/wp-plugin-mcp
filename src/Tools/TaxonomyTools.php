<?php
/**
 * Taxonomy MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers taxonomy tools.
 */
trait TaxonomyTools {
	/**
	 * Taxonomy abilities.
	 *
	 * @return void
	 */
	private function add_taxonomy_abilities() {
		$taxonomy_schema = $this->schema(
			array(
				'taxonomy' => $this->string_prop( 'Taxonomy name.' ),
			),
			array( 'taxonomy' )
		);
		$term_get_schema = $this->schema(
			array(
				'taxonomy' => $this->string_prop( 'Taxonomy name.' ),
				'id'       => $this->int_prop( 'Term ID.' ),
			),
			array( 'taxonomy', 'id' )
		);
		$term_save_schema = $this->schema(
			array(
				'taxonomy'    => $this->string_prop( 'Taxonomy name.' ),
				'id'          => $this->int_prop( 'Term ID. Omit to create a new term.' ),
				'name'        => $this->string_prop( 'Term name.' ),
				'slug'        => $this->string_prop( 'Term slug.' ),
				'description' => $this->string_prop( 'Description.' ),
			),
			array( 'taxonomy' )
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'list-taxonomies', 'List Taxonomies', 'List registered WordPress taxonomies', $this->schema(
			array(
				'post_type' => $this->string_prop( 'Filter taxonomies by object type.' ),
			)
		), function ( $params ) {
			return $this->list_taxonomies_tool( $params );
		} );

		$this->add_ability( self::INTERNAL_PREFIX . 'list-taxonomy-terms', 'List Taxonomy Terms', 'List terms for a registered taxonomy', $taxonomy_schema, function ( $params ) {
			return $this->list_taxonomy_terms_tool( $params['taxonomy'] );
		} );

		$this->add_ability( self::INTERNAL_PREFIX . 'get-taxonomy-term', 'Get Taxonomy Term', 'Get a term from a registered taxonomy by ID', $term_get_schema, function ( $params ) {
			return $this->get_taxonomy_term_tool( $params['taxonomy'], (int) $params['id'] );
		} );

		$this->add_ability( self::INTERNAL_PREFIX . 'save-taxonomy-term', 'Save Taxonomy Term', 'Create or update a term in a registered taxonomy', $term_save_schema, function ( $params ) {
			return $this->save_taxonomy_term_tool( $params );
		}, false, 'manage_categories' );

		$this->add_ability( self::INTERNAL_PREFIX . 'delete-taxonomy-term', 'Delete Taxonomy Term', 'Delete a term from a registered taxonomy', $term_get_schema, function ( $params ) {
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
		$out        = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! empty( $params['post_type'] ) && ! in_array( $params['post_type'], $taxonomy->object_type, true ) ) {
				continue;
			}

			$out[] = array(
				'name'         => $taxonomy->name,
				'label'        => $taxonomy->label,
				'description'  => $taxonomy->description,
				'public'       => (bool) $taxonomy->public,
				'hierarchical' => (bool) $taxonomy->hierarchical,
				'object_type'  => array_values( $taxonomy->object_type ),
				'rest_base'    => $taxonomy->rest_base,
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
	private function list_taxonomy_terms_tool( $taxonomy ) {
		$taxonomy_check = $this->validate_taxonomy( $taxonomy );
		if ( isset( $taxonomy_check['status'] ) && 'error' === $taxonomy_check['status'] ) {
			return $taxonomy_check;
		}

		return $this->list_terms( $taxonomy_check['taxonomy'] );
	}

	/**
	 * Get a taxonomy term.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $id Term ID.
	 * @return mixed
	 */
	private function get_taxonomy_term_tool( $taxonomy, $id ) {
		$taxonomy_check = $this->validate_taxonomy( $taxonomy );
		if ( isset( $taxonomy_check['status'] ) && 'error' === $taxonomy_check['status'] ) {
			return $taxonomy_check;
		}

		return $this->get_term_item( $taxonomy_check['taxonomy'], $id );
	}

	/**
	 * Save a taxonomy term.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function save_taxonomy_term_tool( $params ) {
		$taxonomy_check = $this->validate_taxonomy( $params['taxonomy'] );
		if ( isset( $taxonomy_check['status'] ) && 'error' === $taxonomy_check['status'] ) {
			return $taxonomy_check;
		}

		$taxonomy = $taxonomy_check['taxonomy'];
		if ( ! empty( $params['id'] ) ) {
			return $this->update_term( $taxonomy, (int) $params['id'], $params );
		}

		if ( empty( $params['name'] ) ) {
			return Response::error( 'Term name is required when creating a term.', 400 );
		}

		return $this->insert_term( $taxonomy, $params );
	}

	/**
	 * Validate taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array<string,mixed>
	 */
	private function validate_taxonomy( $taxonomy ) {
		if ( ! function_exists( 'taxonomy_exists' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$taxonomy = sanitize_key( $taxonomy );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return Response::error( 'Taxonomy not found: ' . $taxonomy, 404 );
		}

		return array( 'taxonomy' => $taxonomy );
	}
}
