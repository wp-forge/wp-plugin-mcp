const endpoint = process.env.WP_API_URL || 'http://127.0.0.1:9400/wp-json/mcp/wp-forge';
const username = process.env.WP_API_USERNAME;
const password = process.env.WP_API_PASSWORD;
const endpointUrl = new URL(endpoint);

function setCookiesFromResponse(response, cookies) {
  const setCookie = response.headers.getSetCookie ? response.headers.getSetCookie() : [];

  for (const header of setCookie) {
    const pair = header.split(';')[0];
    const index = pair.indexOf('=');

    if (index > 0) {
      cookies.set(pair.slice(0, index), pair.slice(index + 1));
    }
  }
}

function cookieHeader(cookies) {
  return [...cookies.entries()].map(([name, value]) => `${name}=${value}`).join('; ');
}

async function getPlaygroundAuthHeaders() {
  const cookies = new Map();
  const loginUrl = new URL('/wp-login.php', endpointUrl.origin);
  const playgroundUsername = process.env.WP_PLAYGROUND_USERNAME || 'admin';
  const playgroundPassword = process.env.WP_PLAYGROUND_PASSWORD || 'password';

  let response = await fetch(loginUrl, { redirect: 'manual' });
  setCookiesFromResponse(response, cookies);

  response = await fetch(loginUrl, {
    method: 'POST',
    redirect: 'manual',
    headers: {
      Cookie: cookieHeader(cookies),
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
      log: playgroundUsername,
      pwd: playgroundPassword,
      'wp-submit': 'Log In',
      redirect_to: new URL('/wp-admin/post-new.php', endpointUrl.origin).toString(),
      testcookie: '1',
    }),
  });
  setCookiesFromResponse(response, cookies);

  const postEditorUrl = new URL('/wp-admin/post-new.php', endpointUrl.origin);
  response = await fetch(postEditorUrl, {
    redirect: 'manual',
    headers: { Cookie: cookieHeader(cookies) },
  });
  setCookiesFromResponse(response, cookies);

  if (response.status >= 300 && response.status < 400 && response.headers.get('location')) {
    response = await fetch(new URL(response.headers.get('location'), endpointUrl.origin), {
      redirect: 'manual',
      headers: { Cookie: cookieHeader(cookies) },
    });
    setCookiesFromResponse(response, cookies);
  }

  const html = await response.text();
  const match = html.match(/wpApiSettings\s*=\s*\{[^}]*"nonce":"([^"]+)"/);

  if (!match) {
    throw new Error('Could not find a WordPress REST nonce on the Playground admin page.');
  }

  return {
    Cookie: cookieHeader(cookies),
    'X-WP-Nonce': match[1],
  };
}

const authHeaders = username && password
  ? { Authorization: `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}` }
  : await getPlaygroundAuthHeaders();

