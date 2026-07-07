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
  'wp-forge-post_type_list',
  'wp-forge-content_search',
  'wp-forge-content_get',
  'wp-forge-content_save',
  'wp-forge-content_delete',
  'wp-forge-taxonomy_list',
  'wp-forge-taxonomy_term_list',
  'wp-forge-taxonomy_term_get',
  'wp-forge-taxonomy_term_save',
  'wp-forge-taxonomy_term_delete',
  'wp-forge-media_list',
  'wp-forge-media_get',
  'wp-forge-media_file_get',
  'wp-forge-media_upload',
  'wp-forge-media_update',
  'wp-forge-media_delete',
  'wp-forge-media_search',
  'wp-forge-user_search',
  'wp-forge-user_get',
  'wp-forge-user_save',
  'wp-forge-user_delete',
  'wp-forge-general_settings_get',
  'wp-forge-general_settings_save',
  'wp-forge-site_info_get',
  'wp-forge-plugin_list',
  'wp-forge-plugin_install',
  'wp-forge-plugin_set_status',
  'wp-forge-plugin_uninstall',
  'wp-forge-theme_list',
  'wp-forge-theme_install',
  'wp-forge-theme_activate',
  'wp-forge-theme_delete',
  'wp-forge-option_list',
  'wp-forge-option_get',
  'wp-forge-option_save',
  'wp-forge-option_delete',
  'wp-forge-comment_list',
  'wp-forge-comment_get',
  'wp-forge-comment_save',
  'wp-forge-comment_delete',
  'wp-forge-site_health_info_get',
  'wp-forge-site_health_test_list',
  'wp-forge-error_log_read',
  'wp-forge-wp_cli_command_run',
  'wp-forge-global_styles_get',
  'wp-forge-global_styles_update',
  'wp-forge-active_global_styles_get',
  'wp-forge-active_global_styles_id_get',
  'wp-forge-active_theme_get',
  'wp-forge-api_function_list',
  'wp-forge-function_details_get',
  'wp-forge-api_function_run',
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

const savedPost = await expectSuccess('wp-forge-content_save', {
  post_type: 'post',
  title: `MCP integration post ${suffix}`,
  content: 'Created by the WordPress MCP integration test.',
  status: 'draft',
});
const postId = savedPost.id;
await expectSuccess('wp-forge-content_search', { post_type: 'post', status: 'draft', query: 'MCP integration post', per_page: 5 });
const postItem = await expectSuccess('wp-forge-content_get', { post_type: 'post', id: postId, fields: ['id', 'type', 'title'] });
assert(postItem.id === postId && postItem.type === 'post', 'wp-forge-content_get returned the wrong post');
await expectSuccess('wp-forge-content_save', { post_type: 'post', id: postId, title: `MCP integration post updated ${suffix}` });
await expectError('wp-forge-content_save', { post_type: 'post', id: postId, parent_id: 1 }, 400);

const page = await expectSuccess('wp-forge-content_save', {
  post_type: 'page',
  title: `MCP integration page ${suffix}`,
  content: 'Created by the WordPress MCP integration test.',
  status: 'draft',
});
const pageId = page.id;
await expectSuccess('wp-forge-content_search', { post_type: 'page', status: 'draft', query: 'MCP integration page', per_page: 5, orderby: 'menu_order' });
const pageItem = await expectSuccess('wp-forge-content_get', { post_type: 'page', id: pageId });
assert(pageItem.id === pageId, 'wp-forge-content_get returned the wrong page');
await expectSuccess('wp-forge-content_save', { post_type: 'page', id: pageId, title: `MCP integration page updated ${suffix}`, parent_id: 0 });
await expectError('wp-forge-content_save', { post_type: 'page', id: postId, title: 'Wrong type' }, 400);

const postTypes = await expectSuccess('wp-forge-post_type_list', { public: true });
assert(Array.isArray(postTypes.post_types), 'wp-forge-post_type_list did not return a post_types array');
const postType = postTypes.post_types.find((item) => item.slug === 'post');
assert(postType, 'wp-forge-post_type_list did not include posts');
assert(postType.hierarchical === false, 'wp-forge-post_type_list did not expose hierarchical status');
assert(Array.isArray(postType.supports) && postType.supports.includes('title'), 'wp-forge-post_type_list did not expose supported features');
assert(Array.isArray(postType.taxonomies) && postType.taxonomies.includes('category'), 'wp-forge-post_type_list did not expose supported taxonomies');

