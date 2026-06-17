# WordPress MCP

WordPress MCP adds a Model Context Protocol endpoint to WordPress at:

```text
/wp-json/mcp/wp-forge
```

The public MCP tools are:

- `wp-forge-list-abilities`
- `wp-forge-get-ability-schema`
- `wp-forge-call-ability`

The ability catalog mirrors the non-WooCommerce tools from `newfold-labs/wp-module-mcp`, namespaced as `wp-forge-*`.

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

Run the test suite:

```bash
composer test
```
