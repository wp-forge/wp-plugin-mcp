# WordPress MCP

WordPress MCP adds a Model Context Protocol endpoint to WordPress at:

```text
/wp-json/mcp/wp-forge
```

All MCP tools are exposed directly as top-level `wp-forge-*` tools.

## Available Tools

### Content Management

#### Posts

| Tool | Description |
| --- | --- |
| `wp-forge-posts-search` | Search and filter WordPress posts with pagination |
| `wp-forge-get-post` | Get a WordPress post by ID |
| `wp-forge-add-post` | Add a new WordPress post |
| `wp-forge-update-post` | Update a WordPress post by ID |
| `wp-forge-delete-post` | Delete a WordPress post by ID |

#### Post Categories

| Tool | Description |
| --- | --- |
| `wp-forge-list-categories` | List all WordPress post categories |
| `wp-forge-add-category` | Add a new WordPress post category |
| `wp-forge-update-category` | Update a WordPress post category |
| `wp-forge-delete-category` | Delete a WordPress post category |

#### Post Tags

| Tool | Description |
| --- | --- |
| `wp-forge-list-tags` | List all WordPress post tags |
| `wp-forge-add-tag` | Add a new WordPress post tag |
| `wp-forge-update-tag` | Update a WordPress post tag |
| `wp-forge-delete-tag` | Delete a WordPress post tag |

#### Pages

| Tool | Description |
| --- | --- |
| `wp-forge-pages-search` | Search and filter WordPress pages with pagination |
| `wp-forge-get-page` | Get a WordPress page by ID |
| `wp-forge-add-page` | Add a new WordPress page |
| `wp-forge-update-page` | Update a WordPress page by ID |
| `wp-forge-delete-page` | Delete a WordPress page by ID |

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

#### Custom Post Types

| Tool | Description |
| --- | --- |
| `wp-forge-list-post-types` | List all registered WordPress post types |
| `wp-forge-cpt-search` | Search and filter content items within a custom post type |
| `wp-forge-get-cpt` | Get a single content item from a custom post type by ID |
| `wp-forge-add-cpt` | Create a new content item within an existing custom post type |
| `wp-forge-update-cpt` | Update an existing content item in a custom post type by ID |
| `wp-forge-delete-cpt` | Permanently delete a content item from a custom post type by ID |

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

### Advanced REST API CRUD

| Tool | Description |
| --- | --- |
| `wp-forge-list-api-functions` | List available WordPress REST API endpoints that support CRUD |
| `wp-forge-get-function-details` | Get detailed metadata for a specific REST API route and HTTP method |
| `wp-forge-run-api-function` | Execute a REST API request by route, method, and parameters |

## Copy-Paste MCP Configuration

Replace `https://example.com` with your site URL. Create a WordPress Application Password from your user profile, then Base64 encode `username:application-password` and replace the placeholder below.

```json
{
  "mcpServers": {
    "wordpress": {
      "url": "https://example.com/wp-json/mcp/wp-forge",
      "headers": {
        "Authorization": "Basic BASE64_USERNAME_APPLICATION_PASSWORD"
      }
    }
  }
}
```

The same configuration is available in WordPress under **Settings > WordPress MCP** after activating the plugin.

## Development

Install Composer dependencies to generate the PSR-4 autoloader:

```bash
composer install
```

Run the test suite:

```bash
composer test
```