const taxonomies = await expectSuccess('wp-forge-taxonomy_list');
assert(taxonomies.some((taxonomy) => taxonomy.name === 'category'), 'wp-forge-taxonomy_list did not include categories');
await expectSuccess('wp-forge-taxonomy_term_list', { taxonomy: 'category' });
const taxonomyTerm = await expectSuccess('wp-forge-taxonomy_term_save', {
  taxonomy: 'category',
  name: `MCP Taxonomy Term ${suffix}`,
  slug: `mcp-taxonomy-term-${suffix}`,
});
const taxonomyTermId = taxonomyTerm.term_id;
await expectSuccess('wp-forge-content_save', { post_type: 'post', id: postId, taxonomies: { category: [taxonomyTermId] } });
await expectError('wp-forge-content_save', { post_type: 'post', id: postId, taxonomies: { nav_menu: [taxonomyTermId] } }, 400);
const savedTerm = await expectSuccess('wp-forge-taxonomy_term_get', { taxonomy: 'category', id: taxonomyTermId });
assert(savedTerm.id === taxonomyTermId, 'wp-forge-taxonomy_term_get returned the wrong term');
await expectSuccess('wp-forge-taxonomy_term_save', { taxonomy: 'category', id: taxonomyTermId, name: `MCP Taxonomy Term Updated ${suffix}`, description: 'Updated by MCP integration tests.' });
await expectSuccess('wp-forge-taxonomy_term_delete', { taxonomy: 'category', id: taxonomyTermId });

const mediaId = await expectSuccess('wp-forge-media_upload', {
  filename: `mcp-${suffix}.txt`,
  mime_type: 'text/plain',
  title: `MCP media ${suffix}`,
  base64: Buffer.from(`MCP media ${suffix}`).toString('base64'),
});
await expectSuccess('wp-forge-media_list', { per_page: 5 });
await expectSuccess('wp-forge-media_search', { search: 'MCP media', per_page: 5 });
const media = await expectSuccess('wp-forge-media_get', { id: mediaId });
assert(media.id === mediaId, 'wp-forge-media_get returned the wrong item');
const mediaFile = await expectSuccess('wp-forge-media_file_get', { id: mediaId });
assert(mediaFile.base64, 'wp-forge-media_file_get did not return base64 content');
await expectSuccess('wp-forge-media_update', { id: mediaId, title: `MCP media updated ${suffix}`, alt_text: 'MCP alt text' });
await expectSuccess('wp-forge-media_delete', { id: mediaId });

const usernameForTest = `mcp_user_${suffix}`.replace(/[^a-zA-Z0-9_]/g, '_');
const userId = await expectSuccess('wp-forge-user_save', {
  username: usernameForTest,
  email: `${usernameForTest}@example.com`,
  password: `mcp-password-${suffix}`,
  role: 'subscriber',
});
await expectSuccess('wp-forge-user_search', { search: usernameForTest, per_page: 5 });
const user = await expectSuccess('wp-forge-user_get', { id: userId });
assert(user.id === userId, 'wp-forge-user_get returned the wrong user');
await expectSuccess('wp-forge-user_save', { id: userId, first_name: 'MCP', last_name: 'Integration' });
await expectSuccess('wp-forge-user_delete', { id: userId });

await expectSuccess('wp-forge-general_settings_get');
await expectSuccess('wp-forge-general_settings_save', {});
await expectSuccess('wp-forge-site_info_get');
await expectSuccess('wp-forge-active_theme_get');

const plugins = await expectSuccess('wp-forge-plugin_list');
assert(
  plugins.some((plugin) => plugin.plugin_file === 'wp-plugin-mcp/wp-plugin-mcp.php'),
  'wp-forge-plugin_list did not include this plugin'
);
await expectError('wp-forge-plugin_install', { slug: `missing-mcp-plugin-${suffix}` });
await expectError('wp-forge-plugin_set_status', { plugin_file: `missing-mcp-plugin-${suffix}/missing.php`, status: 'active' }, 404);
await expectError('wp-forge-plugin_set_status', { plugin_file: `missing-mcp-plugin-${suffix}/missing.php`, status: 'inactive' }, 404);
await expectError('wp-forge-plugin_uninstall', { plugin_file: 'wp-plugin-mcp/wp-plugin-mcp.php' }, 400);

const themes = await expectSuccess('wp-forge-theme_list');
const activeTheme = themes.find((theme) => theme.active === true);
assert(activeTheme, 'wp-forge-theme_list did not include an active theme');
await expectError('wp-forge-theme_install', { slug: `missing-mcp-theme-${suffix}` });
await expectSuccess('wp-forge-theme_activate', { stylesheet: activeTheme.stylesheet });
await expectError('wp-forge-theme_delete', { stylesheet: activeTheme.stylesheet }, 400);

