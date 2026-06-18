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
  const postEditorUrl = new URL('/wp-admin/post-new.php', endpointUrl.origin);
  let response = await fetch(postEditorUrl, { redirect: 'manual' });
  setCookiesFromResponse(response, cookies);

  if (response.status >= 300 && response.status < 400 && response.headers.get('location')) {
    response = await fetch(new URL(response.headers.get('location'), endpointUrl.origin), {
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
assert(tools.some((tool) => tool.name === 'wp-forge-posts-search'), 'wp-forge-posts-search was not listed');
assert(tools.some((tool) => tool.name === 'wp-forge-get-site-info'), 'wp-forge-get-site-info was not listed');
assert(tools.some((tool) => tool.name === 'wp-forge-list-plugins'), 'wp-forge-list-plugins was not listed');
assert(tools.some((tool) => tool.name === 'wp-forge-list-themes'), 'wp-forge-list-themes was not listed');
assert(tools.some((tool) => tool.name === 'wp-forge-list-options'), 'wp-forge-list-options was not listed');
assert(tools.some((tool) => tool.name === 'wp-forge-list-comments'), 'wp-forge-list-comments was not listed');
assert(tools.some((tool) => tool.name === 'wp-forge-list-taxonomies'), 'wp-forge-list-taxonomies was not listed');
assert(tools.some((tool) => tool.name === 'wp-forge-get-site-health-info'), 'wp-forge-get-site-health-info was not listed');
assert(tools.some((tool) => tool.name === 'wp-forge-read-error-log'), 'wp-forge-read-error-log was not listed');
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

const siteInfo = await callTool(10, 'wp-forge-get-site-info');
assert(siteInfo.status === 200, `wp-forge-get-site-info returned HTTP ${siteInfo.status}`);
assert(siteInfo.body?.result?.structuredContent?.status === 'success', 'wp-forge-get-site-info did not return success');

const posts = await post({
  jsonrpc: '2.0',
  id: 11,
  method: 'tools/call',
  params: {
    name: 'wp-forge-posts-search',
    arguments: {},
  },
}, initialized.sessionId);

assert(posts.status === 200, `wp-forge-posts-search returned HTTP ${posts.status}`);
assert(posts.body?.result?.structuredContent?.status === 'success', 'wp-forge-posts-search did not return success');

console.log(`MCP endpoint OK: ${tools.length} tools available at ${endpoint}`);
