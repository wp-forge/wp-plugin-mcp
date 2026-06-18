<?php
/**
 * Comment management MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers comment management tools.
 */
trait CommentManagementTools {
	/**
	 * Comment management abilities.
	 *
	 * @return void
	 */
	private function add_comment_abilities() {
		$id_schema = $this->schema( array( 'id' => $this->int_prop( 'Comment ID.' ) ), array( 'id' ) );

		$this->add_ability( self::INTERNAL_PREFIX . 'list-comments', 'List Comments', 'List WordPress comments with filtering and pagination', $this->schema(
			array(
				'post_id'  => $this->int_prop( 'Post ID.' ),
				'status'   => $this->string_prop( 'Comment status: approve, hold, spam, trash, or all.', 'all' ),
				'search'   => $this->string_prop( 'Search term.' ),
				'page'     => $this->int_prop( 'Page number.', 1 ),
				'per_page' => $this->int_prop( 'Comments per page.', 20 ),
			)
		), function ( $params ) {
			return $this->list_comments( $params );
		}, true, 'moderate_comments' );

		$this->add_ability( self::INTERNAL_PREFIX . 'get-comment', 'Get Comment', 'Get a WordPress comment by ID', $id_schema, function ( $params ) {
			return $this->get_comment_tool( (int) $params['id'] );
		}, true, 'moderate_comments' );

		$this->add_ability( self::INTERNAL_PREFIX . 'add-comment', 'Add Comment', 'Add a comment to a WordPress post', $this->schema(
			array(
				'post_id' => $this->int_prop( 'Post ID.' ),
				'content' => $this->string_prop( 'Comment content.' ),
				'author_name' => $this->string_prop( 'Author name.' ),
				'author_email' => $this->string_prop( 'Author email.' ),
				'status' => $this->string_prop( 'Comment status.', 'hold' ),
			),
			array( 'post_id', 'content' )
		), function ( $params ) {
			return $this->add_comment_tool( $params );
		}, false, 'edit_posts' );

		$this->add_ability( self::INTERNAL_PREFIX . 'update-comment', 'Update Comment', 'Update a WordPress comment by ID', $this->schema(
			array(
				'id'      => $this->int_prop( 'Comment ID.' ),
				'content' => $this->string_prop( 'Comment content.' ),
				'status'  => $this->string_prop( 'Comment status.' ),
			),
			array( 'id' )
		), function ( $params ) {
			return $this->update_comment_tool( $params );
		}, false, 'moderate_comments' );

		$this->add_ability( self::INTERNAL_PREFIX . 'delete-comment', 'Delete Comment', 'Delete a WordPress comment by ID', $id_schema, function ( $params ) {
			return $this->delete_comment_tool( (int) $params['id'] );
		}, false, 'moderate_comments' );

		$this->add_ability( self::INTERNAL_PREFIX . 'approve-comment', 'Approve Comment', 'Approve a WordPress comment by ID', $id_schema, function ( $params ) {
			return $this->set_comment_status_tool( (int) $params['id'], 'approve' );
		}, false, 'moderate_comments' );

		$this->add_ability( self::INTERNAL_PREFIX . 'spam-comment', 'Spam Comment', 'Mark a WordPress comment as spam by ID', $id_schema, function ( $params ) {
			return $this->set_comment_status_tool( (int) $params['id'], 'spam' );
		}, false, 'moderate_comments' );
	}

	/**
	 * List comments.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|array<int,array<string,mixed>>
	 */
	private function list_comments( $params ) {
		if ( ! function_exists( 'get_comments' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$args = array(
			'status' => isset( $params['status'] ) ? $params['status'] : 'all',
			'search' => isset( $params['search'] ) ? $params['search'] : '',
			'number' => isset( $params['per_page'] ) ? max( 1, min( 100, (int) $params['per_page'] ) ) : 20,
			'paged'  => isset( $params['page'] ) ? max( 1, (int) $params['page'] ) : 1,
		);

		if ( isset( $params['post_id'] ) ) {
			$args['post_id'] = (int) $params['post_id'];
		}

		return array_map( array( $this, 'format_comment' ), get_comments( $args ) );
	}

	/**
	 * Get a comment.
	 *
	 * @param int $id Comment ID.
	 * @return array<string,mixed>
	 */
	private function get_comment_tool( $id ) {
		if ( ! function_exists( 'get_comment' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$comment = get_comment( $id );
		return $comment ? $this->format_comment( $comment ) : Response::error( 'Comment not found.', 404 );
	}

	/**
	 * Add a comment.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function add_comment_tool( $params ) {
		if ( ! function_exists( 'wp_insert_comment' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return Response::unwrap_wp_error( wp_insert_comment( array(
			'comment_post_ID'      => (int) $params['post_id'],
			'comment_content'      => $params['content'],
			'comment_author'       => isset( $params['author_name'] ) ? $params['author_name'] : '',
			'comment_author_email' => isset( $params['author_email'] ) ? $params['author_email'] : '',
			'comment_approved'     => isset( $params['status'] ) ? $params['status'] : 'hold',
		) ) );
	}

	/**
	 * Update a comment.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function update_comment_tool( $params ) {
		if ( ! function_exists( 'wp_update_comment' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$data = array( 'comment_ID' => (int) $params['id'] );
		if ( isset( $params['content'] ) ) {
			$data['comment_content'] = $params['content'];
		}
		if ( isset( $params['status'] ) ) {
			$data['comment_approved'] = $params['status'];
		}

		return Response::unwrap_wp_error( wp_update_comment( $data, true ) );
	}

	/**
	 * Delete a comment.
	 *
	 * @param int $id Comment ID.
	 * @return array<string,mixed>
	 */
	private function delete_comment_tool( $id ) {
		if ( ! function_exists( 'wp_delete_comment' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return array(
			'id'      => $id,
			'deleted' => (bool) wp_delete_comment( $id, true ),
		);
	}

	/**
	 * Set comment status.
	 *
	 * @param int    $id Comment ID.
	 * @param string $status Status.
	 * @return mixed
	 */
	private function set_comment_status_tool( $id, $status ) {
		if ( ! function_exists( 'wp_set_comment_status' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return Response::unwrap_wp_error( wp_set_comment_status( $id, $status, true ) );
	}

	/**
	 * Format comment.
	 *
	 * @param mixed $comment Comment.
	 * @return array<string,mixed>
	 */
	private function format_comment( $comment ) {
		return array(
			'id'           => (int) $comment->comment_ID,
			'post_id'      => (int) $comment->comment_post_ID,
			'author_name'  => $comment->comment_author,
			'author_email' => $comment->comment_author_email,
			'content'      => $comment->comment_content,
			'status'       => wp_get_comment_status( $comment ),
			'date'         => $comment->comment_date,
		);
	}
}
