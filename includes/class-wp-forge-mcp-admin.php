<?php
/**
 * Admin settings screen.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a user-friendly setup page.
 */
class WP_Forge_MCP_Admin {
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
		$config   = array(
			'mcpServers' => array(
				'wordpress' => array(
					'url'     => $endpoint,
					'headers' => array(
						'Authorization' => 'Basic ' . __( 'BASE64_USERNAME_APPLICATION_PASSWORD', 'wp-plugin-mcp' ),
					),
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
			<textarea class="large-text code" rows="10" readonly onclick="this.select();"><?php echo esc_textarea( wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>

			<h2><?php esc_html_e( 'Authentication', 'wp-plugin-mcp' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Open your WordPress user profile.', 'wp-plugin-mcp' ); ?></li>
				<li><?php esc_html_e( 'Create an Application Password for your MCP client.', 'wp-plugin-mcp' ); ?></li>
				<li><?php esc_html_e( 'Base64 encode username:application-password and replace BASE64_USERNAME_APPLICATION_PASSWORD in the configuration.', 'wp-plugin-mcp' ); ?></li>
			</ol>

			<p><?php esc_html_e( 'The MCP tools exposed by this plugin are wp-forge-list-abilities, wp-forge-get-ability-schema, and wp-forge-call-ability. All WordPress abilities returned by the catalog also use the wp-forge prefix.', 'wp-plugin-mcp' ); ?></p>
		</div>
		<?php
	}
}
