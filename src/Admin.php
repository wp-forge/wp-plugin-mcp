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
		$endpoint = rest_url( 'mcp/wp-forge' );
		$application_passwords_url = admin_url( 'profile.php#application-passwords-section' );
		$allow_insecure_tls = isset( $_GET['wp_forge_mcp_local_tls'] ) && '1' === $_GET['wp_forge_mcp_local_tls']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$config_env = array(
			'WP_API_URL'      => $endpoint,
			'WP_API_USERNAME' => 'YOUR_WORDPRESS_USERNAME',
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
				<li><?php esc_html_e( 'Replace YOUR_WORDPRESS_USERNAME and YOUR_APPLICATION_PASSWORD in the configuration.', 'wp-plugin-mcp' ); ?></li>
			</ol>

			<p><?php esc_html_e( 'All MCP tools exposed by this plugin use the wp-forge prefix, such as wp-forge-posts-search and wp-forge-get-site-info.', 'wp-plugin-mcp' ); ?></p>
		</div>
		<?php
	}
}
