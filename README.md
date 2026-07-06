# WordPress MCP

WordPress MCP registers WordPress abilities and exposes them through the MCP adapter endpoint at:

```text
/wp-json/mcp/wp-forge
```

WordPress 6.9+ provides the core Abilities API. The MCP transport is provided by the `wordpress/mcp-adapter` Composer dependency.

## Requirements

- WordPress 6.9 or later.
- PHP 8.2 or later.
- Composer dependencies installed, including `wordpress/mcp-adapter`.

## Available Tools

### Content Management

#### Content

| Tool | Description |
| --- | --- |
| `wp-forge-list-post-types` | List registered WordPress post types with runtime validation metadata |
| `wp-forge-search-content` | Search and filter content for any registered post type |
| `wp-forge-get-content` | Get a content item from any registered post type by ID or slug |
| `wp-forge-save-content` | Create or update content for any registered post type |
| `wp-forge-delete-content` | Delete content from any registered post type |

`wp-forge-list-post-types` returns each post type's slug, label, hierarchical status, public status, supported features, registered taxonomies, and REST base. The content tools use the same runtime metadata to validate conditional fields: `parent_id` is accepted only for hierarchical post types, and `taxonomies` may only include taxonomies registered to the selected `post_type`.

#### Taxonomies

| Tool | Description |
| --- | --- |
| `wp-forge-list-taxonomies` | List registered WordPress taxonomies |
| `wp-forge-list-taxonomy-terms` | List terms for a registered taxonomy |
| `wp-forge-get-taxonomy-term` | Get a term from a registered taxonomy by ID |
| `wp-forge-save-taxonomy-term` | Create or update a term in a registered taxonomy |
| `wp-forge-delete-taxonomy-term` | Delete a term from a registered taxonomy |

#### Media

| Tool | Description |
| --- | --- |
| `wp-forge-list-media` | List WordPress media items with pagination and filtering |
| `wp-forge-get-media` | Get a WordPress media item by ID |
| `wp-forge-get-media-file` | Get the actual file content of a WordPress media item |
| `wp-forge-upload-media` | Upload a new media file to WordPress |
| `wp-forge-update-media` | Update a WordPress media item |
| `wp-forge-delete-media` | Delete a WordPress media item permanently |
| `wp-forge-search-media` | Search WordPress media by title, caption, or description |

### Site Management

#### Users

| Tool | Description |
| --- | --- |
| `wp-forge-users-search` | Search and filter WordPress users with pagination |
| `wp-forge-get-user` | Get a WordPress user by ID |
| `wp-forge-add-user` | Add a new WordPress user |
| `wp-forge-update-user` | Update a WordPress user by ID |
| `wp-forge-delete-user` | Delete a WordPress user by ID |

#### Settings

| Tool | Description |
| --- | --- |
| `wp-forge-get-general-settings` | Get WordPress general site settings |
| `wp-forge-update-general-settings` | Update WordPress general site settings |

#### Site Info

| Tool | Description |
| --- | --- |
| `wp-forge-get-site-info` | Get detailed site information |

#### Plugins

| Tool | Description |
| --- | --- |
| `wp-forge-list-plugins` | List installed WordPress plugins and their activation state |
| `wp-forge-install-plugin` | Install a WordPress plugin from the WordPress.org plugin directory by slug |
| `wp-forge-activate-plugin` | Activate an installed WordPress plugin by plugin file path |
| `wp-forge-deactivate-plugin` | Deactivate an active WordPress plugin by plugin file path |
| `wp-forge-uninstall-plugin` | Deactivate and delete an installed WordPress plugin by plugin file path |

#### Options

| Tool | Description |
| --- | --- |
| `wp-forge-list-options` | List WordPress options by search or prefix |
| `wp-forge-get-option` | Get a WordPress option value by name |
| `wp-forge-update-option` | Update a WordPress option value by name |
| `wp-forge-delete-option` | Delete a WordPress option by name |

#### Comments

