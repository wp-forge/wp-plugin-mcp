<?php
/**
 * Ability validation helpers for WordPress MCP abilities.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Ability\Concerns;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides runtime validation behavior.
 */
trait Validation {
	/**
	 * Validate a post type and return its runtime metadata.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<string,mixed>
	 */
	private function validate_post_type( $post_type ) {
		if ( ! function_exists( 'get_post_types' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$post_type = $this->sanitize_key_value( $post_type );
		$type      = function_exists( 'get_post_type_object' ) ? get_post_type_object( $post_type ) : null;
		if ( ! $type ) {
			$types = get_post_types( array(), 'objects' );
			$type  = isset( $types[ $post_type ] ) ? $types[ $post_type ] : null;
		}

		if ( ! $type ) {
			return Response::error( 'Unknown post type: ' . $post_type, 404 );
		}

		return array(
			'post_type'    => $post_type,
			'type'         => $type,
			'hierarchical' => (bool) $type->hierarchical,
			'taxonomies'   => $this->get_post_type_taxonomies( $post_type, $type ),
		);
	}

	/**
	 * Validate parent assignment for a content type.
	 *
	 * @param array<string,mixed> $post_type_check Post type metadata.
	 * @param int                 $parent_id Parent content ID.
	 * @return array<string,mixed>
	 */
	private function validate_content_parent( $post_type_check, $parent_id ) {
		if ( ! $post_type_check['hierarchical'] ) {
			return Response::error( 'Post type ' . $post_type_check['post_type'] . ' does not support parent_id because it is not hierarchical.', 400 );
		}

		if ( $parent_id > 0 && function_exists( 'get_post' ) ) {
			$parent = get_post( $parent_id );
			if ( ! $parent || $post_type_check['post_type'] !== $parent->post_type ) {
				return Response::error( 'parent_id must reference an existing ' . $post_type_check['post_type'] . ' item.', 400 );
			}
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate a post status for content operations.
	 *
	 * WordPress registers post statuses globally. Core does not expose a reliable
	 * status-to-post-type registry, so post-type-specific rules are left to
	 * wp_insert_post/wp_update_post and any plugin filters they invoke.
	 *
	 * @param mixed  $status Status value.
	 * @param string $context Operation context: query or write.
	 * @return array<string,mixed>
	 */
	private function validate_post_status( $status, $context ) {
		$status = $this->sanitize_key_value( $status );

		if ( '' === $status ) {
			return Response::error( 'Post status cannot be empty.', 400 );
		}

		if ( 'any' === $status ) {
			if ( 'query' === $context ) {
				return array( 'post_status' => $status );
			}

			return Response::error( 'Post status any can only be used when querying content.', 400 );
		}

		if ( function_exists( 'get_post_status_object' ) && get_post_status_object( $status ) ) {
			return array( 'post_status' => $status );
		}

		$statuses = $this->get_registered_post_status_names();
		if ( $statuses && in_array( $status, $statuses, true ) ) {
			return array( 'post_status' => $status );
		}

		if ( $statuses ) {
			return Response::error( 'Unknown post status: ' . $status, 400 );
		}

		return array( 'post_status' => $status );
	}

	/**
	 * Validate a taxonomy against a post type.
	 *
	 * @param array<string,mixed> $post_type_check Post type metadata.
	 * @param string              $taxonomy Taxonomy slug.
	 * @return array<string,mixed>
	 */
	private function validate_post_type_taxonomy( $post_type_check, $taxonomy ) {
		if ( '' === $taxonomy || ! in_array( $taxonomy, $post_type_check['taxonomies'], true ) ) {
			return Response::error( 'Taxonomy ' . $taxonomy . ' is not registered for post type ' . $post_type_check['post_type'] . '.', 400 );
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate taxonomy assignments for a content type.
	 *
	 * @param array<string,mixed> $post_type_check Post type metadata.
	 * @param mixed               $taxonomies Taxonomy assignments.
	 * @return array<string,mixed>
	 */
	private function validate_content_taxonomies( $post_type_check, $taxonomies ) {
		if ( ! is_array( $taxonomies ) ) {
			return Response::error( 'taxonomies must be an object keyed by taxonomy slug.', 400 );
		}

		foreach ( $taxonomies as $taxonomy => $terms ) {
			$taxonomy_check = $this->validate_post_type_taxonomy( $post_type_check, (string) $taxonomy );
			if ( isset( $taxonomy_check['status'] ) && 'error' === $taxonomy_check['status'] ) {
				return $taxonomy_check;
			}

			if ( ! is_array( $terms ) ) {
				return Response::error( 'Taxonomy ' . $taxonomy . ' must be an array of term IDs, slugs, or names.', 400 );
			}
		}

		return array( 'valid' => true );
	}
	/**
	 * Validate a MIME type against the allowed upload MIME type registry.
	 *
	 * @param mixed $mime_type MIME type.
	 * @return array<string,mixed>
	 */
	private function validate_mime_type( $mime_type ) {
		$mime_type = function_exists( 'sanitize_mime_type' ) ? sanitize_mime_type( $mime_type ) : strtolower( trim( (string) $mime_type ) );

		if ( '' === $mime_type ) {
			return Response::error( 'MIME type cannot be empty.', 400 );
		}

		$allowed = $this->get_allowed_mime_types();
		if ( $allowed && ! in_array( $mime_type, $allowed, true ) ) {
			return Response::error( 'Unsupported MIME type: ' . $mime_type, 400 );
		}

		return array( 'mime_type' => $mime_type );
	}
	/**
	 * Validate a user role when the role registry is available.
	 *
	 * @param mixed $role Role slug.
	 * @return array<string,mixed>
	 */
	private function validate_user_role( $role ) {
		$role = $this->sanitize_key_value( $role );

		if ( '' === $role ) {
			return Response::error( 'User role cannot be empty.', 400 );
		}

		$roles = $this->get_editable_role_slugs();
		if ( $roles && ! in_array( $role, $roles, true ) ) {
			return Response::error( 'Unknown user role: ' . $role, 400 );
		}

		return array( 'role' => $role );
	}

	/**
	 * Get supported features for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @param mixed  $type Post type object.
	 * @return array<int,string>
	 */
	private function get_post_type_supports( $post_type, $type ) {
		if ( function_exists( 'get_all_post_type_supports' ) ) {
			$supports = get_all_post_type_supports( $post_type );
			return is_array( $supports ) ? array_keys( $supports ) : array();
		}

		if ( isset( $type->supports ) && is_array( $type->supports ) ) {
			if ( array() === $type->supports ) {
				return array();
			}

			$is_list = array_keys( $type->supports ) === range( 0, count( $type->supports ) - 1 );
			return array_values( $is_list ? $type->supports : array_keys( $type->supports ) );
		}

		return array();
	}

	/**
	 * Get taxonomies registered for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @param mixed  $type Post type object.
	 * @return array<int,string>
	 */
	private function get_post_type_taxonomies( $post_type, $type ) {
		if ( function_exists( 'get_object_taxonomies' ) ) {
			return array_values( get_object_taxonomies( $post_type, 'names' ) );
		}

		if ( isset( $type->taxonomies ) && is_array( $type->taxonomies ) ) {
			return array_values( $type->taxonomies );
		}

		return array();
	}
}
