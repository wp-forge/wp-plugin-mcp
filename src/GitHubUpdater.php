<?php
/**
 * GitHub release updater.
 *
 * @package WP_Forge
 */

namespace WP_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Offers plugin updates from the latest GitHub release.
 *
 * Reads the latest release from the GitHub API, caches it, and feeds it into
 * the WordPress plugin update transient and the "View details" modal. Only
 * releases that include the expected zip asset are offered as updates.
 */
class GitHubUpdater {
	const CACHE_KEY       = 'wp_forge_mcp_github_release';
	const CACHE_TTL       = 12 * HOUR_IN_SECONDS;
	const ERROR_CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Plugin basename, such as wp-plugin-mcp/wp-plugin-mcp.php.
	 *
	 * @var string
	 */
	private $basename;

	/**
	 * Plugin slug, such as wp-plugin-mcp.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * GitHub repository in owner/name form.
	 *
	 * @var string
	 */
	private $repo;

	/**
	 * Installed plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Release asset name pattern; {version} is replaced with the release version.
	 *
	 * @var string
	 */
	private $asset_pattern;

	/**
	 * Plugin details shown in the update modal.
	 *
	 * @var array<string,string>
	 */
	private $details;

	/**
	 * Constructor.
	 *
	 * @param string               $plugin_file   Main plugin file.
	 * @param string               $repo          GitHub repository in owner/name form.
	 * @param string               $version       Installed plugin version.
	 * @param string               $asset_pattern Release asset name pattern.
	 * @param array<string,string> $details       Plugin details: name, author, homepage, requires, requires_php.
	 */
	public function __construct( $plugin_file, $repo, $version, $asset_pattern, $details = array() ) {
		$this->basename      = plugin_basename( $plugin_file );
		$this->slug          = dirname( $this->basename );
		$this->repo          = $repo;
		$this->version       = $version;
		$this->asset_pattern = $asset_pattern;
		$this->details       = $details;
	}

	/**
	 * Register WordPress update hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
	}

	/**
	 * Add this plugin to the update transient when a newer release exists.
	 *
	 * @param mixed $transient Plugin update transient.
	 * @return mixed
	 */
	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$item = (object) array(
			'id'            => 'github.com/' . $this->repo,
			'slug'          => $this->slug,
			'plugin'        => $this->basename,
			'new_version'   => $release['version'],
			'url'           => $this->detail( 'homepage' ),
			'package'       => $release['package'],
			'requires'      => $this->detail( 'requires' ),
			'requires_php'  => $this->detail( 'requires_php' ),
			'compatibility' => new \stdClass(),
		);

		// Prefer the version WordPress just read from disk; right after an update
		// the running code still reports the previous version.
		$installed = isset( $transient->checked[ $this->basename ] ) ? (string) $transient->checked[ $this->basename ] : $this->version;

		if ( version_compare( $release['version'], $installed, '>' ) ) {
			$transient->response[ $this->basename ] = $item;
			unset( $transient->no_update[ $this->basename ] );
		} else {
			// Listing the plugin in no_update enables the auto-update toggle.
			$transient->no_update[ $this->basename ] = $item;
			unset( $transient->response[ $this->basename ] );
		}

		return $transient;
	}

	/**
	 * Provide plugin details for the "View details" modal.
	 *
	 * @param mixed  $result Default result.
	 * @param string $action Plugins API action.
	 * @param object $args   Plugins API arguments.
	 * @return mixed
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || $this->slug !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => $this->detail( 'name' ),
			'slug'          => $this->slug,
			'version'       => $release['version'],
			'author'        => $this->detail( 'author' ),
			'homepage'      => $this->detail( 'homepage' ),
			'requires'      => $this->detail( 'requires' ),
			'requires_php'  => $this->detail( 'requires_php' ),
			'last_updated'  => $release['published_at'],
			'download_link' => $release['package'],
			'sections'      => array(
				'changelog' => $this->format_release_notes( $release ),
			),
		);
	}

	/**
	 * Clear the cached release after this plugin is updated.
	 *
	 * @param object              $upgrader Upgrader instance.
	 * @param array<string,mixed> $options  Upgrade details.
	 * @return void
	 */
	public function clear_cache( $upgrader, $options ) {
		unset( $upgrader );

		if ( ! is_array( $options ) || ( $options['type'] ?? '' ) !== 'plugin' ) {
			return;
		}

		$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : array();
		if ( isset( $options['plugin'] ) ) {
			$plugins[] = $options['plugin'];
		}

		if ( in_array( $this->basename, $plugins, true ) ) {
			delete_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Get the latest release, using the cache when possible.
	 *
	 * @return array<string,string>|null Release with version, package, notes, url and published_at; null when unavailable.
	 */
	public function get_latest_release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return empty( $cached ) ? null : $cached;
		}

		$release = $this->fetch_latest_release();

		// Cache failures too, but briefly, so a GitHub outage does not trigger a request on every page load.
		set_transient( self::CACHE_KEY, $release ? $release : array(), $release ? self::CACHE_TTL : self::ERROR_CACHE_TTL );

		return $release;
	}

	/**
	 * Fetch and parse the latest release from the GitHub API.
	 *
	 * @return array<string,string>|null
	 */
	private function fetch_latest_release() {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . $this->repo . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . $this->slug,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}

		$version = ltrim( (string) $data['tag_name'], 'vV' );
		if ( ! preg_match( '/^\d+(\.\d+)*/', $version ) ) {
			return null;
		}

		$package = $this->find_package_url( isset( $data['assets'] ) && is_array( $data['assets'] ) ? $data['assets'] : array(), $version );
		if ( ! $package ) {
			return null;
		}

		return array(
			'version'      => $version,
			'package'      => $package,
			'notes'        => isset( $data['body'] ) ? (string) $data['body'] : '',
			'url'          => isset( $data['html_url'] ) ? (string) $data['html_url'] : '',
			'published_at' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
		);
	}

	/**
	 * Find the download URL of the plugin zip among release assets.
	 *
	 * @param array<int,mixed> $assets  Release assets.
	 * @param string           $version Release version.
	 * @return string|null
	 */
	private function find_package_url( $assets, $version ) {
		$expected = str_replace( '{version}', $version, $this->asset_pattern );

		foreach ( $assets as $asset ) {
			if ( is_array( $asset ) && isset( $asset['name'], $asset['browser_download_url'] ) && $expected === $asset['name'] ) {
				return (string) $asset['browser_download_url'];
			}
		}

		return null;
	}

	/**
	 * Format release notes as safe HTML for the details modal.
	 *
	 * @param array<string,string> $release Release data.
	 * @return string
	 */
	private function format_release_notes( $release ) {
		$notes = '' !== trim( $release['notes'] ) ? nl2br( esc_html( trim( $release['notes'] ) ) ) : esc_html__( 'No release notes provided.', 'wp-plugin-mcp' );

		if ( '' !== $release['url'] ) {
			$notes .= '<p><a href="' . esc_url( $release['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View this release on GitHub', 'wp-plugin-mcp' ) . '</a></p>';
		}

		return $notes;
	}

	/**
	 * Get a plugin detail.
	 *
	 * @param string $key Detail key.
	 * @return string
	 */
	private function detail( $key ) {
		return isset( $this->details[ $key ] ) ? (string) $this->details[ $key ] : '';
	}
}
