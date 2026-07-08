# Ability Architecture

`WP_Forge\Abilities` is the coordinator for the WordPress MCP ability catalog. It owns the registry, public listing/schema APIs, WordPress Abilities API registration, tool-name conversion, and dispatch through `call()`.

`src/Tools/` is reserved for ability registration traits. Registration traits define tool schemas, labels, descriptions, annotations, capabilities, and callbacks.

Shared coordinator behavior lives in `src/Ability/Concerns/`:

| File | Responsibility |
| --- | --- |
| `Concerns/SchemaHelpers.php` | JSON schema builders, runtime enum lookup helpers, response schema, annotation mapping, and small shared normalizers. |
| `Concerns/Permissions.php` | Static and dynamic permission checks for ability calls. |
| `Concerns/Validation.php` | Runtime validation for post types, content constraints, MIME types, user roles, and post type metadata. |

Domain behavior lives in `src/Ability/Domains/`:

| Registration trait | Domain trait |
| --- | --- |
| `Tools/ContentManagementTools.php` | `Domains/Content.php` |
| `Tools/TaxonomyTools.php` | `Domains/TaxonomyTerms.php` |
| `Tools/MediaTools.php` | `Domains/Media.php` |
| `Tools/SiteManagementTools.php` | `Domains/Users.php` and `Domains/Site.php` |
| `Tools/GlobalStylesTools.php` | `Domains/GlobalStyles.php` |
| `Tools/RestCatalogTools.php` | `Domains/RestExecution.php` |

When adding a tool, keep registration in the existing `src/Tools/*Tools.php` trait and put runtime behavior in the matching `src/Ability/Domains/` trait. If the tool needs shared schema, validation, or permission behavior, prefer `src/Ability/Concerns/` instead of adding more private implementation methods to `Abilities`.

This layout preserves the existing public API: tool names, ability names, response envelopes, callbacks, and permission callback names remain unchanged.
