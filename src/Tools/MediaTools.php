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
		$list_schema = function () {
			return $this->schema(
			array(
				'search'    => $this->string_prop( 'Search term.' ),
				'mime_type' => $this->enum_string_prop( 'Allowed upload MIME type filter.', $this->get_allowed_mime_types() ),
				'page'      => $this->int_prop( 'Page number.', 1 ),
				'per_page'  => $this->int_prop( 'Items per page.', 10 ),
			)
			);
		};
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
		$upload_schema = function () {
			return $this->schema(
			array(
				'filename'  => $this->string_prop( 'File name.' ),
				'mime_type' => $this->enum_string_prop( 'Allowed upload MIME type.', $this->get_allowed_mime_types() ),
				'base64'    => $this->string_prop( 'Base64-encoded file contents.' ),
				'title'     => $this->string_prop( 'Title.' ),
			),
			array( 'filename', 'base64' )
			);
		};

		$this->add_ability( self::INTERNAL_PREFIX . 'media_list', 'List Media', 'List WordPress media items with pagination and filtering', $list_schema, function ( $params ) {
			return $this->search_content_items( 'attachment', array_merge( array( 'status' => 'inherit' ), $params ) );
		}, true, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'media_get', 'Get Media', 'Get a WordPress media item by ID', $id_schema, function ( $params ) {
			return $this->get_content_item( (int) $params['id'], 'attachment' );
		}, true, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'media_file_get', 'Get Media File', 'Get the actual file content of a WordPress media item', $id_schema, function ( $params ) {
			return $this->get_media_file( (int) $params['id'] );
		}, true, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'media_upload', 'Upload Media', 'Upload a new media file to WordPress', $upload_schema, function ( $params ) {
			return $this->upload_media( $params );
		}, false, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'media_update', 'Update Media', 'Update a WordPress media item', $update_schema, function ( $params ) {
			return $this->update_media( (int) $params['id'], $params );
		}, false, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'media_delete', 'Delete Media', 'Delete a WordPress media item permanently', $id_schema, function ( $params ) {
			return $this->delete_content_item( (int) $params['id'], 'attachment' );
		}, false, 'upload_files' );
		$this->add_ability( self::INTERNAL_PREFIX . 'media_search', 'Search Media', 'Search WordPress media by title, caption, or description', $list_schema, function ( $params ) {
			return $this->search_content_items( 'attachment', array_merge( array( 'status' => 'inherit' ), $params ) );
		}, true, 'upload_files' );
	}
}
