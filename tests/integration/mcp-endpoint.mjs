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

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function initializeWithRetry(payload) {
  let lastResponse = null;

  for (let attempt = 1; attempt <= 30; attempt++) {
    lastResponse = await post(payload);

    if (lastResponse.status !== 404) {
      return lastResponse;
    }

    await sleep(1000);
  }

  return lastResponse;
}

async function getAdminSettingsPage() {
  const response = await fetch(new URL('/wp-admin/options-general.php?page=wp-plugin-mcp', endpointUrl.origin), {
    headers: username && password
      ? { Authorization: `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}` }
      : { Cookie: authHeaders.Cookie },
  });

  return response.text();
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

const initialized = await initializeWithRetry({
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
  'wp-forge-post-type-list',
  'wp-forge-content-search',
  'wp-forge-content-get',
  'wp-forge-content-save',
  'wp-forge-content-delete',
  'wp-forge-taxonomy-list',
  'wp-forge-taxonomy-term-list',
  'wp-forge-taxonomy-term-get',
  'wp-forge-taxonomy-term-save',
  'wp-forge-taxonomy-term-delete',
  'wp-forge-media-list',
  'wp-forge-media-get',
  'wp-forge-media-file-get',
  'wp-forge-media-upload',
  'wp-forge-media-update',
  'wp-forge-media-delete',
  'wp-forge-media-search',
  'wp-forge-user-search',
  'wp-forge-user-get',
  'wp-forge-user-save',
  'wp-forge-user-delete',
  'wp-forge-general-settings-get',
  'wp-forge-general-settings-save',
  'wp-forge-site-info-get',
  'wp-forge-plugin-list',
  'wp-forge-plugin-install',
  'wp-forge-plugin-activate',
  'wp-forge-plugin-deactivate',
  'wp-forge-plugin-uninstall',
  'wp-forge-theme-list',
  'wp-forge-theme-install',
  'wp-forge-theme-activate',
  'wp-forge-theme-delete',
  'wp-forge-option-list',
  'wp-forge-option-get',
  'wp-forge-option-save',
  'wp-forge-option-delete',
  'wp-forge-comment-list',
  'wp-forge-comment-get',
  'wp-forge-comment-save',
  'wp-forge-comment-delete',
  'wp-forge-site-health-info-get',
  'wp-forge-site-health-test-list',
  'wp-forge-error-log-read',
  'wp-forge-wp-cli-command-run',
  'wp-forge-global-styles-get',
  'wp-forge-global-styles-update',
  'wp-forge-global-styles-active-get',
  'wp-forge-global-styles-active-id-get',
  'wp-forge-theme-active-get',
  'wp-forge-api-function-list',
  'wp-forge-api-function-details-get',
  'wp-forge-api-function-run',
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

const savedPost = await expectSuccess('wp-forge-content-save', {
  post_type: 'post',
  title: `MCP integration post ${suffix}`,
  content: 'Created by the WordPress MCP integration test.',
  status: 'draft',
});
const postId = savedPost.id;
await expectSuccess('wp-forge-content-search', { post_type: 'post', status: 'draft', query: 'MCP integration post', per_page: 5 });
const postItem = await expectSuccess('wp-forge-content-get', { post_type: 'post', id: postId, fields: ['id', 'type', 'title'] });
assert(postItem.id === postId && postItem.type === 'post', 'wp-forge-content-get returned the wrong post');
await expectSuccess('wp-forge-content-save', { post_type: 'post', id: postId, title: `MCP integration post updated ${suffix}` });
await expectError('wp-forge-content-save', { post_type: 'post', id: postId, parent_id: 1 }, 400);

const page = await expectSuccess('wp-forge-content-save', {
  post_type: 'page',
  title: `MCP integration page ${suffix}`,
  content: 'Created by the WordPress MCP integration test.',
  status: 'draft',
});
const pageId = page.id;
await expectSuccess('wp-forge-content-search', { post_type: 'page', status: 'draft', query: 'MCP integration page', per_page: 5, orderby: 'menu_order' });
const pageItem = await expectSuccess('wp-forge-content-get', { post_type: 'page', id: pageId });
assert(pageItem.id === pageId, 'wp-forge-content-get returned the wrong page');
await expectSuccess('wp-forge-content-save', { post_type: 'page', id: pageId, title: `MCP integration page updated ${suffix}`, parent_id: 0 });
await expectError('wp-forge-content-save', { post_type: 'page', id: postId, title: 'Wrong type' }, 400);

const postTypes = await expectSuccess('wp-forge-post-type-list', { public: true });
assert(Array.isArray(postTypes.post_types), 'wp-forge-post-type-list did not return a post_types array');
const postType = postTypes.post_types.find((item) => item.slug === 'post');
assert(postType, 'wp-forge-post-type-list did not include posts');
assert(postType.hierarchical === false, 'wp-forge-post-type-list did not expose hierarchical status');
assert(Array.isArray(postType.supports) && postType.supports.includes('title'), 'wp-forge-post-type-list did not expose supported features');
assert(Array.isArray(postType.taxonomies) && postType.taxonomies.includes('category'), 'wp-forge-post-type-list did not expose supported taxonomies');

const taxonomies = await expectSuccess('wp-forge-taxonomy-list');
assert(taxonomies.some((taxonomy) => taxonomy.name === 'category'), 'wp-forge-taxonomy-list did not include categories');
await expectSuccess('wp-forge-taxonomy-term-list', { taxonomy: 'category' });
const taxonomyTerm = await expectSuccess('wp-forge-taxonomy-term-save', {
  taxonomy: 'category',
  name: `MCP Taxonomy Term ${suffix}`,
  slug: `mcp-taxonomy-term-${suffix}`,
});
const taxonomyTermId = taxonomyTerm.term_id;
await expectSuccess('wp-forge-content-save', { post_type: 'post', id: postId, taxonomies: { category: [taxonomyTermId] } });
await expectError('wp-forge-content-save', { post_type: 'post', id: postId, taxonomies: { nav_menu: [taxonomyTermId] } }, 400);
const savedTerm = await expectSuccess('wp-forge-taxonomy-term-get', { taxonomy: 'category', id: taxonomyTermId });
assert(savedTerm.id === taxonomyTermId, 'wp-forge-taxonomy-term-get returned the wrong term');
await expectSuccess('wp-forge-taxonomy-term-save', { taxonomy: 'category', id: taxonomyTermId, name: `MCP Taxonomy Term Updated ${suffix}`, description: 'Updated by MCP integration tests.' });
await expectSuccess('wp-forge-taxonomy-term-delete', { taxonomy: 'category', id: taxonomyTermId });

const mediaId = await expectSuccess('wp-forge-media-upload', {
  filename: `mcp-${suffix}.txt`,
  mime_type: 'text/plain',
  title: `MCP media ${suffix}`,
  base64: Buffer.from(`MCP media ${suffix}`).toString('base64'),
});
await expectSuccess('wp-forge-media-list', { per_page: 5 });
await expectSuccess('wp-forge-media-search', { search: 'MCP media', per_page: 5 });
const media = await expectSuccess('wp-forge-media-get', { id: mediaId });
assert(media.id === mediaId, 'wp-forge-media-get returned the wrong item');
const mediaFile = await expectSuccess('wp-forge-media-file-get', { id: mediaId });
assert(mediaFile.base64, 'wp-forge-media-file-get did not return base64 content');
await expectSuccess('wp-forge-media-update', { id: mediaId, title: `MCP media updated ${suffix}`, alt_text: 'MCP alt text' });
await expectSuccess('wp-forge-media-delete', { id: mediaId });

const usernameForTest = `mcp_user_${suffix}`.replace(/[^a-zA-Z0-9_]/g, '_');
const userId = await expectSuccess('wp-forge-user-save', {
  username: usernameForTest,
  email: `${usernameForTest}@example.com`,
  password: `mcp-password-${suffix}`,
  role: 'subscriber',
});
await expectSuccess('wp-forge-user-search', { search: usernameForTest, per_page: 5 });
const user = await expectSuccess('wp-forge-user-get', { id: userId });
assert(user.id === userId, 'wp-forge-user-get returned the wrong user');
await expectSuccess('wp-forge-user-save', { id: userId, first_name: 'MCP', last_name: 'Integration' });
await expectSuccess('wp-forge-user-delete', { id: userId });

await expectSuccess('wp-forge-general-settings-get');
await expectSuccess('wp-forge-general-settings-save', {});
await expectSuccess('wp-forge-site-info-get');
await expectSuccess('wp-forge-theme-active-get');

const plugins = await expectSuccess('wp-forge-plugin-list');
assert(
  plugins.some((plugin) => plugin.plugin_file === 'wp-plugin-mcp/wp-plugin-mcp.php'),
  'wp-forge-plugin-list did not include this plugin'
);
await expectError('wp-forge-plugin-install', { slug: `missing-mcp-plugin-${suffix}` });
await expectError('wp-forge-plugin-activate', { plugin_file: `missing-mcp-plugin-${suffix}/missing.php` }, 404);
await expectError('wp-forge-plugin-deactivate', { plugin_file: `missing-mcp-plugin-${suffix}/missing.php` }, 404);
await expectError('wp-forge-plugin-uninstall', { plugin_file: 'wp-plugin-mcp/wp-plugin-mcp.php' }, 400);

const themes = await expectSuccess('wp-forge-theme-list');
const activeTheme = themes.find((theme) => theme.active === true);
assert(activeTheme, 'wp-forge-theme-list did not include an active theme');
await expectError('wp-forge-theme-install', { slug: `missing-mcp-theme-${suffix}` });
await expectSuccess('wp-forge-theme-activate', { stylesheet: activeTheme.stylesheet });
await expectError('wp-forge-theme-delete', { stylesheet: activeTheme.stylesheet }, 400);

const optionName = `wp_forge_mcp_integration_${suffix}`;
await expectSuccess('wp-forge-option-save', { option_name: optionName, value: { status: 'ok', suffix } });
const option = await expectSuccess('wp-forge-option-get', { option_name: optionName });
assert(option.exists === true, 'wp-forge-option-get did not find the test option');
await expectSuccess('wp-forge-option-list', { name_prefix: 'wp_forge_mcp_integration_', per_page: 10 });
await expectSuccess('wp-forge-option-delete', { option_name: optionName });

const commentId = await expectSuccess('wp-forge-comment-save', {
  post_id: postId,
  content: `MCP comment ${suffix}`,
  author_name: 'MCP Integration',
  author_email: 'mcp-integration@example.com',
  status: 'hold',
});
await expectSuccess('wp-forge-comment-list', { post_id: postId, per_page: 5 });
const comment = await expectSuccess('wp-forge-comment-get', { id: commentId });
assert(comment.id === commentId, 'wp-forge-comment-get returned the wrong comment');
await expectSuccess('wp-forge-comment-save', { id: commentId, content: `MCP comment updated ${suffix}` });
await expectSuccess('wp-forge-comment-save', { id: commentId, status: 'approved' });
const approvedComment = await expectSuccess('wp-forge-comment-get', { id: commentId });
assert(approvedComment.status === 'approved', 'wp-forge-comment-save did not approve the comment');
await expectSuccess('wp-forge-comment-save', { id: commentId, status: 'spam' });
const spamComment = await expectSuccess('wp-forge-comment-get', { id: commentId });
assert(spamComment.status === 'spam', 'wp-forge-comment-save did not mark the comment as spam');
await expectSuccess('wp-forge-comment-delete', { id: commentId });

const siteHealthInfo = await expectSuccess('wp-forge-site-health-info-get');
assert(siteHealthInfo['wp-core'], 'wp-forge-site-health-info-get did not include wp-core debug data');
await expectSuccess('wp-forge-site-health-test-list');
await expectSuccess('wp-forge-error-log-read', { lines: 5 });
await expectError('wp-forge-wp-cli-command-run', { args: ['plugin', 'list'] }, 403);

const activeGlobalStylesId = await expectSuccess('wp-forge-global-styles-active-id-get');
if (activeGlobalStylesId.id) {
  await expectSuccess('wp-forge-global-styles-active-get');
  await expectSuccess('wp-forge-global-styles-get', { id: activeGlobalStylesId.id });
} else {
  await expectError('wp-forge-global-styles-active-get', {});
  await expectError('wp-forge-global-styles-get', { id: 99999999 }, 404);
}
await expectError('wp-forge-global-styles-update', { id: 99999999, settings: {}, styles: {} }, 404);

await expectSuccess('wp-forge-api-function-list', { namespace: 'wp/v2', methods: ['GET'], search: '/types' });
await expectSuccess('wp-forge-api-function-details-get', { route: '/wp/v2/types', method: 'GET' });
await expectSuccess('wp-forge-api-function-run', { route: '/wp/v2/types', method: 'GET' });

if (!username && !password) {
  await expectSuccess('wp-forge-option-save', { option_name: 'wp_forge_mcp_activity_log_enabled', value: '' });
  const disabledSettingsPage = await getAdminSettingsPage();
  assert(disabledSettingsPage.includes('Enable MCP activity log'), 'Activity log setting was not displayed');
  assert(!disabledSettingsPage.includes('Filter Activity Log'), 'Activity log filters were displayed while disabled');
  assert(!disabledSettingsPage.includes('No MCP activity has been logged yet.'), 'Activity log empty state was displayed while disabled');

  await expectSuccess('wp-forge-option-save', { option_name: 'wp_forge_mcp_activity_log_enabled', value: '1' });
  await expectSuccess('wp-forge-site-info-get');
  const settingsPage = await getAdminSettingsPage();
  assert(settingsPage.includes('wp-forge-site-info-get'), 'Activity log did not show a logged MCP tool call');
  assert(settingsPage.includes('Filter Activity Log'), 'Activity log filters were not displayed');
  assert(settingsPage.includes('mcp_log_per_page'), 'Activity log per-page control was not displayed');
  assert(!settingsPage.includes('<td>0 ms</td>'), 'Activity log displayed a 0 ms duration');
}

await expectSuccess('wp-forge-content-delete', { post_type: 'page', id: pageId, force: true });
await expectSuccess('wp-forge-content-delete', { post_type: 'post', id: postId, force: true });

for (const expectedTool of expectedTools) {
  assert(calledTools.has(expectedTool), `${expectedTool} was listed but not called by the integration test`);
}

console.log(`MCP endpoint OK: ${tools.length} tools listed and ${calledTools.size} tools called at ${endpoint}`);
