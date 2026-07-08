# Ability Meta Annotation Guidelines

WordPress MCP abilities should set `meta.annotations` so clients can understand whether a tool reads data, mutates state, performs destructive work, or can be safely repeated.

The plugin stores annotations in WordPress Abilities API format and maps them to MCP tool hints automatically.

## Annotation Fields

Use these WordPress ability annotation keys:

| Annotation | Meaning |
| --- | --- |
| `readonly` | The ability does not modify the site, filesystem, database, external services, or runtime state. |
| `destructive` | The ability may delete, uninstall, overwrite, deactivate, or otherwise remove or damage an existing resource. |
| `idempotent` | Calling the ability repeatedly with the same arguments has no additional effect after the first successful call. |

These map to MCP tool annotations as follows:

| WordPress ability annotation | MCP tool hint |
| --- | --- |
| `readonly` | `readOnlyHint` |
| `destructive` | `destructiveHint` |
| `idempotent` | `idempotentHint` |

## Registration Pattern

Use the `$annotations` argument on `add_ability()` when the defaults are not specific enough.

```php
$this->add_ability(
	self::INTERNAL_PREFIX . 'option-save',
	'Save Option',
	'Create or update a WordPress option value by name',
	$schema,
	$callback,
	false,
	'manage_options',
	array( 'idempotent' => true )
);
```

Current signature:

```php
add_ability(
	$name,
	$label,
	$description,
	$input_schema,
	$callback,
	$read_only = true,
	$capability = 'edit_posts',
	$annotations = array(),
	$permission_callback = null
)
```

## Defaults

`add_ability()` derives safe defaults from `$read_only`:

| `$read_only` | `readonly` | `destructive` | `idempotent` |
| --- | --- | --- | --- |
| `true` | `true` | `false` | `true` |
| `false` | `false` | `false` | `false` |

Override only the fields that differ from these defaults.

## Classification Rules

Mark an ability as `readonly: true` when it only reads or computes data.

Examples:

- List, search, and get tools.
- Site info and Site Health reads.
- Error log reads, assuming they only read the log file.
- REST route discovery and schema/detail tools.

Mark an ability as `destructive: true` when it can remove or materially damage an existing resource.

Examples:

- `content-delete`
- `taxonomy-term-delete`
- `media-delete`
- `user-delete`
- `option-delete`
- `comment-delete`
- `plugin-uninstall`
- `theme-delete`

Mark an ability as `idempotent: true` when repeating the same call should converge to the same final state.

Examples:

- Updating a specific media item with the same fields.
- Saving a specific option to the same value.
- Saving general settings to the same values.
- Updating a specific global styles record.
- Activating an already-active plugin or theme.
- Deactivating an already-inactive plugin.
- Deleting a resource that is already deleted, when the intended end state is "absent".

Leave `idempotent` as `false` for create-or-update tools when the same arguments can create another resource, depend on omitted IDs, generate timestamps, trigger side effects, or run arbitrary commands.

Examples:

- Content save when `id` is omitted.
- Term save when `id` is omitted.
- Media upload.
- Plugin or theme install.
- Generic REST execution.
- WP-CLI command execution.

## Practical Guidance

- Do not mark a write tool as destructive just because it writes. Use `destructive` for removal or high-risk state changes.
- Do not mark arbitrary command or REST runners as idempotent. Their behavior depends on the command or target route.
- Prefer precise annotations over broad categories. For example, `plugin-activate` is writable and idempotent, but not destructive.
- Keep annotations aligned with actual behavior. If a tool changes from update-only to create-or-update, revisit `idempotent`.
- Treat annotations as client guidance, not authorization. Permissions must still be enforced with capabilities and dynamic permission callbacks.

