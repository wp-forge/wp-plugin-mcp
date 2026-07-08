<?php
/**
 * Ability permissions for WordPress MCP abilities.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Ability\Concerns;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides ability permission behavior.
 */
trait Permissions {
	/**
	 * Check static and dynamic permissions for an ability call.
	 *
	 * @param array<string,mixed> $ability Ability definition.
	 * @param array<string,mixed> $parameters Ability parameters.
	 * @return true|array<string,mixed>
	 */
	private function check_ability_permission( $ability, $parameters ) {
		$capability = isset( $ability['capability'] ) ? $ability['capability'] : 'edit_posts';
		$allowed    = $this->current_user_can_cap( $capability );

		if ( ! $allowed ) {
			return Response::error( 'Access denied for ability: missing capability ' . $capability, 403 );
		}

		if ( ! empty( $ability['permission_callback'] ) && is_callable( $ability['permission_callback'] ) ) {
			$result = call_user_func( $ability['permission_callback'], $parameters );
			if ( true !== $result ) {
				return is_array( $result ) ? $result : Response::error( 'Access denied for ability.', 403 );
			}
		}

		return true;
	}

	/**
	 * Check a WordPress capability with optional object arguments.
	 *
	 * @param string $capability Capability name.
	 * @param mixed  ...$args Optional map-meta-cap arguments.
	 * @return bool
	 */
	private function current_user_can_cap( $capability ) {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		$args = func_get_args();
		return (bool) call_user_func_array( 'current_user_can', $args );
	}

	/**
	 * Build a consistent permission error response.
	 *
	 * @param string $message Error message.
	 * @return array<string,mixed>
	 */
	private function permission_error( $message ) {
		return Response::error( $message, 403 );
	}

	/**
	 * Get a post type capability name.
	 *
	 * @param mixed  $type Post type object.
	 * @param string $cap Capability property.
	 * @param string $fallback Fallback capability.
	 * @return string
	 */
	private function post_type_cap( $type, $cap, $fallback = 'edit_posts' ) {
		return isset( $type->cap, $type->cap->$cap ) ? (string) $type->cap->$cap : $fallback;
	}

