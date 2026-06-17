# WordPress MCP

WordPress MCP adds a Model Context Protocol endpoint to WordPress at:

```text
/wp-json/mcp/wp-forge
```

All MCP tools are exposed directly as top-level `wp-forge-*` tools.

Included ability groups:

- Content Management: posts, categories, tags, pages, media, and custom post types
- Site Management: users, general settings, site info
- Global Styles: get, update, active styles, and active styles ID
- Themes: active theme
- Advanced REST API CRUD helpers

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
