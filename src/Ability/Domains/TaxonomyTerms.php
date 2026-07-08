<?php
/**
 * Taxonomy term domain for WordPress MCP abilities.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Ability\Domains;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides taxonomy term domain behavior.
 */
trait TaxonomyTerms {
	/**
	 * List taxonomy terms.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return mixed
	 */
	private function list_terms( $taxonomy ) {
		if ( ! function_exists( 'get_terms' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return array_map( array( $this, 'format_term' ), get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) );
	}

	/**
	 * Get taxonomy term.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $id Term ID.
	 * @return array<string,mixed>
	 */
	private function get_term_item( $taxonomy, $id ) {
		if ( ! function_exists( 'get_term' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$term = get_term( $id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return Response::error( 'Term not found.', 404 );
		}

		return $this->format_term( $term );
	}

	/**
	 * Insert taxonomy term.
	 *
	 * @param string              $taxonomy Taxonomy.
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function insert_term( $taxonomy, $params ) {
		if ( ! function_exists( 'wp_insert_term' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$args = $this->term_args( $params );
		unset( $args['name'] );

		return Response::unwrap_wp_error( wp_insert_term( $params['name'], $taxonomy, $args ) );
	}

	/**
	 * Update taxonomy term.
	 *
	 * @param string              $taxonomy Taxonomy.
	 * @param int                 $id Term ID.
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function update_term( $taxonomy, $id, $params ) {
		if ( ! function_exists( 'wp_update_term' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return Response::unwrap_wp_error( wp_update_term( $id, $taxonomy, $this->term_args( $params ) ) );
	}

	/**
	 * Delete taxonomy term.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $id Term ID.
	 * @return mixed
	 */
	private function delete_term( $taxonomy, $id ) {
		if ( ! function_exists( 'wp_delete_term' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return Response::unwrap_wp_error( wp_delete_term( $id, $taxonomy ) );
	}

	/**
	 * Build term write arguments.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function term_args( $params ) {
		$args = array();
		foreach ( array( 'name', 'slug', 'description' ) as $key ) {
			if ( isset( $params[ $key ] ) ) {
				$args[ $key ] = $params[ $key ];
			}
		}
		return $args;
	}

	/**
	 * Format term.
	 *
	 * @param mixed $term Term object.
	 * @return array<string,mixed>
	 */
	private function format_term( $term ) {
		return array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'taxonomy'    => $term->taxonomy,
			'description' => $term->description,
			'count'       => (int) $term->count,
		);
	}
}