| Tool | Description |
| --- | --- |
| `wp-forge-list-comments` | List WordPress comments with filtering and pagination |
| `wp-forge-get-comment` | Get a WordPress comment by ID |
| `wp-forge-add-comment` | Add a comment to a WordPress post |
| `wp-forge-update-comment` | Update a WordPress comment by ID |
| `wp-forge-delete-comment` | Delete a WordPress comment by ID |
| `wp-forge-approve-comment` | Approve a WordPress comment by ID |
| `wp-forge-spam-comment` | Mark a WordPress comment as spam by ID |

#### Site Health

| Tool | Description |
| --- | --- |
| `wp-forge-get-site-health-info` | Get WordPress Site Health debug information |
| `wp-forge-list-site-health-tests` | List available WordPress Site Health tests |

#### Error Logs

| Tool | Description |
| --- | --- |
| `wp-forge-get-error-log-path` | Get the WordPress debug log path used by this site |
| `wp-forge-read-error-log` | Read the tail of the WordPress debug log |

#### WP-CLI

| Tool | Description |
| --- | --- |
| `wp-forge-run-wp-cli-command` | Run a WP-CLI command when WP-CLI execution is explicitly enabled and available |

WP-CLI execution is disabled by default. To enable it, define `WP_FORGE_MCP_ENABLE_WP_CLI` as `true` in `wp-config.php`, or return `true` from the `wp_forge_mcp_enable_wp_cli` filter. If `wp` is not on the web server user's `PATH`, define `WP_FORGE_MCP_WP_CLI_PATH` with the full path to the WP-CLI executable.

### Global Styles

| Tool | Description |
| --- | --- |
| `wp-forge-get-global-styles` | Get a global styles configuration by ID |
| `wp-forge-update-global-styles` | Update a global styles configuration |
| `wp-forge-get-active-global-styles` | Get the currently active global styles for the current theme |
| `wp-forge-get-active-global-styles-id` | Get the active global styles ID |

### Themes

| Tool | Description |
| --- | --- |
| `wp-forge-get-active-theme` | Get the active theme information |
| `wp-forge-list-themes` | List installed WordPress themes and their activation state |
| `wp-forge-install-theme` | Install a WordPress theme from the WordPress.org theme directory by slug |
| `wp-forge-activate-theme` | Activate an installed WordPress theme by stylesheet directory name |
| `wp-forge-delete-theme` | Delete an installed WordPress theme by stylesheet directory name |

### Advanced REST API CRUD

| Tool | Description |
| --- | --- |
| `wp-forge-list-api-functions` | List available WordPress REST API endpoints that support CRUD |
| `wp-forge-get-function-details` | Get detailed metadata for a specific REST API route and HTTP method |
| `wp-forge-run-api-function` | Execute a REST API request by route, method, and parameters |

## Copy-Paste MCP Configuration

Replace `https://example.com` with your site URL. Create a WordPress Application Password from your user profile, then replace the username and password placeholders below.

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": [
        "-y",
        "@automattic/mcp-wordpress-remote"
      ],
      "env": {
        "WP_API_URL": "https://example.com/wp-json/mcp/wp-forge",
        "WP_API_USERNAME": "YOUR_WORDPRESS_USERNAME",
        "WP_API_PASSWORD": "YOUR_APPLICATION_PASSWORD",
        "OAUTH_ENABLED": "false"
      }
    }
  }
}
```

The same configuration is available in WordPress under **Settings > WordPress MCP** after activating the plugin.

## Activity Log

An optional MCP activity log is available under **Settings > WordPress MCP**. When enabled, it records each tool call's tool name, user, status, duration, IP address, user agent, and session ID. Tool arguments and responses are not logged.

## Development

Install Composer dependencies to generate the PSR-4 autoloader:

```bash
composer install
```

Run the test suite:

```bash
composer test
```

## Playground Testing

Start a local WordPress Playground site with this plugin mounted and activated:

```bash
npm install
npm run playground:start
```

Test the MCP endpoint:

```bash
npm run test:playground
```

By default, the endpoint test uses `http://127.0.0.1:9400/wp-json/mcp/wp-forge` and authenticates with Playground's local auto-login flow. For remote sites, set `WP_API_URL`, `WP_API_USERNAME`, and `WP_API_PASSWORD` to use a WordPress Application Password.