async function post(payload, sessionId = '') {
  const response = await fetch(endpoint, {
    method: 'POST',
    headers: {
      ...authHeaders,
      'Content-Type': 'application/json',
      ...(sessionId ? { 'Mcp-Session-Id': sessionId } : {}),
    },
    body: JSON.stringify(payload),
  });

  const text = await response.text();
  let body;

  try {
    body = text ? JSON.parse(text) : null;
  } catch (error) {
    throw new Error(`Expected JSON from ${endpoint}, got HTTP ${response.status}: ${text}`);
  }

  return {
    status: response.status,
    sessionId: response.headers.get('mcp-session-id') || '',
    body,
  };
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

const initialized = await post({
  jsonrpc: '2.0',
  id: 1,
  method: 'initialize',
  params: {
    protocolVersion: '2025-06-18',
    capabilities: {},
    clientInfo: {
      name: 'wp-plugin-mcp-integration-test',
      version: '1.0.0',
    },
  },
});

assert(initialized.status === 200, `initialize returned HTTP ${initialized.status}`);
assert(initialized.sessionId, 'initialize did not return Mcp-Session-Id header');
assert(initialized.body?.result?.serverInfo?.name === 'WordPress MCP', 'initialize returned unexpected serverInfo');

const listed = await post({
  jsonrpc: '2.0',
  id: 2,
  method: 'tools/list',
  params: {},
}, initialized.sessionId);

assert(listed.status === 200, `tools/list returned HTTP ${listed.status}`);

const tools = listed.body?.result?.tools;
assert(Array.isArray(tools), 'tools/list did not return result.tools array');
assert(tools.length > 0, 'tools/list returned no tools');

const expectedTools = [
  'wp-forge-posts-search',
  'wp-forge-get-post',
  'wp-forge-add-post',
  'wp-forge-update-post',
  'wp-forge-delete-post',
  'wp-forge-list-taxonomies',
  'wp-forge-list-taxonomy-terms',
  'wp-forge-get-taxonomy-term',
  'wp-forge-save-taxonomy-term',
  'wp-forge-delete-taxonomy-term',
  'wp-forge-pages-search',
  'wp-forge-get-page',
  'wp-forge-add-page',
  'wp-forge-update-page',
  'wp-forge-delete-page',
  'wp-forge-list-media',
  'wp-forge-get-media',
  'wp-forge-get-media-file',
  'wp-forge-upload-media',
  'wp-forge-update-media',
  'wp-forge-delete-media',
  'wp-forge-search-media',
  'wp-forge-list-post-types',
  'wp-forge-cpt-search',
  'wp-forge-get-cpt',
  'wp-forge-add-cpt',
  'wp-forge-update-cpt',
  'wp-forge-delete-cpt',
  'wp-forge-users-search',
  'wp-forge-get-user',
  'wp-forge-add-user',
  'wp-forge-update-user',
  'wp-forge-delete-user',
  'wp-forge-get-general-settings',
  'wp-forge-update-general-settings',
  'wp-forge-get-site-info',
  'wp-forge-list-plugins',
  'wp-forge-install-plugin',
  'wp-forge-activate-plugin',
  'wp-forge-deactivate-plugin',
  'wp-forge-uninstall-plugin',
  'wp-forge-list-themes',
  'wp-forge-install-theme',
  'wp-forge-activate-theme',
  'wp-forge-delete-theme',
  'wp-forge-list-options',
  'wp-forge-get-option',
  'wp-forge-update-option',
  'wp-forge-delete-option',
  'wp-forge-list-comments',
  'wp-forge-get-comment',
  'wp-forge-add-comment',
  'wp-forge-update-comment',
  'wp-forge-delete-comment',
  'wp-forge-approve-comment',
  'wp-forge-spam-comment',
  'wp-forge-get-site-health-info',
  'wp-forge-list-site-health-tests',
  'wp-forge-get-error-log-path',
  'wp-forge-read-error-log',
  'wp-forge-run-wp-cli-command',
  'wp-forge-get-global-styles',
  'wp-forge-update-global-styles',
  'wp-forge-get-active-global-styles',
  'wp-forge-get-active-global-styles-id',
  'wp-forge-get-active-theme',
  'wp-forge-list-api-functions',
  'wp-forge-get-function-details',
  'wp-forge-run-api-function',
];

const toolNames = tools.map((tool) => tool.name);
assert(tools.length === expectedTools.length, `tools/list returned ${tools.length} tools, expected ${expectedTools.length}`);
for (const expectedTool of expectedTools) {
  assert(toolNames.includes(expectedTool), `${expectedTool} was not listed`);
}

assert(!tools.some((tool) => tool.name === 'wp-forge-call-ability'), 'gateway tool wp-forge-call-ability should not be listed');
assert(tools.every((tool) => !Array.isArray(tool.inputSchema?.properties)), 'tool inputSchema.properties must be JSON objects, not arrays');

let nextId = 3;
const calledTools = new Set();

async function callTool(name, args = {}) {
  calledTools.add(name);

  return post({
    jsonrpc: '2.0',
    id: nextId++,
    method: 'tools/call',
    params: {
      name,
      arguments: args,
    },
  }, initialized.sessionId);
}

function structured(response, name) {
  assert(response.status === 200, `${name} returned HTTP ${response.status}`);
  assert(response.body?.result?.structuredContent, `${name} did not return structured content`);
  return response.body.result.structuredContent;
}

async function expectSuccess(name, args = {}) {
  const result = structured(await callTool(name, args), name);
  assert(result.status === 'success', `${name} did not return success: ${JSON.stringify(result)}`);
  return result.message;
}

async function expectError(name, args = {}, statusCode = null) {
  const result = structured(await callTool(name, args), name);
  assert(result.status === 'error', `${name} did not return error`);
  if (statusCode !== null) {
    assert(result.statusCode === statusCode, `${name} returned statusCode ${result.statusCode}, expected ${statusCode}`);
  }
  return result.message;
}

const suffix = `${Date.now()}-${Math.floor(Math.random() * 100000)}`;

const postId = await expectSuccess('wp-forge-add-post', {
  title: `MCP integration post ${suffix}`,
  content: 'Created by the WordPress MCP integration test.',
  status: 'draft',
});
await expectSuccess('wp-forge-posts-search', { status: 'draft', search: 'MCP integration post', per_page: 5 });
const postItem = await expectSuccess('wp-forge-get-post', { id: postId });
assert(postItem.id === postId, 'wp-forge-get-post returned the wrong post');
await expectSuccess('wp-forge-update-post', { id: postId, title: `MCP integration post updated ${suffix}` });

const pageId = await expectSuccess('wp-forge-add-page', {
  title: `MCP integration page ${suffix}`,
  content: 'Created by the WordPress MCP integration test.',
  status: 'draft',
});
await expectSuccess('wp-forge-pages-search', { status: 'draft', search: 'MCP integration page', per_page: 5 });
const page = await expectSuccess('wp-forge-get-page', { id: pageId });
assert(page.id === pageId, 'wp-forge-get-page returned the wrong page');
await expectSuccess('wp-forge-update-page', { id: pageId, title: `MCP integration page updated ${suffix}` });

const cptId = await expectSuccess('wp-forge-add-cpt', {
  post_type: 'post',
  title: `MCP integration cpt ${suffix}`,
  content: 'Created through the CPT tool against the built-in post type.',
  status: 'draft',
});
await expectSuccess('wp-forge-list-post-types');
await expectSuccess('wp-forge-cpt-search', { post_type: 'post', status: 'draft', search: 'MCP integration cpt', per_page: 5 });
const cpt = await expectSuccess('wp-forge-get-cpt', { post_type: 'post', id: cptId });
assert(cpt.id === cptId, 'wp-forge-get-cpt returned the wrong item');
await expectSuccess('wp-forge-update-cpt', { post_type: 'post', id: cptId, title: `MCP integration cpt updated ${suffix}` });
await expectSuccess('wp-forge-delete-cpt', { post_type: 'post', id: cptId });

const taxonomies = await expectSuccess('wp-forge-list-taxonomies');
assert(taxonomies.some((taxonomy) => taxonomy.name === 'category'), 'wp-forge-list-taxonomies did not include categories');
await expectSuccess('wp-forge-list-taxonomy-terms', { taxonomy: 'category' });
const taxonomyTerm = await expectSuccess('wp-forge-save-taxonomy-term', {
  taxonomy: 'category',
  name: `MCP Taxonomy Term ${suffix}`,
  slug: `mcp-taxonomy-term-${suffix}`,
});
const taxonomyTermId = taxonomyTerm.term_id;
const savedTerm = await expectSuccess('wp-forge-get-taxonomy-term', { taxonomy: 'category', id: taxonomyTermId });
assert(savedTerm.id === taxonomyTermId, 'wp-forge-get-taxonomy-term returned the wrong term');
await expectSuccess('wp-forge-save-taxonomy-term', { taxonomy: 'category', id: taxonomyTermId, name: `MCP Taxonomy Term Updated ${suffix}`, description: 'Updated by MCP integration tests.' });
await expectSuccess('wp-forge-delete-taxonomy-term', { taxonomy: 'category', id: taxonomyTermId });

const mediaId = await expectSuccess('wp-forge-upload-media', {
  filename: `mcp-${suffix}.txt`,
  mime_type: 'text/plain',
  title: `MCP media ${suffix}`,
  base64: Buffer.from(`MCP media ${suffix}`).toString('base64'),
});
await expectSuccess('wp-forge-list-media', { per_page: 5 });
await expectSuccess('wp-forge-search-media', { search: 'MCP media', per_page: 5 });
const media = await expectSuccess('wp-forge-get-media', { id: mediaId });
assert(media.id === mediaId, 'wp-forge-get-media returned the wrong item');
const mediaFile = await expectSuccess('wp-forge-get-media-file', { id: mediaId });
assert(mediaFile.base64, 'wp-forge-get-media-file did not return base64 content');
await expectSuccess('wp-forge-update-media', { id: mediaId, title: `MCP media updated ${suffix}`, alt_text: 'MCP alt text' });
await expectSuccess('wp-forge-delete-media', { id: mediaId });

const usernameForTest = `mcp_user_${suffix}`.replace(/[^a-zA-Z0-9_]/g, '_');
const userId = await expectSuccess('wp-forge-add-user', {
  username: usernameForTest,
  email: `${usernameForTest}@example.com`,
  password: `mcp-password-${suffix}`,
  role: 'subscriber',
});
await expectSuccess('wp-forge-users-search', { search: usernameForTest, per_page: 5 });
const user = await expectSuccess('wp-forge-get-user', { id: userId });
assert(user.id === userId, 'wp-forge-get-user returned the wrong user');
await expectSuccess('wp-forge-update-user', { id: userId, first_name: 'MCP', last_name: 'Integration' });
await expectSuccess('wp-forge-delete-user', { id: userId });

await expectSuccess('wp-forge-get-general-settings');
await expectSuccess('wp-forge-update-general-settings', {});
await expectSuccess('wp-forge-get-site-info');
await expectSuccess('wp-forge-get-active-theme');

const plugins = await expectSuccess('wp-forge-list-plugins');
assert(
  plugins.some((plugin) => plugin.plugin_file === 'wp-plugin-mcp/wp-plugin-mcp.php'),
  'wp-forge-list-plugins did not include this plugin'
);
await expectError('wp-forge-install-plugin', { slug: `missing-mcp-plugin-${suffix}` });
await expectError('wp-forge-activate-plugin', { plugin_file: `missing-mcp-plugin-${suffix}/missing.php` }, 404);
await expectError('wp-forge-deactivate-plugin', { plugin_file: `missing-mcp-plugin-${suffix}/missing.php` }, 404);
await expectError('wp-forge-uninstall-plugin', { plugin_file: 'wp-plugin-mcp/wp-plugin-mcp.php' }, 400);

const themes = await expectSuccess('wp-forge-list-themes');
const activeTheme = themes.find((theme) => theme.active === true);
assert(activeTheme, 'wp-forge-list-themes did not include an active theme');
await expectError('wp-forge-install-theme', { slug: `missing-mcp-theme-${suffix}` });
await expectSuccess('wp-forge-activate-theme', { stylesheet: activeTheme.stylesheet });
await expectError('wp-forge-delete-theme', { stylesheet: activeTheme.stylesheet }, 400);

const optionName = `wp_forge_mcp_integration_${suffix}`;
await expectSuccess('wp-forge-update-option', { option_name: optionName, value: { status: 'ok', suffix } });
const option = await expectSuccess('wp-forge-get-option', { option_name: optionName });
assert(option.exists === true, 'wp-forge-get-option did not find the test option');
await expectSuccess('wp-forge-list-options', { name_prefix: 'wp_forge_mcp_integration_', per_page: 10 });
await expectSuccess('wp-forge-delete-option', { option_name: optionName });

const commentId = await expectSuccess('wp-forge-add-comment', {
  post_id: postId,
  content: `MCP comment ${suffix}`,
  author_name: 'MCP Integration',
  author_email: 'mcp-integration@example.com',
  status: 'hold',
});
await expectSuccess('wp-forge-list-comments', { post_id: postId, per_page: 5 });
const comment = await expectSuccess('wp-forge-get-comment', { id: commentId });
assert(comment.id === commentId, 'wp-forge-get-comment returned the wrong comment');
await expectSuccess('wp-forge-update-comment', { id: commentId, content: `MCP comment updated ${suffix}` });
await expectSuccess('wp-forge-approve-comment', { id: commentId });
await expectSuccess('wp-forge-spam-comment', { id: commentId });
await expectSuccess('wp-forge-delete-comment', { id: commentId });

const siteHealthInfo = await expectSuccess('wp-forge-get-site-health-info');
assert(siteHealthInfo['wp-core'], 'wp-forge-get-site-health-info did not include wp-core debug data');
await expectSuccess('wp-forge-list-site-health-tests');
await expectSuccess('wp-forge-get-error-log-path');
await expectSuccess('wp-forge-read-error-log', { lines: 5 });
await expectError('wp-forge-run-wp-cli-command', { args: ['plugin', 'list'] }, 403);

const activeGlobalStylesId = await expectSuccess('wp-forge-get-active-global-styles-id');
if (activeGlobalStylesId.id) {
  await expectSuccess('wp-forge-get-active-global-styles');
  await expectSuccess('wp-forge-get-global-styles', { id: activeGlobalStylesId.id });
} else {
  await expectError('wp-forge-get-active-global-styles', {});
  await expectError('wp-forge-get-global-styles', { id: 99999999 }, 404);
}
await expectError('wp-forge-update-global-styles', { id: 99999999, settings: {}, styles: {} }, 404);

await expectSuccess('wp-forge-list-api-functions', { namespace: 'wp/v2', methods: ['GET'], search: '/types' });
await expectSuccess('wp-forge-get-function-details', { route: '/wp/v2/types', method: 'GET' });
await expectSuccess('wp-forge-run-api-function', { route: '/wp/v2/types', method: 'GET' });

await expectSuccess('wp-forge-delete-page', { id: pageId });
await expectSuccess('wp-forge-delete-post', { id: postId });

for (const expectedTool of expectedTools) {
  assert(calledTools.has(expectedTool), `${expectedTool} was listed but not called by the integration test`);
}

console.log(`MCP endpoint OK: ${tools.length} tools listed and ${calledTools.size} tools called at ${endpoint}`);
