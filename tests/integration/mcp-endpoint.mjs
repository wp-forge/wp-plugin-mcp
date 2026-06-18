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
  'wp-forge-list-categories',
  'wp-forge-add-category',
  'wp-forge-update-category',
  'wp-forge-delete-category',
  'wp-forge-list-tags',
  'wp-forge-add-tag',
  'wp-forge-update-tag',
  'wp-forge-delete-tag',
  'wp-forge-list-taxonomies',
  'wp-forge-list-taxonomy-terms',
  'wp-forge-add-taxonomy-term',
  'wp-forge-update-taxonomy-term',
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

async function callTool(id, name, args = {}) {
  return post({
    jsonrpc: '2.0',
    id,
    method: 'tools/call',
    params: {
      name,
      arguments: args,
    },
  }, initialized.sessionId);
}

const plugins = await callTool(3, 'wp-forge-list-plugins');

assert(plugins.status === 200, `wp-forge-list-plugins returned HTTP ${plugins.status}`);
assert(plugins.body?.result?.structuredContent?.status === 'success', 'wp-forge-list-plugins did not return success');
assert(
  plugins.body.result.structuredContent.message.some((plugin) => plugin.plugin_file === 'wp-plugin-mcp/wp-plugin-mcp.php'),
  'wp-forge-list-plugins did not include this plugin'
);

const themes = await callTool(4, 'wp-forge-list-themes');
assert(themes.status === 200, `wp-forge-list-themes returned HTTP ${themes.status}`);
assert(themes.body?.result?.structuredContent?.status === 'success', 'wp-forge-list-themes did not return success');
assert(
  themes.body.result.structuredContent.message.some((theme) => theme.active === true),
  'wp-forge-list-themes did not include an active theme'
);

const taxonomies = await callTool(5, 'wp-forge-list-taxonomies');
assert(taxonomies.status === 200, `wp-forge-list-taxonomies returned HTTP ${taxonomies.status}`);
assert(taxonomies.body?.result?.structuredContent?.status === 'success', 'wp-forge-list-taxonomies did not return success');
assert(
  taxonomies.body.result.structuredContent.message.some((taxonomy) => taxonomy.name === 'category'),
  'wp-forge-list-taxonomies did not include categories'
);

const options = await callTool(6, 'wp-forge-list-options', { name_prefix: 'blog', per_page: 10 });
assert(options.status === 200, `wp-forge-list-options returned HTTP ${options.status}`);
assert(options.body?.result?.structuredContent?.status === 'success', 'wp-forge-list-options did not return success');
assert(Array.isArray(options.body.result.structuredContent.message), 'wp-forge-list-options did not return an option array');

const comments = await callTool(7, 'wp-forge-list-comments', { per_page: 5 });
assert(comments.status === 200, `wp-forge-list-comments returned HTTP ${comments.status}`);
assert(comments.body?.result?.structuredContent?.status === 'success', 'wp-forge-list-comments did not return success');
assert(Array.isArray(comments.body.result.structuredContent.message), 'wp-forge-list-comments did not return a comment array');

const errorLog = await callTool(8, 'wp-forge-read-error-log', { lines: 5 });
assert(errorLog.status === 200, `wp-forge-read-error-log returned HTTP ${errorLog.status}`);
assert(errorLog.body?.result?.structuredContent?.status === 'success', 'wp-forge-read-error-log did not return success');

const siteHealth = await callTool(9, 'wp-forge-list-site-health-tests');
assert(siteHealth.status === 200, `wp-forge-list-site-health-tests returned HTTP ${siteHealth.status}`);
assert(siteHealth.body?.result?.structuredContent?.status === 'success', 'wp-forge-list-site-health-tests did not return success');

const siteHealthInfo = await callTool(10, 'wp-forge-get-site-health-info');
assert(siteHealthInfo.status === 200, `wp-forge-get-site-health-info returned HTTP ${siteHealthInfo.status}`);
assert(siteHealthInfo.body?.result?.structuredContent?.status === 'success', 'wp-forge-get-site-health-info did not return success');
assert(siteHealthInfo.body.result.structuredContent.message['wp-core'], 'wp-forge-get-site-health-info did not include wp-core debug data');

const siteInfo = await callTool(11, 'wp-forge-get-site-info');
assert(siteInfo.status === 200, `wp-forge-get-site-info returned HTTP ${siteInfo.status}`);
assert(siteInfo.body?.result?.structuredContent?.status === 'success', 'wp-forge-get-site-info did not return success');

const uninstallSelf = await callTool(12, 'wp-forge-uninstall-plugin', { plugin_file: 'wp-plugin-mcp/wp-plugin-mcp.php' });
assert(uninstallSelf.status === 200, `wp-forge-uninstall-plugin returned HTTP ${uninstallSelf.status}`);
assert(uninstallSelf.body?.result?.structuredContent?.status === 'error', 'wp-forge-uninstall-plugin should refuse to uninstall itself');
assert(uninstallSelf.body?.result?.structuredContent?.statusCode === 400, 'wp-forge-uninstall-plugin self-protection should return a 400 statusCode');

const wpCli = await callTool(13, 'wp-forge-run-wp-cli-command', { args: ['plugin', 'list'] });
assert(wpCli.status === 200, `wp-forge-run-wp-cli-command returned HTTP ${wpCli.status}`);
assert(wpCli.body?.result?.structuredContent?.status === 'error', 'wp-forge-run-wp-cli-command should be disabled by default');
assert(wpCli.body?.result?.structuredContent?.statusCode === 403, 'wp-forge-run-wp-cli-command disabled response should return a 403 statusCode');

const posts = await post({
  jsonrpc: '2.0',
  id: 14,
  method: 'tools/call',
  params: {
    name: 'wp-forge-posts-search',
    arguments: {},
  },
}, initialized.sessionId);

assert(posts.status === 200, `wp-forge-posts-search returned HTTP ${posts.status}`);
assert(posts.body?.result?.structuredContent?.status === 'success', 'wp-forge-posts-search did not return success');

console.log(`MCP endpoint OK: ${tools.length} tools available at ${endpoint}`);
