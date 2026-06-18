<?php
/**
 * Admin settings screen.
 *
 * @package WP_Forge
 */

namespace WP_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a user-friendly setup page.
 */
class Admin {
	/**
	 * Activity logger.
	 *
	 * @var ActivityLogger
	 */
	private $activity_logger;

	/**
	 * Constructor.
	 *
	 * @param ActivityLogger $activity_logger Activity logger.
	 */
	public function __construct( ActivityLogger $activity_logger ) {
		$this->activity_logger = $activity_logger;
	}

	/**
	 * Register settings page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'WordPress MCP', 'wp-plugin-mcp' ),
			__( 'WordPress MCP', 'wp-plugin-mcp' ),
			'manage_options',
			'wp-plugin-mcp',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add settings link on plugin list.
	 *
	 * @param array<int,string> $links Links.
	 * @return array<int,string>
	 */
	public function add_settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=wp-plugin-mcp' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'wp-plugin-mcp' ) . '</a>' );
		return $links;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		$this->handle_post_actions();

		$endpoint = rest_url( 'mcp/wp-forge' );
		$application_passwords_url = admin_url( 'profile.php#application-passwords-section' );
		$current_user = wp_get_current_user();
		$current_username = $current_user && $current_user->exists() ? $current_user->user_login : 'YOUR_WORDPRESS_USERNAME';
		$allow_insecure_tls = isset( $_GET['wp_forge_mcp_local_tls'] ) && '1' === $_GET['wp_forge_mcp_local_tls']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$activity_log_enabled = $this->activity_logger->is_enabled();
		$activity_entries = $this->activity_logger->get_recent_entries( 50 );
		$config_env = array(
			'WP_API_URL'      => $endpoint,
			'WP_API_USERNAME' => $current_username,
			'WP_API_PASSWORD' => 'YOUR_APPLICATION_PASSWORD',
			'OAUTH_ENABLED'   => 'false',
		);

		if ( $allow_insecure_tls ) {
			$config_env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
		}

		$config   = array(
			'mcpServers' => array(
				'wordpress' => array(
					'command' => 'npx',
					'args'    => array(
						'-y',
						'@automattic/mcp-wordpress-remote',
					),
					'env'     => $config_env,
				),
			),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WordPress MCP', 'wp-plugin-mcp' ); ?></h1>
			<p><?php esc_html_e( 'Connect Claude Desktop, Cursor, or another MCP client to this WordPress site using the endpoint and configuration below.', 'wp-plugin-mcp' ); ?></p>

			<h2><?php esc_html_e( 'Endpoint', 'wp-plugin-mcp' ); ?></h2>
			<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $endpoint ); ?>" onclick="this.select();" />

