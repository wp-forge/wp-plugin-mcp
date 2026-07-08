# WordPress MCP

WordPress MCP registers WordPress abilities and exposes them through the MCP adapter endpoint at:

```text
/wp-json/mcp/wp-forge
```

WordPress 6.9+ provides the core Abilities API. The MCP transport is provided by the `wordpress/mcp-adapter` Composer dependency.

See [docs/architecture.md](docs/architecture.md) for the ability registry, registration trait, domain trait, schema, validation, and permission helper layout.

## Requirements

- WordPress 6.9 or later.
- PHP 8.2 or later.
- Composer dependencies installed, including `wordpress/mcp-adapter`.

## Available Tools

### Content Management

#### Content

| Tool | Description |
| --- | --- |
| `wp-forge-post-type-list` | List registered WordPress post types with runtime validation metadata |
| `wp-forge-content-search` | Search and filter content for any registered post type |
| `wp-forge-content-get` | Get a content item from any registered post type by ID or slug |
| `wp-forge-content-save` | Create or update content for any registered post type |
| `wp-forge-content-delete` | Delete content from any registered post type |

`wp-forge-post-type-list` returns each post type's slug, label, hierarchical status, public status, supported features, registered taxonomies, and REST base. The content tools use the same runtime metadata to validate conditional fields: `parent_id` is accepted only for hierarchical post types, and `taxonomies` may only include taxonomies registered to the selected `post_type`.

#### Taxonomies

| Tool | Description |
| --- | --- |
| `wp-forge-taxonomy-list` | List registered WordPress taxonomies |
| `wp-forge-taxonomy-term-list` | List terms for a registered taxonomy |
| `wp-forge-taxonomy-term-get` | Get a term from a registered taxonomy by ID |
| `wp-forge-taxonomy-term-save` | Create or update a term in a registered taxonomy |
| `wp-forge-taxonomy-term-delete` | Delete a term from a registered taxonomy |

#### Media

| Tool | Description |
| --- | --- |
| `wp-forge-media-list` | List WordPress media items with pagination and filtering |
| `wp-forge-media-get` | Get a WordPress media item by ID |
| `wp-forge-media-file-get` | Get the actual file content of a WordPress media item |
| `wp-forge-media-upload` | Upload a new media file to WordPress |
| `wp-forge-media-update` | Update a WordPress media item |
| `wp-forge-media-delete` | Delete a WordPress media item permanently |

### Site Management

#### Users

| Tool | Description |
| --- | --- |
| `wp-forge-user-search` | Search and filter WordPress users with pagination |
| `wp-forge-user-get` | Get a WordPress user by ID |
| `wp-forge-user-save` | Create or update a WordPress user |
| `wp-forge-user-delete` | Delete a WordPress user by ID |

#### Settings

| Tool | Description |
| --- | --- |
| `wp-forge-general-settings-get` | Get WordPress general site settings |
| `wp-forge-general-settings-save` | Save WordPress general site settings |

#### Site Info

| Tool | Description |
| --- | --- |
| `wp-forge-site-info-get` | Get detailed site information |

#### Plugins

| Tool | Description |
| --- | --- |
| `wp-forge-plugin-list` | List installed WordPress plugins and their activation state |
| `wp-forge-plugin-install` | Install a WordPress plugin from the WordPress.org plugin directory by slug |
| `wp-forge-plugin-activate` | Activate an installed WordPress plugin by plugin file path |
| `wp-forge-plugin-deactivate` | Deactivate an active WordPress plugin by plugin file path |
| `wp-forge-plugin-uninstall` | Deactivate and delete an installed WordPress plugin by plugin file path |

#### Options

| Tool | Description |
| --- | --- |
| `wp-forge-option-list` | List WordPress options by search or prefix |
| `wp-forge-option-get` | Get a WordPress option value by name |
| `wp-forge-option-save` | Create or update a WordPress option value by name |
| `wp-forge-option-delete` | Delete a WordPress option by name |

#### Comments

| Tool | Description |
| --- | --- |
| `wp-forge-comment-list` | List WordPress comments with filtering and pagination |
| `wp-forge-comment-get` | Get a WordPress comment by ID |
| `wp-forge-comment-save` | Create or update a WordPress comment |
| `wp-forge-comment-delete` | Delete a WordPress comment by ID |

#### Site Health

| Tool | Description |
| --- | --- |
| `wp-forge-site-health-info-get` | Get WordPress Site Health debug information |
| `wp-forge-site-health-test-list` | List available WordPress Site Health tests |

#### Error Logs

| Tool | Description |
| --- | --- |
| `wp-forge-error-log-read` | Read the tail of the WordPress debug log |

#### WP-CLI

| Tool | Description |
| --- | --- |
| `wp-forge-wp-cli-command-run` | Run a WP-CLI command when WP-CLI execution is explicitly enabled and available |

WP-CLI execution is disabled by default. To enable it, define `WP_FORGE_MCP_ENABLE_WP_CLI` as `true` in `wp-config.php`, or return `true` from the `wp_forge_mcp_enable_wp_cli` filter. If `wp` is not on the web server user's `PATH`, define `WP_FORGE_MCP_WP_CLI_PATH` with the full path to the WP-CLI executable.

### Global Styles

| Tool | Description |
| --- | --- |
| `wp-forge-global-styles-get` | Get a global styles configuration by ID |
| `wp-forge-global-styles-update` | Update a global styles configuration |
| `wp-forge-global-styles-active-get` | Get the currently active global styles for the current theme |
| `wp-forge-global-styles-active-id-get` | Get the active global styles ID |

### Themes

| Tool | Description |
| --- | --- |
| `wp-forge-theme-active-get` | Get the active theme information |
| `wp-forge-theme-list` | List installed WordPress themes and their activation state |
| `wp-forge-theme-install` | Install a WordPress theme from the WordPress.org theme directory by slug |
| `wp-forge-theme-activate` | Activate an installed WordPress theme by stylesheet directory name |
| `wp-forge-theme-delete` | Delete an installed WordPress theme by stylesheet directory name |

### Advanced REST API CRUD

| Tool | Description |
| --- | --- |
| `wp-forge-api-function-list` | List available WordPress REST API endpoints that support CRUD |
| `wp-forge-api-function-details-get` | Get detailed metadata for a specific REST API route and HTTP method |
| `wp-forge-api-function-run` | Execute a REST API request by route, method, and parameters |

REST method schemas are derived from the registered WordPress REST routes at discovery time, so methods such as `PUT` appear when the underlying API supports them.

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
