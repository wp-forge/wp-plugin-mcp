<?php
/**
 * MCP activity logger.
 *
 * @package WP_Forge
 */

namespace WP_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records MCP tool calls when enabled.
 */
class ActivityLogger {
	const ENABLED_OPTION = 'wp_forge_mcp_activity_log_enabled';
	const DB_VERSION_OPTION = 'wp_forge_mcp_activity_log_db_version';
	const DB_VERSION = '1';

	/**
	 * Check whether logging is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return function_exists( 'get_option' ) && (bool) get_option( self::ENABLED_OPTION, false );
	}

	/**
	 * Set logging enabled state.
	 *
	 * @param bool $enabled Enabled.
	 * @return void
	 */
	public function set_enabled( $enabled ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::ENABLED_OPTION, $enabled ? '1' : '0' );
		}
	}

	/**
	 * Ensure the activity log table exists.
	 *
	 * @return void
	 */
	public function maybe_create_table() {
		if ( ! function_exists( 'get_option' ) || self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}

		$this->create_table();
	}

	/**
	 * Create the activity log table.
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! defined( 'ABSPATH' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			username varchar(191) NOT NULL DEFAULT '',
			tool_name varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT '',
			status_code int(11) NOT NULL DEFAULT 0,
			duration_ms int(11) unsigned NOT NULL DEFAULT 0,
			client_ip varchar(100) NOT NULL DEFAULT '',
			user_agent text NULL,
			session_id varchar(191) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY tool_name (tool_name),
			KEY status (status),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Log a tool call.
	 *
	 * @param array<string,mixed> $entry Entry values.
	 * @return void
	 */
	public function log_tool_call( $entry ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return;
		}

		$this->maybe_create_table();

		$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		$user_id = $user && $user->exists() ? (int) $user->ID : 0;
		$username = $user && $user->exists() ? $user->user_login : '';

		$wpdb->insert(
			$this->get_table_name(),
			array(
				'created_at'  => function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' ),
				'user_id'     => $user_id,
				'username'    => $username,
				'tool_name'   => isset( $entry['tool_name'] ) ? sanitize_text_field( $entry['tool_name'] ) : '',
				'status'      => isset( $entry['status'] ) ? sanitize_key( $entry['status'] ) : '',
				'status_code' => isset( $entry['status_code'] ) ? (int) $entry['status_code'] : 0,
				'duration_ms' => isset( $entry['duration_ms'] ) ? max( 1, (int) $entry['duration_ms'] ) : 0,
				'client_ip'   => $this->get_client_ip(),
				'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'session_id'  => isset( $entry['session_id'] ) ? sanitize_text_field( $entry['session_id'] ) : '',
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get log entries.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @param int                 $page Page.
	 * @param int                 $per_page Rows per page.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_entries( $filters = array(), $page = 1, $per_page = 50 ) {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return array();
		}

		$this->maybe_create_table();

		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 200, (int) $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$where    = $this->build_where_clause( $filters );
		$sql      = $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} {$where['sql']} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $where['args'], array( $per_page, $offset ) ) );
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count log entries.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return int
	 */
	public function count_entries( $filters = array() ) {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return 0;
		}

		$this->maybe_create_table();

		$where = $this->build_where_clause( $filters );
		$sql   = "SELECT COUNT(*) FROM {$this->get_table_name()} {$where['sql']}";

		if ( $where['args'] ) {
			$sql = $wpdb->prepare( $sql, $where['args'] );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get distinct column values for filters.
	 *
	 * @param string $column Column name.
	 * @return array<int,string>
	 */
	public function get_distinct_values( $column ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! in_array( $column, array( 'tool_name', 'username', 'status' ), true ) ) {
			return array();
		}

		$this->maybe_create_table();

		$values = $wpdb->get_col( "SELECT DISTINCT {$column} FROM {$this->get_table_name()} WHERE {$column} <> '' ORDER BY {$column} ASC" );

		return is_array( $values ) ? array_map( 'strval', $values ) : array();
	}

	/**
	 * Clear all log entries.
	 *
	 * @return void
	 */
	public function clear() {
		global $wpdb;

		if ( isset( $wpdb ) ) {
			$wpdb->query( 'TRUNCATE TABLE ' . $this->get_table_name() );
		}
	}

	/**
	 * Build a SQL WHERE clause for activity filters.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array{sql:string,args:array<int,mixed>}
	 */
	private function build_where_clause( $filters ) {
		$where = array();
		$args  = array();

		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$args[]  = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$args[]  = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		if ( ! empty( $filters['tool_name'] ) ) {
			$where[] = 'tool_name = %s';
			$args[]  = sanitize_text_field( $filters['tool_name'] );
		}

		if ( ! empty( $filters['username'] ) ) {
			$where[] = 'username = %s';
			$args[]  = sanitize_text_field( $filters['username'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = sanitize_key( $filters['status'] );
		}

		return array(
			'sql'  => $where ? 'WHERE ' . implode( ' AND ', $where ) : '',
			'args' => $args,
		);
	}

	/**
	 * Get the activity log table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;

		return isset( $wpdb ) ? $wpdb->prefix . 'wp_forge_mcp_activity_log' : 'wp_forge_mcp_activity_log';
	}

	/**
	 * Get the client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
}