const optionName = `wp_forge_mcp_integration_${suffix}`;
await expectSuccess('wp-forge-option_save', { option_name: optionName, value: { status: 'ok', suffix } });
const option = await expectSuccess('wp-forge-option_get', { option_name: optionName });
assert(option.exists === true, 'wp-forge-option_get did not find the test option');
await expectSuccess('wp-forge-option_list', { name_prefix: 'wp_forge_mcp_integration_', per_page: 10 });
await expectSuccess('wp-forge-option_delete', { option_name: optionName });

const commentId = await expectSuccess('wp-forge-comment_save', {
  post_id: postId,
  content: `MCP comment ${suffix}`,
  author_name: 'MCP Integration',
  author_email: 'mcp-integration@example.com',
  status: 'hold',
});
await expectSuccess('wp-forge-comment_list', { post_id: postId, per_page: 5 });
const comment = await expectSuccess('wp-forge-comment_get', { id: commentId });
assert(comment.id === commentId, 'wp-forge-comment_get returned the wrong comment');
await expectSuccess('wp-forge-comment_save', { id: commentId, content: `MCP comment updated ${suffix}` });
await expectSuccess('wp-forge-comment_save', { id: commentId, status: 'approved' });
const approvedComment = await expectSuccess('wp-forge-comment_get', { id: commentId });
assert(approvedComment.status === 'approved', 'wp-forge-comment_save did not approve the comment');
await expectSuccess('wp-forge-comment_save', { id: commentId, status: 'spam' });
const spamComment = await expectSuccess('wp-forge-comment_get', { id: commentId });
assert(spamComment.status === 'spam', 'wp-forge-comment_save did not mark the comment as spam');
await expectSuccess('wp-forge-comment_delete', { id: commentId });

const siteHealthInfo = await expectSuccess('wp-forge-site_health_info_get');
assert(siteHealthInfo['wp-core'], 'wp-forge-site_health_info_get did not include wp-core debug data');
await expectSuccess('wp-forge-site_health_test_list');
await expectSuccess('wp-forge-error_log_read', { lines: 5 });
await expectError('wp-forge-wp_cli_command_run', { args: ['plugin', 'list'] }, 403);

const activeGlobalStylesId = await expectSuccess('wp-forge-active_global_styles_id_get');
if (activeGlobalStylesId.id) {
  await expectSuccess('wp-forge-active_global_styles_get');
  await expectSuccess('wp-forge-global_styles_get', { id: activeGlobalStylesId.id });
} else {
  await expectError('wp-forge-active_global_styles_get', {});
  await expectError('wp-forge-global_styles_get', { id: 99999999 }, 404);
}
await expectError('wp-forge-global_styles_update', { id: 99999999, settings: {}, styles: {} }, 404);

await expectSuccess('wp-forge-api_function_list', { namespace: 'wp/v2', methods: ['GET'], search: '/types' });
await expectSuccess('wp-forge-function_details_get', { route: '/wp/v2/types', method: 'GET' });
await expectSuccess('wp-forge-api_function_run', { route: '/wp/v2/types', method: 'GET' });

if (!username && !password) {
  await expectSuccess('wp-forge-option_save', { option_name: 'wp_forge_mcp_activity_log_enabled', value: '' });
  const disabledSettingsPage = await getAdminSettingsPage();
  assert(disabledSettingsPage.includes('Enable MCP activity log'), 'Activity log setting was not displayed');
  assert(!disabledSettingsPage.includes('Filter Activity Log'), 'Activity log filters were displayed while disabled');
  assert(!disabledSettingsPage.includes('No MCP activity has been logged yet.'), 'Activity log empty state was displayed while disabled');

  await expectSuccess('wp-forge-option_save', { option_name: 'wp_forge_mcp_activity_log_enabled', value: '1' });
  await expectSuccess('wp-forge-site_info_get');
  const settingsPage = await getAdminSettingsPage();
  assert(settingsPage.includes('wp-forge-site_info_get'), 'Activity log did not show a logged MCP tool call');
  assert(settingsPage.includes('Filter Activity Log'), 'Activity log filters were not displayed');
  assert(settingsPage.includes('mcp_log_per_page'), 'Activity log per-page control was not displayed');
  assert(!settingsPage.includes('<td>0 ms</td>'), 'Activity log displayed a 0 ms duration');
}

await expectSuccess('wp-forge-content_delete', { post_type: 'page', id: pageId, force: true });
await expectSuccess('wp-forge-content_delete', { post_type: 'post', id: postId, force: true });

for (const expectedTool of expectedTools) {
  assert(calledTools.has(expectedTool), `${expectedTool} was listed but not called by the integration test`);
}

console.log(`MCP endpoint OK: ${tools.length} tools listed and ${calledTools.size} tools called at ${endpoint}`);
