# Ability Permission Guidelines

WordPress MCP abilities must use least-privilege permission checks. A static capability is acceptable only when the action does not depend on a specific object, taxonomy, user, route, or option name. When request parameters determine access, register a dynamic permission callback and also keep defensive checks near the runtime operation when practical.

## Registration Pattern

Use `add_ability()` with:

- A baseline capability that controls discovery and first-pass access.
- A dynamic permission callback when the ability acts on a specific object or requested resource.
- The narrowest WordPress capability that matches the action.

Examples:

```php
$this->add_ability(
	self::INTERNAL_PREFIX . 'content-get',
	'Get Content',
	'Get a content item from any registered post type by ID or slug',
	$schema,
	$callback,
	true,
	'read',
	array(),
	array( $this, 'can_read_content_request' )
);
```

The dispatcher checks the static capability first and then invokes the dynamic callback with the tool input. Dynamic callbacks should return `true` or a `Response::error( ..., 403 )` payload.

## Capability Selection

- Posts and custom post types:
  - Read a specific item with `current_user_can( 'read_post', $post_id )`.
  - Create with the post type's `create_posts` capability.
  - Update with `current_user_can( 'edit_post', $post_id )`.
  - Delete with `current_user_can( 'delete_post', $post_id )`.
  - Publishing, private, future, or cross-author actions must also check the post type's `publish_posts`, `read_private_posts`, or `edit_others_posts` caps as appropriate.
- Attachments:
  - Upload with `upload_files`.
  - Read, update, or delete a specific attachment with `read_post`, `edit_post`, or `delete_post` for that attachment ID.
- Taxonomies:
  - Use the taxonomy object's capability map: `manage_terms`, `edit_terms`, `delete_terms`, and `assign_terms`.
  - Do not assume `manage_categories` for custom taxonomies.
- Users:
  - List users with `list_users`.
  - Create users with `create_users`.
  - Edit users with `current_user_can( 'edit_user', $user_id )`.
  - Delete users with `current_user_can( 'delete_user', $user_id )`.
  - Role changes must check `promote_users` or `promote_user`.
- Settings and options:
  - General WordPress settings use `manage_options`.
  - Arbitrary option management should use a plugin-specific capability and protect critical options.
  - Redact likely secrets from option read responses.
- Plugins and themes:
  - Use core caps: `install_plugins`, `activate_plugins`, `delete_plugins`, `install_themes`, `switch_themes`, and `delete_themes`.
- Site Health:
  - Use `view_site_health_checks`.
- Error logs and command execution:
  - Use plugin-specific capabilities such as `wp_forge_mcp_read_error_log` and `wp_forge_mcp_run_wp_cli`.
  - Keep WP-CLI disabled by default and constrain commands with an allowlist.
- REST proxy tools:
  - Never proxy the MCP transport route.
  - Let `rest_do_request()` enforce the target route's `permission_callback`.
  - Add route allowlists or denylists when exposing sensitive namespaces.

## Runtime Checks

Permission callbacks are the primary guard, but high-risk methods should also check permissions near the operation they perform. This is important for shared helper methods that may be reused by multiple abilities.

Examples:

- Before returning post content, check `read_post`.
- Before mutating a post, check `edit_post`.
- Before returning a media file body, check `read_post` for the attachment.
- Before deleting anything, check the delete capability for the target object.

## Custom Capabilities

Use custom capabilities when an MCP ability is more sensitive than the closest core screen or when it should be delegated independently. Grant custom capabilities to administrators on activation, then let site owners assign them to dedicated automation roles if needed.

Current sensitive MCP capabilities:

- `wp_forge_mcp_manage_options`
- `wp_forge_mcp_read_error_log`
- `wp_forge_mcp_run_wp_cli`

## Nonces and Authentication

MCP ability calls run through the WordPress MCP/REST transport and must rely on authenticated WordPress users plus capability checks. Browser-admin actions still need normal WordPress nonce verification. Do not use nonces as a replacement for `current_user_can()`, and do not use capabilities as a replacement for nonce checks on browser-submitted admin forms.