	/**
	 * Check whether the current user can query requested content.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_search_content_request( $params ) {
		if ( empty( $params['post_type'] ) ) {
			return true;
		}

		$post_type_check = $this->validate_post_type( $params['post_type'] );
		if ( isset( $post_type_check['status'] ) && 'error' === $post_type_check['status'] ) {
			return true;
		}

		$type   = $post_type_check['type'];
		$status = isset( $params['status'] ) ? $this->sanitize_key_value( $params['status'] ) : 'publish';
		if ( in_array( $status, array( 'publish', 'inherit' ), true ) ) {
			return true;
		}

		if ( 'private' === $status && $this->current_user_can_cap( $this->post_type_cap( $type, 'read_private_posts' ) ) ) {
			return true;
		}

		if ( $this->current_user_can_cap( $this->post_type_cap( $type, 'edit_posts' ) ) ) {
			return true;
		}

		return $this->permission_error( 'Access denied for the requested content status.' );
	}

	/**
	 * Check whether the current user can read a content request.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_read_content_request( $params ) {
		$post = $this->get_post_for_content_request( $params );
		if ( ! $post ) {
			return true;
		}

		return $this->current_user_can_cap( 'read_post', (int) $post->ID ) ? true : $this->permission_error( 'Access denied for this content item.' );
	}

	/**
	 * Check whether the current user can create or update content.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_save_content_request( $params ) {
		if ( empty( $params['post_type'] ) ) {
			return true;
		}

		$post_type_check = $this->validate_post_type( $params['post_type'] );
		if ( isset( $post_type_check['status'] ) && 'error' === $post_type_check['status'] ) {
			return true;
		}

		$type = $post_type_check['type'];
		if ( ! empty( $params['id'] ) ) {
			$post = function_exists( 'get_post' ) ? get_post( (int) $params['id'] ) : null;
			if ( $post && ! $this->current_user_can_cap( 'edit_post', (int) $post->ID ) ) {
				return $this->permission_error( 'Access denied for editing this content item.' );
			}
		} elseif ( ! $this->current_user_can_cap( $this->post_type_cap( $type, 'create_posts', $this->post_type_cap( $type, 'edit_posts' ) ) ) ) {
			return $this->permission_error( 'Access denied for creating this content type.' );
		}

		if ( ! empty( $params['status'] ) && in_array( $this->sanitize_key_value( $params['status'] ), array( 'publish', 'future', 'private' ), true ) && ! $this->current_user_can_cap( $this->post_type_cap( $type, 'publish_posts' ) ) ) {
			return $this->permission_error( 'Access denied for publishing this content type.' );
		}

		if ( isset( $params['author'] ) && function_exists( 'get_current_user_id' ) && (int) $params['author'] !== (int) get_current_user_id() && ! $this->current_user_can_cap( $this->post_type_cap( $type, 'edit_others_posts' ) ) ) {
			return $this->permission_error( 'Access denied for assigning content to another author.' );
		}

		if ( ! empty( $params['taxonomies'] ) && is_array( $params['taxonomies'] ) ) {
			foreach ( array_keys( $params['taxonomies'] ) as $taxonomy ) {
				$taxonomy_obj = $this->get_taxonomy_object( (string) $taxonomy );
				if ( $taxonomy_obj && ! $this->current_user_can_cap( $this->taxonomy_cap( $taxonomy_obj, 'assign_terms' ) ) ) {
					return $this->permission_error( 'Access denied for assigning terms in taxonomy: ' . (string) $taxonomy );
				}
			}
		}

		if ( ! empty( $params['meta'] ) && is_array( $params['meta'] ) && ! empty( $params['id'] ) && function_exists( 'current_user_can' ) ) {
			foreach ( array_keys( $params['meta'] ) as $meta_key ) {
				if ( ! $this->current_user_can_cap( 'edit_post_meta', (int) $params['id'], (string) $meta_key ) ) {
					return $this->permission_error( 'Access denied for editing post meta: ' . (string) $meta_key );
				}
			}
		}

		return true;
	}

	/**
	 * Check whether the current user can delete content.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_delete_content_request( $params ) {
		if ( empty( $params['id'] ) ) {
			return true;
		}

		return $this->current_user_can_cap( 'delete_post', (int) $params['id'] ) ? true : $this->permission_error( 'Access denied for deleting this content item.' );
	}

	/**
	 * Find a post referenced by a content request.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return mixed|null
	 */
	private function get_post_for_content_request( $params ) {
		if ( ! function_exists( 'get_post' ) || empty( $params['post_type'] ) ) {
			return null;
		}

		if ( ! empty( $params['id'] ) ) {
			return get_post( (int) $params['id'] );
		}

		if ( empty( $params['slug'] ) || ! function_exists( 'get_posts' ) ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => $this->sanitize_key_value( $params['post_type'] ),
				'name'           => (string) $params['slug'],
				'post_status'    => 'any',
				'posts_per_page' => 1,
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * Check whether the current user can read an attachment.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_read_media_request( $params ) {
		return empty( $params['id'] ) || $this->current_user_can_cap( 'read_post', (int) $params['id'] ) ? true : $this->permission_error( 'Access denied for this media item.' );
	}

	/**
	 * Check whether the current user can update an attachment.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_update_media_request( $params ) {
		return empty( $params['id'] ) || $this->current_user_can_cap( 'edit_post', (int) $params['id'] ) ? true : $this->permission_error( 'Access denied for editing this media item.' );
	}

	/**
	 * Check whether the current user can delete an attachment.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_delete_media_request( $params ) {
		return empty( $params['id'] ) || $this->current_user_can_cap( 'delete_post', (int) $params['id'] ) ? true : $this->permission_error( 'Access denied for deleting this media item.' );
	}

	/**
	 * Get a taxonomy object.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return mixed|null
	 */
	private function get_taxonomy_object( $taxonomy ) {
		$taxonomy = $this->sanitize_key_value( $taxonomy );
		return function_exists( 'get_taxonomy' ) ? get_taxonomy( $taxonomy ) : null;
	}

	/**
	 * Get a taxonomy capability name.
	 *
	 * @param mixed  $taxonomy Taxonomy object.
	 * @param string $cap Capability property.
	 * @return string
	 */
	private function taxonomy_cap( $taxonomy, $cap ) {
		$fallbacks = array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		);

		return isset( $taxonomy->cap, $taxonomy->cap->$cap ) ? (string) $taxonomy->cap->$cap : $fallbacks[ $cap ];
	}

	/**
	 * Check whether the current user can list terms for a taxonomy.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_list_terms_request( $params ) {
		if ( empty( $params['taxonomy'] ) ) {
			return true;
		}

		$taxonomy = $this->get_taxonomy_object( (string) $params['taxonomy'] );
		if ( ! $taxonomy ) {
			return true;
		}

		if ( ! empty( $taxonomy->public ) ) {
			return true;
		}

		return $this->current_user_can_cap( $this->taxonomy_cap( $taxonomy, 'manage_terms' ) ) ? true : $this->permission_error( 'Access denied for this taxonomy.' );
	}

	/**
	 * Check whether the current user can create or update a term.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_save_term_request( $params ) {
		if ( empty( $params['taxonomy'] ) ) {
			return true;
		}

		$taxonomy = $this->get_taxonomy_object( (string) $params['taxonomy'] );
		if ( ! $taxonomy ) {
			return true;
		}

		$capability = ! empty( $params['id'] ) ? $this->taxonomy_cap( $taxonomy, 'edit_terms' ) : $this->taxonomy_cap( $taxonomy, 'manage_terms' );
		return $this->current_user_can_cap( $capability ) ? true : $this->permission_error( 'Access denied for saving terms in this taxonomy.' );
	}

	/**
	 * Check whether the current user can delete a term.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_delete_term_request( $params ) {
		if ( empty( $params['taxonomy'] ) ) {
			return true;
		}

		$taxonomy = $this->get_taxonomy_object( (string) $params['taxonomy'] );
		if ( ! $taxonomy ) {
			return true;
		}

		return $this->current_user_can_cap( $this->taxonomy_cap( $taxonomy, 'delete_terms' ) ) ? true : $this->permission_error( 'Access denied for deleting terms in this taxonomy.' );
	}

	/**
	 * Check whether the current user can create or update a user.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_save_user_request( $params ) {
		if ( empty( $params['id'] ) ) {
			if ( ! $this->current_user_can_cap( 'create_users' ) ) {
				return $this->permission_error( 'Access denied for creating users.' );
			}
			if ( isset( $params['role'] ) && ! $this->current_user_can_cap( 'promote_users' ) ) {
				return $this->permission_error( 'Access denied for assigning user roles.' );
			}
			return true;
		}

		$user_id = (int) $params['id'];
		if ( ! $this->current_user_can_cap( 'edit_user', $user_id ) ) {
			return $this->permission_error( 'Access denied for editing this user.' );
		}
		if ( isset( $params['role'] ) && ! $this->current_user_can_cap( 'promote_user', $user_id ) ) {
			return $this->permission_error( 'Access denied for changing this user role.' );
		}

		return true;
	}

	/**
	 * Check whether the current user can delete a user.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_delete_user_request( $params ) {
		if ( empty( $params['id'] ) ) {
			return true;
		}

		$user_id = (int) $params['id'];
		if ( function_exists( 'get_current_user_id' ) && $user_id === (int) get_current_user_id() ) {
			return $this->permission_error( 'Users cannot delete themselves through MCP.' );
		}

		return $this->current_user_can_cap( 'delete_user', $user_id ) ? true : $this->permission_error( 'Access denied for deleting this user.' );
	}

	/**
	 * Check whether an option can be managed through MCP.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_manage_option_request( $params ) {
		$option_name = isset( $params['option_name'] ) ? $this->normalize_option_name( $params['option_name'] ) : '';
		$protected   = array(
			'active_plugins',
			'admin_email',
			'cron',
			'home',
			'siteurl',
			'template',
			'stylesheet',
			'upload_path',
			'users_can_register',
		);

		if ( function_exists( 'apply_filters' ) ) {
			$protected = apply_filters( 'wp_forge_mcp_protected_options', $protected );
		}

		if ( in_array( $option_name, is_array( $protected ) ? $protected : array(), true ) ) {
			return $this->permission_error( 'Option is protected from MCP writes: ' . $option_name );
		}

		return true;
	}

	/**
	 * Check whether a global styles post can be updated.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_update_global_styles_request( $params ) {
		if ( empty( $params['id'] ) || ! function_exists( 'get_post' ) ) {
			return true;
		}

		$post = get_post( (int) $params['id'] );
		if ( ! $post || 'wp_global_styles' !== $post->post_type ) {
			return true;
		}

		return $this->current_user_can_cap( 'edit_post', (int) $post->ID ) ? true : $this->permission_error( 'Access denied for editing this global styles post.' );
	}

	/**
	 * Check whether a proxied REST request is eligible to run.
	 *
	 * The target route's own permission_callback is enforced by rest_do_request().
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_run_api_function_request( $params ) {
		$route = isset( $params['route'] ) ? (string) $params['route'] : '';
		if ( '/mcp/wp-forge' === $route ) {
			return $this->permission_error( 'The MCP transport route cannot be proxied through MCP.' );
		}

		return true;
	}

	/**
	 * Check whether a WP-CLI command is allowed.
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @return true|array<string,mixed>
	 */
	private function can_run_wp_cli_request( $params ) {
		$args = isset( $params['args'] ) && is_array( $params['args'] ) ? array_values( array_map( 'strval', $params['args'] ) ) : array();
		if ( ! $args ) {
			return true;
		}

		$allowed = array(
			'cache flush',
			'core version',
			'option get',
			'plugin list',
			'post list',
			'theme list',
		);

		if ( function_exists( 'apply_filters' ) ) {
			$allowed = apply_filters( 'wp_forge_mcp_allowed_wp_cli_commands', $allowed, $args );
		}

		if ( true === $allowed ) {
			return true;
		}

		$command = implode( ' ', array_slice( $args, 0, 2 ) );
		return in_array( $command, is_array( $allowed ) ? $allowed : array(), true ) ? true : $this->permission_error( 'WP-CLI command is not allowed through MCP: ' . $command );
	}
}
