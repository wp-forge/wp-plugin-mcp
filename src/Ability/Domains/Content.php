<?php
/**
 * Content domain for WordPress MCP abilities.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Ability\Domains;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides content domain behavior.
 */
trait Content {
	/**
	 * Search content items.
	 *
	 * @param string              $post_type Post type.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function search_content_items( $post_type, $params ) {
		if ( isset( $params['status'] ) ) {
			$status_check = $this->validate_post_status( $params['status'], 'query' );
			if ( isset( $status_check['status'] ) && 'error' === $status_check['status'] ) {
				return $status_check;
			}
			$params['status'] = $status_check['post_status'];
		}

		if ( isset( $params['mime_type'] ) ) {
			$mime_type_check = $this->validate_mime_type( $params['mime_type'] );
			if ( isset( $mime_type_check['status'] ) && 'error' === $mime_type_check['status'] ) {
				return $mime_type_check;
			}
			$params['mime_type'] = $mime_type_check['mime_type'];
		}

		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => isset( $params['status'] ) ? $params['status'] : 'publish',
			's'              => isset( $params['search'] ) ? $params['search'] : '',
			'paged'          => isset( $params['page'] ) ? max( 1, (int) $params['page'] ) : 1,
			'posts_per_page' => isset( $params['per_page'] ) ? max( 1, min( 100, (int) $params['per_page'] ) ) : 10,
			'perm'           => 'readable',
		);

		if ( isset( $params['mime_type'] ) ) {
			$args['post_mime_type'] = $params['mime_type'];
		}

		return array_map( array( $this, 'format_content_item' ), get_posts( $args ) );
	}

	/**
	 * Search content for any registered post type.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function search_content( $params ) {
		$post_type_check = $this->validate_post_type( $params['post_type'] );
		if ( isset( $post_type_check['status'] ) && 'error' === $post_type_check['status'] ) {
			return $post_type_check;
		}

		if ( isset( $params['status'] ) ) {
			$status_check = $this->validate_post_status( $params['status'], 'query' );
			if ( isset( $status_check['status'] ) && 'error' === $status_check['status'] ) {
				return $status_check;
			}
			$params['status'] = $status_check['post_status'];
		}

		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$args = array(
			'post_type'      => $post_type_check['post_type'],
			'post_status'    => isset( $params['status'] ) ? $params['status'] : 'publish',
			's'              => isset( $params['query'] ) ? $params['query'] : '',
			'paged'          => isset( $params['page'] ) ? max( 1, (int) $params['page'] ) : 1,
			'posts_per_page' => isset( $params['per_page'] ) ? max( 1, min( 100, (int) $params['per_page'] ) ) : 10,
			'orderby'        => $this->normalize_content_orderby( isset( $params['orderby'] ) ? $params['orderby'] : 'date' ),
			'order'          => $this->normalize_sort_order( isset( $params['order'] ) ? $params['order'] : 'desc' ),
			'perm'           => 'readable',
		);

		if ( isset( $params['author'] ) ) {
			$args['author'] = (int) $params['author'];
		}

		if ( ! empty( $params['taxonomy_query'] ) && is_array( $params['taxonomy_query'] ) ) {
			$taxonomy = isset( $params['taxonomy_query']['taxonomy'] ) ? (string) $params['taxonomy_query']['taxonomy'] : '';
			$terms    = isset( $params['taxonomy_query']['terms'] ) && is_array( $params['taxonomy_query']['terms'] ) ? $params['taxonomy_query']['terms'] : array();

			$taxonomy_check = $this->validate_post_type_taxonomy( $post_type_check, $taxonomy );
			if ( isset( $taxonomy_check['status'] ) && 'error' === $taxonomy_check['status'] ) {
				return $taxonomy_check;
			}

			if ( '' === $taxonomy || array() === $terms ) {
				return Response::error( 'taxonomy_query requires a taxonomy and at least one term.', 400 );
			}

			$args['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => $this->taxonomy_terms_are_ids( $terms ) ? 'term_id' : 'slug',
					'terms'    => $terms,
				),
			);
		}

		if ( ! empty( $params['date_query'] ) && is_array( $params['date_query'] ) ) {
			$date_query = array();
			foreach ( array( 'after', 'before' ) as $key ) {
				if ( ! empty( $params['date_query'][ $key ] ) ) {
					$date_query[ $key ] = (string) $params['date_query'][ $key ];
				}
			}
			if ( $date_query ) {
				$args['date_query'] = array( $date_query );
			}
		}

		return array_map( array( $this, 'format_content_item' ), get_posts( $args ) );
	}

	/**
	 * Get content by ID or slug.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function get_content( $params ) {
		$post_type_check = $this->validate_post_type( $params['post_type'] );
		if ( isset( $post_type_check['status'] ) && 'error' === $post_type_check['status'] ) {
			return $post_type_check;
		}

		if ( isset( $params['id'] ) ) {
			$item = $this->get_content_item( (int) $params['id'], $post_type_check['post_type'] );
		} elseif ( ! empty( $params['slug'] ) ) {
			$item = $this->get_content_item_by_slug( $post_type_check['post_type'], (string) $params['slug'] );
		} else {
			return Response::error( 'content_get requires either id or slug.', 400 );
		}

		if ( isset( $item['status'] ) && 'error' === $item['status'] ) {
			return $item;
		}

		return $this->filter_content_fields( $item, isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array() );
	}

	/**
	 * Create or update content.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function save_content( $params ) {
		$post_type_check = $this->validate_post_type( $params['post_type'] );
		if ( isset( $post_type_check['status'] ) && 'error' === $post_type_check['status'] ) {
			return $post_type_check;
		}

		$is_update = isset( $params['id'] );
		if ( ! $is_update && ( ! isset( $params['title'] ) || '' === trim( (string) $params['title'] ) ) ) {
			return Response::error( 'content_save requires title when creating content.', 400 );
		}

		if ( isset( $params['status'] ) ) {
			$status_check = $this->validate_post_status( $params['status'], 'write' );
			if ( isset( $status_check['status'] ) && 'error' === $status_check['status'] ) {
				return $status_check;
			}
			$params['status'] = $status_check['post_status'];
		}

		if ( isset( $params['parent_id'] ) ) {
			$parent_check = $this->validate_content_parent( $post_type_check, (int) $params['parent_id'] );
			if ( isset( $parent_check['status'] ) && 'error' === $parent_check['status'] ) {
				return $parent_check;
			}
		}

		if ( isset( $params['taxonomies'] ) ) {
			$taxonomy_check = $this->validate_content_taxonomies( $post_type_check, $params['taxonomies'] );
			if ( isset( $taxonomy_check['status'] ) && 'error' === $taxonomy_check['status'] ) {
				return $taxonomy_check;
			}
		}

		if ( ! function_exists( 'wp_insert_post' ) || ! function_exists( 'wp_update_post' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$post = $this->content_post_args( $post_type_check['post_type'], $params );

		if ( $is_update ) {
			$existing = get_post( (int) $params['id'] );
			if ( ! $existing ) {
				return Response::error( 'Content item not found.', 404 );
			}
			if ( $post_type_check['post_type'] !== $existing->post_type ) {
				return Response::error( 'Content item ' . (int) $params['id'] . ' is not a ' . $post_type_check['post_type'] . '.', 400 );
			}

			$post['ID'] = (int) $params['id'];
			$id         = Response::unwrap_wp_error( wp_update_post( $post, true ) );
		} else {
			$id = Response::unwrap_wp_error( wp_insert_post( $post, true ) );
		}

		if ( is_array( $id ) && isset( $id['status'] ) && 'error' === $id['status'] ) {
			return $id;
		}

		$this->apply_content_taxonomies( (int) $id, isset( $params['taxonomies'] ) && is_array( $params['taxonomies'] ) ? $params['taxonomies'] : array() );
		$this->apply_content_meta( (int) $id, isset( $params['meta'] ) && is_array( $params['meta'] ) ? $params['meta'] : array() );
		if ( isset( $params['featured_media_id'] ) ) {
			$this->apply_featured_media( (int) $id, (int) $params['featured_media_id'] );
		}

		return $this->get_content_item( (int) $id, $post_type_check['post_type'] );
	}

	/**
	 * Delete content.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function delete_content( $params ) {
		$item = $this->get_content_item( (int) $params['id'], $params['post_type'] );
		if ( isset( $item['status'] ) && 'error' === $item['status'] ) {
			return $item;
		}

		$force  = isset( $params['force'] ) ? (bool) $params['force'] : false;
		$result = function_exists( 'wp_delete_post' ) ? wp_delete_post( (int) $params['id'], $force ) : false;

		return array(
			'id'      => (int) $params['id'],
			'deleted' => (bool) $result,
			'force'   => $force,
		);
	}

	/**
	 * Get a content item.
	 *
	 * @param int    $id Post ID.
	 * @param string $post_type Expected post type.
	 * @return array<string,mixed>
	 */
	private function get_content_item( $id, $post_type ) {
		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$post = get_post( $id );
		if ( ! $post || $post_type !== $post->post_type ) {
			return Response::error( 'Item not found.', 404 );
		}
		if ( function_exists( 'current_user_can' ) && ! $this->current_user_can_cap( 'read_post', (int) $post->ID ) ) {
			return Response::error( 'Access denied for this content item.', 403 );
		}

		return $this->format_content_item( $post );
	}

