<?php
/**
 * User domain for WordPress MCP abilities.
 *
 * @package WP_Forge
 */

namespace WP_Forge\Ability\Domains;

use WP_Forge\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides user domain behavior.
 */
trait Users {

	/**
	 * Search users.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function search_users( $params ) {
		if ( ! function_exists( 'get_users' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$args = array(
			'search' => isset( $params['search'] ) ? '*' . $params['search'] . '*' : '',
			'number' => isset( $params['per_page'] ) ? (int) $params['per_page'] : 10,
			'paged'  => isset( $params['page'] ) ? (int) $params['page'] : 1,
		);
		if ( isset( $params['role'] ) ) {
			$role_check = $this->validate_user_role( $params['role'] );
			if ( isset( $role_check['status'] ) && 'error' === $role_check['status'] ) {
				return $role_check;
			}
			$params['role'] = $role_check['role'];
			$args['role'] = $params['role'];
		}

		return array_map( array( $this, 'format_user' ), get_users( $args ) );
	}

	/**
	 * Get user.
	 *
	 * @param int $id User ID.
	 * @return mixed
	 */
	private function get_user( $id ) {
		if ( ! function_exists( 'get_user_by' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$user = get_user_by( 'id', $id );
		return $user ? $this->format_user( $user ) : Response::error( 'User not found.', 404 );
	}

	/**
	 * Save a user.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function save_user( $params ) {
		if ( isset( $params['role'] ) ) {
			$role_check = $this->validate_user_role( $params['role'] );
			if ( isset( $role_check['status'] ) && 'error' === $role_check['status'] ) {
				return $role_check;
			}
			$params['role'] = $role_check['role'];
		}

		if ( isset( $params['id'] ) ) {
			return $this->update_user( (int) $params['id'], $params );
		}

		foreach ( array( 'username', 'email', 'password' ) as $required ) {
			if ( ! isset( $params[ $required ] ) || '' === (string) $params[ $required ] ) {
				return Response::error( 'user_save requires ' . $required . ' when creating a user.', 400 );
			}
		}

		return $this->insert_user( $params );
	}

	/**
	 * Insert user.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function insert_user( $params ) {
		if ( ! function_exists( 'wp_insert_user' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return Response::unwrap_wp_error( wp_insert_user( $this->user_args( $params ) ) );
	}

	/**
	 * Update user.
	 *
	 * @param int                 $id User ID.
	 * @param array<string,mixed> $params Params.
	 * @return mixed
	 */
	private function update_user( $id, $params ) {
		if ( ! function_exists( 'wp_update_user' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		$args       = $this->user_args( $params );
		$args['ID'] = $id;
		return Response::unwrap_wp_error( wp_update_user( $args ) );
	}

	/**
	 * Delete user.
	 *
	 * @param int $id User ID.
	 * @return mixed
	 */
	private function delete_user( $id ) {
		if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/user.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			return Response::error( 'This ability requires a WordPress runtime.', 500 );
		}

		return (bool) wp_delete_user( $id );
	}

	/**
	 * User write args.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private function user_args( $params ) {
		$map  = array(
			'username'   => 'user_login',
			'email'      => 'user_email',
			'password'   => 'user_pass',
			'first_name' => 'first_name',
			'last_name'  => 'last_name',
			'role'       => 'role',
		);
		$args = array();
		foreach ( $map as $param => $field ) {
			if ( isset( $params[ $param ] ) ) {
				$args[ $field ] = $params[ $param ];
			}
		}
		return $args;
	}

	/**
	 * Format user.
	 *
	 * @param mixed $user User object.
	 * @return array<string,mixed>
	 */
	private function format_user( $user ) {
		return array(
			'id'           => (int) $user->ID,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'roles'        => isset( $user->roles ) ? array_values( $user->roles ) : array(),
		);
	}
}
