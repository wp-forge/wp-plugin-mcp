# Agent Instructions

Use this file as the starting point for work in this repository. Prefer the codebase's existing structure, naming, and test patterns over introducing new conventions.

## Project Basics

- Plugin slug: `wp-plugin-mcp`.
- Plugin name: `WordPress MCP`.
- PHP namespace: `WP_Forge`.
- Composer vendor prefix: `wp-forge`.
- MCP endpoint: `/wp-json/mcp/wp-forge`.
- MCP tool names must use the `wp-forge` prefix.

## Progressive Disclosure

Read only the context needed for the task at hand. Start with the files directly related to the requested change, then expand outward when those files point to shared behavior, tests, workflows, or docs.

### Docs Folder

Do not load every file in `docs/` by default. Use progressive disclosure:

- Open `docs/README.md` first if it exists.
- Open a specific docs file only when the task mentions that topic or code references it.
- For MCP tool behavior, prefer docs whose names mention tools, MCP, endpoints, schemas, authentication, or integration.
- For release packaging, prefer docs whose names mention distribution, build, zip, artifacts, or workflows.
- For testing work, prefer docs whose names mention tests, Playground, PHPUnit, integration, or CI.
- If a doc links to another doc that is directly relevant, follow that link; otherwise leave unrelated docs unread.
- If the `docs/` folder does not exist, continue with the repository files and note that there are no docs to consult.

## Development Workflow

- Use Composer PSR-4 autoloading and keep PHP classes in `src/`.
- Keep tools split into focused files when adding or changing MCP functionality.
- Update unit or integration tests for behavior changes.
- Run focused validation before finishing; use broader checks when touching shared behavior.
- Do not mention copied or derived implementation sources in user-facing project docs.

## Useful Commands

- `composer test`
- `npm run test:playground`
- `npm run playground:start`
- `node --check tests/integration/mcp-endpoint.mjs`