	/**
	 * Get a content item by slug.
	 *
	 * @param string $post_type Post type.
	 * @param string $slug Post slug.
	 * @return array<string,mixed>
	 */
	private function get_content_item_by_slug( $post_type, $slug ) {
		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'name'           => $slug,
				'post_status'    => 'any',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $posts ) ) {
			return Response::error( 'Item not found.', 404 );
		}
		if ( function_exists( 'current_user_can' ) && ! $this->current_user_can_cap( 'read_post', (int) $posts[0]->ID ) ) {
			return Response::error( 'Access denied for this content item.', 403 );
		}

		return $this->format_content_item( $posts[0] );
	}

	/**
	 * Insert a content item.
	 *
	 * @param string              $post_type Post type.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function insert_content_item( $post_type, $params ) {
		$missing = $this->require_wordpress();
		if ( $missing ) {
			return $missing;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_title'   => isset( $params['title'] ) ? $params['title'] : '',
				'post_content' => isset( $params['content'] ) ? $params['content'] : '',
				'post_excerpt' => isset( $params['excerpt'] ) ? $params['excerpt'] : '',
				'post_status'  => isset( $params['status'] ) ? $params['status'] : 'draft',
			),
			true
		);

		return Response::unwrap_wp_error( $id );
	}

	/**
	 * Update a content item.
	 *
	 * @param int                 $id Post ID.
	 * @param string              $post_type Post type.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|int
	 */
	private function update_content_item( $id, $post_type, $params ) {
		$item = $this->get_content_item( $id, $post_type );
		if ( isset( $item['status'] ) && 'error' === $item['status'] ) {
			return $item;
		}

		$post = array( 'ID' => $id );
		foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status' ) as $param => $field ) {
			if ( isset( $params[ $param ] ) ) {
				$post[ $field ] = $params[ $param ];
			}
		}

		return Response::unwrap_wp_error( wp_update_post( $post, true ) );
	}

	/**
	 * Delete a content item.
	 *
	 * @param int    $id Post ID.
	 * @param string $post_type Post type.
	 * @return array<string,mixed>|bool
	 */
	private function delete_content_item( $id, $post_type ) {
		$item = $this->get_content_item( $id, $post_type );
		if ( isset( $item['status'] ) && 'error' === $item['status'] ) {
			return $item;
		}

		return (bool) wp_delete_post( $id, true );
	}

	/**
	 * Format a content item.
	 *
	 * @param mixed $post Post object.
	 * @return array<string,mixed>
	 */
	private function format_content_item( $post ) {
		return array(
			'id'                => (int) $post->ID,
			'type'              => $post->post_type,
			'status'            => $post->post_status,
			'title'             => function_exists( 'get_the_title' ) ? get_the_title( $post ) : $post->post_title,
			'slug'              => $post->post_name,
			'link'              => function_exists( 'get_permalink' ) ? get_permalink( $post ) : '',
			'date'              => $post->post_date,
			'modified'          => $post->post_modified,
			'excerpt'           => $post->post_excerpt,
			'content'           => $post->post_content,
			'author'            => isset( $post->post_author ) ? (int) $post->post_author : 0,
			'parent_id'         => isset( $post->post_parent ) ? (int) $post->post_parent : 0,
			'featured_media_id' => function_exists( 'get_post_thumbnail_id' ) ? (int) get_post_thumbnail_id( $post ) : 0,
			'mime_type'         => isset( $post->post_mime_type ) ? $post->post_mime_type : '',
			'source_url'        => function_exists( 'wp_get_attachment_url' ) && 'attachment' === $post->post_type ? wp_get_attachment_url( $post->ID ) : '',
		);
	}

	/**
	 * Build wp_insert_post/wp_update_post args for content.
	 *
	 * @param string              $post_type Post type slug.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function content_post_args( $post_type, $params ) {
		$post = array( 'post_type' => $post_type );
		$map  = array(
			'title'     => 'post_title',
			'content'   => 'post_content',
			'excerpt'   => 'post_excerpt',
			'status'    => 'post_status',
			'author'    => 'post_author',
			'parent_id' => 'post_parent',
		);

		foreach ( $map as $param => $field ) {
			if ( isset( $params[ $param ] ) ) {
				$post[ $field ] = in_array( $param, array( 'author', 'parent_id' ), true ) ? (int) $params[ $param ] : $params[ $param ];
			}
		}

		if ( ! isset( $post['post_status'] ) ) {
			$post['post_status'] = 'draft';
		}

		return $post;
	}

	/**
	 * Apply taxonomy assignments to content.
	 *
	 * @param int                 $id Content item ID.
	 * @param array<string,mixed> $taxonomies Taxonomy assignments.
	 * @return void
	 */
	private function apply_content_taxonomies( $id, $taxonomies ) {
		if ( ! function_exists( 'wp_set_object_terms' ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy => $terms ) {
			wp_set_object_terms( $id, $terms, (string) $taxonomy );
		}
	}

	/**
	 * Apply post meta values to content.
	 *
	 * @param int                 $id Content item ID.
	 * @param array<string,mixed> $meta Meta values.
	 * @return void
	 */
	private function apply_content_meta( $id, $meta ) {
		if ( ! function_exists( 'update_post_meta' ) ) {
			return;
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, (string) $key, $value );
		}
	}

	/**
	 * Apply featured media to content.
	 *
	 * @param int $id Content item ID.
	 * @param int $featured_media_id Attachment ID.
	 * @return void
	 */
	private function apply_featured_media( $id, $featured_media_id ) {
		if ( function_exists( 'set_post_thumbnail' ) ) {
			set_post_thumbnail( $id, $featured_media_id );
			return;
		}

		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $id, '_thumbnail_id', $featured_media_id );
		}
	}

	/**
	 * Return only requested content fields.
	 *
	 * @param array<string,mixed> $item Content item.
	 * @param array<int,string>   $fields Field names.
	 * @return array<string,mixed>
	 */
	private function filter_content_fields( $item, $fields ) {
		if ( ! $fields ) {
			return $item;
		}

		$out = array();
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $item ) ) {
				$out[ $field ] = $item[ $field ];
			}
		}

		return $out;
	}

	/**
	 * Normalize search ordering field.
	 *
	 * @param string $orderby Orderby value.
	 * @return string
	 */
	private function normalize_content_orderby( $orderby ) {
		return in_array( $orderby, array( 'date', 'title', 'modified', 'menu_order' ), true ) ? $orderby : 'date';
	}

	/**
	 * Normalize sort direction.
	 *
	 * @param string $order Sort order.
	 * @return string
	 */
	private function normalize_sort_order( $order ) {
		return 'asc' === strtolower( (string) $order ) ? 'ASC' : 'DESC';
	}

	/**
	 * Determine whether taxonomy terms are all IDs.
	 *
	 * @param array<int,mixed> $terms Terms.
	 * @return bool
	 */
	private function taxonomy_terms_are_ids( $terms ) {
		foreach ( $terms as $term ) {
			if ( ! is_int( $term ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * List post types.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function list_post_types( $params = array() ) {
		if ( ! function_exists( 'get_post_types' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$args = array();
		foreach ( array( 'public', 'hierarchical', 'show_in_rest', 'show_ui', 'show_in_menu', 'show_in_nav_menus', 'exclude_from_search', 'publicly_queryable', '_builtin' ) as $filter ) {
			if ( array_key_exists( $filter, $params ) ) {
				$args[ $filter ] = (bool) $params[ $filter ];
			}
		}

		$types = get_post_types( $args, 'objects' );
		$out   = array();
		foreach ( $types as $type ) {
			$out[] = array(
				'slug'         => $type->name,
				'label'        => $type->label,
				'hierarchical' => (bool) $type->hierarchical,
				'public'       => (bool) $type->public,
				'supports'     => $this->get_post_type_supports( $type->name, $type ),
				'taxonomies'   => $this->get_post_type_taxonomies( $type->name, $type ),
				'rest_base'    => isset( $type->rest_base ) ? $type->rest_base : '',
			);
		}

		return array( 'post_types' => $out );
	}
}
