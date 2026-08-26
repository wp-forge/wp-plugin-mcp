import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const workflow = readFileSync(new URL('../.github/workflows/release-asset.yml', import.meta.url), 'utf8');
const distignore = readFileSync(new URL('../.distignore', import.meta.url), 'utf8');

assert.match(workflow, /release:\s*\n\s*types:\s*(?:\[published\]|\n\s*- published)/, 'Workflow must run when a release is published.');
assert.match(workflow, /permissions:\s*\n\s*contents:\s*write/, 'Workflow must be allowed to upload a release asset.');
assert.match(workflow, /grep -Eq '\^\[0-9\]\+\\\.\[0-9\]\+\\\.\[0-9\]\+\$'/, 'Workflow must accept unprefixed semver release tags.');
assert.doesNotMatch(workflow, /grep -Eq '\^v/, 'Workflow must reject v-prefixed release tags.');
assert.match(workflow, /echo "version=\$\{tag\}"/, 'Workflow must preserve the bare release tag as the plugin version.');
assert.match(workflow, /plugin_header_version" != "\$RELEASE_TAG"/, 'Workflow must compare the plugin header directly to the bare release tag.');
assert.match(workflow, /plugin_constant_version" != "\$RELEASE_TAG"/, 'Workflow must compare the plugin constant directly to the bare release tag.');
assert.match(workflow, /composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader/, 'Workflow must install production Composer dependencies.');
assert.match(workflow, /rsync -a --delete --exclude-from=\.distignore \.\/ "\$DIST\/\$PACKAGE\//, 'Workflow must stage the plugin with .distignore.');
assert.match(workflow, /echo "PACKAGE=wp-plugin-mcp"/, 'Workflow must use the plugin slug as the archive root.');
assert.match(workflow, /ARCHIVE="\$\{PACKAGE\}-v\$\{VERSION\}\.zip"/, 'Workflow must name assets with the release version.');
assert.match(workflow, /unzip -Z1 "\$ARCHIVE" \| grep -Fx 'wp-plugin-mcp\/vendor\/autoload\.php'/, 'Workflow must verify the packaged Composer autoloader.');
assert.match(workflow, /gh release upload "\$RELEASE_TAG" "\$DIST\/\$\{PACKAGE\}-v\$\{VERSION\}\.zip" --clobber/, 'Workflow must upload the ZIP to the published release.');

for (const entry of ['.git', '.github', 'docs', 'tests', 'node_modules', 'composer.json', 'composer.lock']) {
  assert.match(distignore, new RegExp(`^${entry.replace('.', '\\.')}/?$`, 'm'), `.distignore must exclude ${entry}.`);
}

assert.doesNotMatch(distignore, /^vendor\/?$/m, '.distignore must retain production Composer dependencies.');

console.log('Release workflow tests passed.');
