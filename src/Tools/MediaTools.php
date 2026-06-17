<?php
/**
 * Media MCP tools.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers media tools.
 */
trait MediaTools {
	/**
	 * Media abilities.
	 *
	 * @return void
	 */
	private function add_media_abilities() {
		$list_schema = $this->schema(
			array(
				'search'    => $this->string_prop( 'Search term.' ),
				'mime_type' => $this->string_prop( 'MIME type filter.' ),
				'page'      => $this->int_prop( 'Page number.', 1 ),
				'per_page'  => $this->int_prop( 'Items per page.', 10 ),
			)
		);
		$id_schema = $this->schema( array( 'id' => $this->int_prop( 'Media item ID.' ) ), array( 'id' ) );
		$update_schema = $this->schema(
			array(
				'id'          => $this->int_prop( 'Media item ID.' ),
				'title'       => $this->string_prop( 'Title.' ),
				'caption'     => $this->string_prop( 'Caption.' ),
				'description' => $this->string_prop( 'Description.' ),
				'alt_text'    => $this->string_prop( 'Alt text.' ),
			),
			array( 'id' )
		);
		$upload_schema = $this->schema(
			array(
				'filename'  => $this->string_prop( 'File name.' ),
				'mime_type' => $this->string_prop( 'MIME type.' ),
				'base64'    => $this->string_prop( 'Base64-encoded file contents.' ),
				'title'     => $this->string_prop( 'Title.' ),
			),
			array( 'filename', 'base64' )
		);

		$this->add_ability( self::INTERNAL_PREFIX . 'list-media', 'List Media', 'List WordPress media items with pagination and filtering', $list_schema, function ( $params ) {
			return $this->query_posts( 'attachment', array_merge( array( 'status' => 'inherit' ), $params ) );
		}, true, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-media', 'Get Media', 'Get a WordPress media item by ID', $id_schema, function ( $params ) {
			return $this->get_post_item( (int) $params['id'], 'attachment' );
		}, true, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'get-media-file', 'Get Media File', 'Get the actual file content of a WordPress media item', $id_schema, function ( $params ) {
			return $this->get_media_file( (int) $params['id'] );
		}, true, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'upload-media', 'Upload Media', 'Upload a new media file to WordPress', $upload_schema, function ( $params ) {
			return $this->upload_media( $params );
		}, false, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'update-media', 'Update Media', 'Update a WordPress media item', $update_schema, function ( $params ) {
			return $this->update_media( (int) $params['id'], $params );
		}, false, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'delete-media', 'Delete Media', 'Delete a WordPress media item permanently', $id_schema, function ( $params ) {
			return $this->delete_post_item( (int) $params['id'], 'attachment' );
		}, false, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'search-media', 'Search Media', 'Search WordPress media by title, caption, or description', $list_schema, function ( $params ) {
			return $this->query_posts( 'attachment', array_merge( array( 'status' => 'inherit' ), $params ) );
		}, true, 'upload_files' );
	}
}
