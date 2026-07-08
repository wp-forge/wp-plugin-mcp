<?php
/**
 * Media domain for WordPress MCP abilities.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Ability\Domains;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides media domain behavior.
 */
trait Media {

	/**
	 * Get media file as base64.
	 *
	 * @param int $id Attachment ID.
	 * @return array<string,mixed>
	 */
	private function get_media_file( $id ) {
		if ( ! function_exists( 'get_attached_file' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			return Response::error( 'File not found.', 404 );
		}

		return array(
			'id'        => $id,
			'filename'  => basename( $file ),
			'mime_type' => function_exists( 'get_post_mime_type' ) ? get_post_mime_type( $id ) : '',
			'base64'    => base64_encode( file_get_contents( $file ) ),
		);
	}

	/**
	 * Upload media.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function upload_media( $params ) {
		if ( isset( $params['mime_type'] ) ) {
			$mime_type_check = $this->validate_mime_type( $params['mime_type'] );
			if ( isset( $mime_type_check['status'] ) && 'error' === $mime_type_check['status'] ) {
				return $mime_type_check;
			}
			$params['mime_type'] = $mime_type_check['mime_type'];
		}

		if ( ! function_exists( 'wp_upload_bits' ) || ! function_exists( 'wp_insert_attachment' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$bits = base64_decode( $params['base64'], true );
		if ( false === $bits ) {
			return Response::error( 'Invalid base64 file contents.', 400 );
		}

		$upload = wp_upload_bits( $params['filename'], null, $bits );
		if ( ! empty( $upload['error'] ) ) {
			return Response::error( $upload['error'], 400 );
		}

		$detected_mime_type = isset( $upload['type'] ) ? $upload['type'] : '';
		if ( function_exists( 'wp_check_filetype_and_ext' ) ) {
			$filetype = wp_check_filetype_and_ext( $upload['file'], $params['filename'] );
			if ( empty( $filetype['type'] ) || empty( $filetype['ext'] ) ) {
				if ( file_exists( $upload['file'] ) ) {
					unlink( $upload['file'] );
				}
				return Response::error( 'Uploaded file type is not allowed.', 400 );
			}
			$detected_mime_type = $filetype['type'];
			if ( isset( $params['mime_type'] ) && $params['mime_type'] !== $detected_mime_type ) {
				if ( file_exists( $upload['file'] ) ) {
					unlink( $upload['file'] );
				}
				return Response::error( 'Uploaded file type does not match MIME type.', 400 );
			}
		}

		$id = wp_insert_attachment(
			array(
				'post_title'     => isset( $params['title'] ) ? $params['title'] : $params['filename'],
				'post_mime_type' => $detected_mime_type,
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		return Response::unwrap_wp_error( $id );
	}

	/**
	 * Update media metadata.
	 *
	 * @param int                 $id Attachment ID.
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function update_media( $id, $params ) {
		$post = array( 'ID' => $id );
		foreach ( array( 'title' => 'post_title', 'caption' => 'post_excerpt', 'description' => 'post_content' ) as $param => $field ) {
			if ( isset( $params[ $param ] ) ) {
				$post[ $field ] = $params[ $param ];
			}
		}
		if ( isset( $params['alt_text'] ) && function_exists( 'update_post_meta' ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $params['alt_text'] );
		}

		return function_exists( 'wp_update_post' ) ? Response::unwrap_wp_error( wp_update_post( $post, true ) ) : Response::error( 'This ability requires a WordPress runtime.', 500 );
	}
}