			<h2><?php esc_html_e( 'Copy-paste MCP configuration', 'wp-plugin-mcp' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" style="margin: 0 0 12px;">
				<input type="hidden" name="page" value="wp-plugin-mcp" />
				<label>
					<input type="checkbox" name="wp_forge_mcp_local_tls" value="1" <?php checked( $allow_insecure_tls ); ?> onchange="this.form.submit();" />
					<?php esc_html_e( 'Local site with a self-signed certificate', 'wp-plugin-mcp' ); ?>
				</label>
			</form>
			<textarea class="large-text code" rows="10" readonly onclick="this.select();"><?php echo esc_textarea( wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>

			<h2><?php esc_html_e( 'Authentication', 'wp-plugin-mcp' ); ?></h2>
			<ol>
				<li>
					<?php esc_html_e( 'Create an', 'wp-plugin-mcp' ); ?>
					<a href="<?php echo esc_url( $application_passwords_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Application Password', 'wp-plugin-mcp' ); ?></a>
					<?php esc_html_e( 'for your MCP client.', 'wp-plugin-mcp' ); ?>
				</li>
				<li><?php esc_html_e( 'Replace YOUR_APPLICATION_PASSWORD in the configuration.', 'wp-plugin-mcp' ); ?></li>
			</ol>

			<p><?php esc_html_e( 'All MCP tools exposed by this plugin use the wp-forge prefix, such as wp-forge-posts-search and wp-forge-get-site-info.', 'wp-plugin-mcp' ); ?></p>

			<h2><?php esc_html_e( 'Activity Log', 'wp-plugin-mcp' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=wp-plugin-mcp' ) ); ?>" style="margin: 0 0 16px;">
				<?php wp_nonce_field( 'wp_forge_mcp_activity_log_settings' ); ?>
				<input type="hidden" name="wp_forge_mcp_action" value="save_activity_log_settings" />
				<label>
					<input type="checkbox" name="wp_forge_mcp_activity_log_enabled" value="1" <?php checked( $activity_log_enabled ); ?> />
					<?php esc_html_e( 'Enable MCP activity log', 'wp-plugin-mcp' ); ?>
				</label>
				<?php submit_button( __( 'Save Activity Log Settings', 'wp-plugin-mcp' ), 'secondary', 'submit', false ); ?>
			</form>

			<p><?php esc_html_e( 'The activity log records tool name, user, status, duration, IP address, user agent, and session ID. Tool arguments and responses are not logged.', 'wp-plugin-mcp' ); ?></p>

			<?php if ( $activity_entries ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'wp-plugin-mcp' ); ?></th>
							<th><?php esc_html_e( 'Tool', 'wp-plugin-mcp' ); ?></th>
							<th><?php esc_html_e( 'User', 'wp-plugin-mcp' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-plugin-mcp' ); ?></th>
							<th><?php esc_html_e( 'Duration', 'wp-plugin-mcp' ); ?></th>
							<th><?php esc_html_e( 'IP', 'wp-plugin-mcp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $activity_entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $this->format_log_date( $entry['created_at'] ) ); ?></td>
								<td><code><?php echo esc_html( $entry['tool_name'] ); ?></code></td>
								<td><?php echo esc_html( $entry['username'] ? $entry['username'] : __( 'Unknown', 'wp-plugin-mcp' ) ); ?></td>
								<td><?php echo esc_html( $entry['status'] . ' ' . $entry['status_code'] ); ?></td>
								<td><?php echo esc_html( sprintf( '%d ms', (int) $entry['duration_ms'] ) ); ?></td>
								<td><?php echo esc_html( $entry['client_ip'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=wp-plugin-mcp' ) ); ?>" style="margin-top: 12px;">
					<?php wp_nonce_field( 'wp_forge_mcp_activity_log_settings' ); ?>
					<input type="hidden" name="wp_forge_mcp_action" value="clear_activity_log" />
					<?php submit_button( __( 'Clear Activity Log', 'wp-plugin-mcp' ), 'delete', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'No MCP activity has been logged yet.', 'wp-plugin-mcp' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle settings POST actions.
	 *
	 * @return void
	 */
	private function handle_post_actions() {
		if ( empty( $_POST['wp_forge_mcp_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'wp_forge_mcp_activity_log_settings' );

		$action = sanitize_key( wp_unslash( $_POST['wp_forge_mcp_action'] ) );
		if ( 'save_activity_log_settings' === $action ) {
			$this->activity_logger->set_enabled( ! empty( $_POST['wp_forge_mcp_activity_log_enabled'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Activity log settings saved.', 'wp-plugin-mcp' ) . '</p></div>';
		}

		if ( 'clear_activity_log' === $action ) {
			$this->activity_logger->clear();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Activity log cleared.', 'wp-plugin-mcp' ) . '</p></div>';
		}
	}

	/**
	 * Format a UTC log date for display.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	private function format_log_date( $date ) {
		if ( ! function_exists( 'get_date_from_gmt' ) ) {
			return $date;
		}

		return get_date_from_gmt( $date, 'Y-m-d H:i:s' );
	}
}
