<?php
/**
 * Taxonomy MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers post category and tag tools.
 */
trait TaxonomyTools {
	/**
	 * Taxonomy abilities.
	 *
	 * @return void
	 */
	private function add_taxonomy_abilities() {
		$write_schema = $this->schema(
			array(
				'name'        => $this->string_prop( 'Term name.' ),
				'slug'        => $this->string_prop( 'Term slug.' ),
				'description' => $this->string_prop( 'Description.' ),
			),
			array( 'name' )
		);
		$update_schema = $write_schema;
		$update_schema['properties']['id'] = $this->int_prop( 'Term ID.' );
		$update_schema['required'] = array( 'id' );
		$delete_schema = $this->schema( array( 'id' => $this->int_prop( 'Term ID.' ) ), array( 'id' ) );

		$this->add_taxonomy_group( 'category', 'categories', 'category', 'Category', $write_schema, $update_schema, $delete_schema );
		$this->add_taxonomy_group( 'post_tag', 'tags', 'tag', 'Tag', $write_schema, $update_schema, $delete_schema );
	}

	/**
	 * Register abilities for one taxonomy.
	 *
	 * @param string              $taxonomy Taxonomy name.
	 * @param string              $slug Tool slug.
	 * @param string              $singular Singular tool slug.
	 * @param string              $label Label.
	 * @param array<string,mixed> $write_schema Write schema.
	 * @param array<string,mixed> $update_schema Update schema.
	 * @param array<string,mixed> $delete_schema Delete schema.
	 * @return void
	 */
	private function add_taxonomy_group( $taxonomy, $slug, $singular, $label, $write_schema, $update_schema, $delete_schema ) {
		$this->add_ability( self::INTERNAL_PREFIX . 'list-' . $slug, 'List ' . $label . 's', 'List all WordPress post ' . strtolower( $label ) . 's', $this->schema(), function () use ( $taxonomy ) {
			return $this->list_terms( $taxonomy );
		} );
		$this->add_ability( self::INTERNAL_PREFIX . 'add-' . $singular, 'Add ' . $label, 'Add a new WordPress post ' . strtolower( $label ), $write_schema, function ( $params ) use ( $taxonomy ) {
			return $this->insert_term( $taxonomy, $params );
		}, false );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-' . $singular, 'Update ' . $label, 'Update a WordPress post ' . strtolower( $label ), $update_schema, function ( $params ) use ( $taxonomy ) {
			return $this->update_term( $taxonomy, (int) $params['id'], $params );
		}, false );
		$this->add_ability( self::INTERNAL_PREFIX . 'delete-' . $singular, 'Delete ' . $label, 'Delete a WordPress post ' . strtolower( $label ), $delete_schema, function ( $params ) use ( $taxonomy ) {
			return $this->delete_term( $taxonomy, (int) $params['id'] );
		}, false );
	}
}
