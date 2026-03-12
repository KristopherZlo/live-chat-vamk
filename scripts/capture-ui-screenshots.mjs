import { execFileSync } from 'node:child_process';
import { promises as fs } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const BASE_URL = normalizeBaseUrl(process.env.SCREENSHOT_BASE_URL ?? 'http://127.0.0.1:8000');
const OUTPUT_ROOT = process.env.SCREENSHOT_OUTPUT_DIR ?? 'interface-screenshots-auto';
const VIEWPORT = parseViewport(process.env.SCREENSHOT_VIEWPORT ?? '1920x1080');
const WAIT_MS = parsePositiveInt(process.env.SCREENSHOT_WAIT_MS, 450);
const TIMEOUT_MS = parsePositiveInt(process.env.SCREENSHOT_TIMEOUT_MS, 30000);
const NAV_RETRIES = parsePositiveInt(process.env.SCREENSHOT_NAV_RETRIES, 3);
const INCLUDE_TEST_ROUTES = process.env.SCREENSHOT_INCLUDE_TEST_ROUTES === '1';
const AUTH_EMAIL = process.env.SCREENSHOT_AUTH_EMAIL ?? '';
const AUTH_PASSWORD = process.env.SCREENSHOT_AUTH_PASSWORD ?? '';
const USE_HMR = process.env.SCREENSHOT_USE_HMR === '1';
const DISABLE_ONBOARDING_MODALS = process.env.SCREENSHOT_DISABLE_ONBOARDING_MODALS !== '0';
const PRELOAD_LAST_VISITED_ROOMS = process.env.SCREENSHOT_PRELOAD_LAST_VISITED_ROOMS !== '0';
const SEED_ROOM_DEMO = process.env.SCREENSHOT_SEED_ROOM_DEMO !== '0';
const SEED_ROOM_SLUG = String(process.env.SCREENSHOT_SEED_ROOM_SLUG ?? 'neo-zion-briefing').trim();
const SEED_DEMO_COUNT = parsePositiveInt(process.env.SCREENSHOT_SEED_DEMO_COUNT, 90);
const EXTRA_GUEST_ROUTES = parseRouteList(process.env.SCREENSHOT_EXTRA_GUEST_ROUTES ?? '');
const EXTRA_AUTH_ROUTES = parseRouteList(process.env.SCREENSHOT_EXTRA_AUTH_ROUTES ?? '');

const DEFAULT_LAST_VISITED_ROOMS = [
  { slug: 'neo-zion-briefing', title: 'Zion Briefing', description: 'Matrix strategy sync for Zion crew.', owner: 'Thomas Anderson' },
  { slug: 'neo-nebuchadnezzar-ops', title: 'Nebuchadnezzar Ops', description: 'Mission room for ship operations and routing.', owner: 'Thomas Anderson' },
  { slug: 'neo-construct-training', title: 'Construct Training', description: 'Simulation drills and operator notes.', owner: 'Thomas Anderson' },
  { slug: 'neo-sentinel-watch', title: 'Sentinel Watch', description: 'Perimeter alerts and incident callouts.', owner: 'Thomas Anderson' },
  { slug: 'neo-oracle-corner', title: 'Oracle Corner', description: 'Predictions, edge-cases, and tough questions.', owner: 'Thomas Anderson' },
  { slug: 'neo-architect-debate', title: 'Architect Debate', description: 'Protocol decisions and system design tradeoffs.', owner: 'Thomas Anderson' },
  { slug: 'neo-red-pill-qa', title: 'Red Pill Q&A', description: 'Hard questions that need direct answers.', owner: 'Thomas Anderson' },
  { slug: 'neo-blue-pill-lobby', title: 'Blue Pill Lobby', description: 'Soft landing room for general updates.', owner: 'Thomas Anderson' },
  { slug: 'neo-matrix-reloaded-lab', title: 'Matrix Reloaded Lab', description: 'Experiments, regressions, and release checks.', owner: 'Thomas Anderson' },
];

const EXCLUDED_URIS = new Set([
  '_boost/browser-logs',
  'admin',
  'broadcasting/auth',
  'legal/privacy',
  'sitemap.xml',
  'up',
  'verify-email',
]);

const EXCLUDED_PREFIXES = [
  'storage/',
];

async function main() {
  const restoreHotFile = await disableViteHotFileIfNeeded();
  const timestamp = new Date().toISOString().replace(/[.:]/g, '-');
  const runDir = path.resolve(process.cwd(), OUTPUT_ROOT, timestamp);
  await fs.mkdir(runDir, { recursive: true });

  if (SEED_ROOM_DEMO && SEED_ROOM_SLUG) {
    seedRoomDemoData(SEED_ROOM_SLUG, SEED_DEMO_COUNT);
  }

  const routes = withExtraRoutes(loadStaticGetRoutes());
  const guestRoutes = routes.filter((route) => !route.requiresAuth);
  const authRoutes = routes.filter((route) => route.requiresAuth);

  const browser = await chromium.launch({ headless: true });
  const summary = {
    baseUrl: BASE_URL,
    timestamp,
    viewport: VIEWPORT,
    counts: {
      guestCandidates: guestRoutes.length,
      authCandidates: authRoutes.length,
      captured: 0,
      skipped: 0,
      failed: 0,
    },
    runs: [],
  };

  try {
    try {
      for (const theme of ['light', 'dark']) {
        const themeDir = path.join(runDir, theme);
        await fs.mkdir(themeDir, { recursive: true });

        const guestResult = await captureRouteGroup({
          browser,
          routes: guestRoutes,
          theme,
          mode: 'guest',
          outputDir: themeDir,
        });
        summary.runs.push(guestResult);
        summary.counts.captured += guestResult.captured.length;
        summary.counts.skipped += guestResult.skipped.length;
        summary.counts.failed += guestResult.failed.length;

        if (AUTH_EMAIL !== '' && AUTH_PASSWORD !== '') {
          const authResult = await captureRouteGroup({
            browser,
            routes: authRoutes,
            theme,
            mode: 'auth',
            outputDir: themeDir,
            credentials: {
              email: AUTH_EMAIL,
              password: AUTH_PASSWORD,
            },
          });
          summary.runs.push(authResult);
          summary.counts.captured += authResult.captured.length;
          summary.counts.skipped += authResult.skipped.length;
          summary.counts.failed += authResult.failed.length;
        } else {
          summary.runs.push({
            theme,
            mode: 'auth',
            captured: [],
            skipped: authRoutes.map((route) => ({
              route: route.uri,
              reason: 'Missing SCREENSHOT_AUTH_EMAIL or SCREENSHOT_AUTH_PASSWORD',
            })),
            failed: [],
          });
          summary.counts.skipped += authRoutes.length;
        }
      }
    } finally {
      await restoreHotFile();
    }
  } finally {
    await browser.close();
  }

  await fs.writeFile(
    path.join(runDir, 'manifest.json'),
    JSON.stringify(summary, null, 2),
    'utf8',
  );

  console.log('');
  console.log(`[ui:screenshots] output: ${runDir}`);
  console.log(
    `[ui:screenshots] captured=${summary.counts.captured} skipped=${summary.counts.skipped} failed=${summary.counts.failed}`,
  );
}

function loadStaticGetRoutes() {
  const raw = execFileSync('php', ['artisan', 'route:list', '--json'], {
    encoding: 'utf8',
  });
  const parsed = JSON.parse(raw);
  const byUri = new Map();

  for (const route of parsed) {
    const method = String(route.method ?? '');
    if (!method.includes('GET')) {
      continue;
    }

    const uri = normalizeUri(route.uri);
    if (uri.includes('{')) {
      continue;
    }
    if (shouldSkipUri(uri)) {
      continue;
    }

    const middleware = Array.isArray(route.middleware) ? route.middleware : [];
    const requiresAuth = middleware.includes('auth') || middleware.includes('verified');
    const name = route.name ? String(route.name) : null;
    const normalizedRoute = {
      uri: uri === '' ? '/' : `/${uri}`,
      name,
      requiresAuth,
    };

    const existing = byUri.get(normalizedRoute.uri);
    if (!existing) {
      byUri.set(normalizedRoute.uri, normalizedRoute);
      continue;
    }

    // If at least one route variant is guest-accessible, keep the page in guest list.
    existing.requiresAuth = existing.requiresAuth && normalizedRoute.requiresAuth;
    if (!existing.name && normalizedRoute.name) {
      existing.name = normalizedRoute.name;
    }
  }

  return Array.from(byUri.values()).sort((a, b) => a.uri.localeCompare(b.uri));
}

function withExtraRoutes(routes) {
  const routeKey = (uri, requiresAuth) => `${requiresAuth ? 'auth' : 'guest'}:${uri}`;
  const byRouteKey = new Map(
    routes.map((route) => [routeKey(route.uri, route.requiresAuth), { ...route }]),
  );
  const seedRoomPublicRoute = SEED_ROOM_SLUG ? `/r/${SEED_ROOM_SLUG}` : null;
  const seedGuestRoute = seedRoomPublicRoute ? [seedRoomPublicRoute] : [];
  const seedAuthRoutes = SEED_ROOM_SLUG
    ? [seedRoomPublicRoute]
    : [];

  const extraGuestRoutes = [...seedGuestRoute, ...EXTRA_GUEST_ROUTES];
  for (const uri of extraGuestRoutes) {
    const normalizedUri = normalizeUri(uri);
    if (normalizedUri === '' || shouldSkipUri(normalizedUri)) {
      continue;
    }
    const fullUri = `/${normalizedUri}`;
    const key = routeKey(fullUri, false);
    if (!byRouteKey.has(key)) {
      byRouteKey.set(key, {
        uri: fullUri,
        name: null,
        requiresAuth: false,
      });
    }
  }

  const extraAuthRoutes = [...seedAuthRoutes, ...EXTRA_AUTH_ROUTES];
  for (const uri of extraAuthRoutes) {
    const normalizedUri = normalizeUri(uri);
    if (normalizedUri === '' || shouldSkipUri(normalizedUri)) {
      continue;
    }
    const fullUri = `/${normalizedUri}`;
    const key = routeKey(fullUri, true);
    if (!byRouteKey.has(key)) {
      byRouteKey.set(key, {
        uri: fullUri,
        name: null,
        requiresAuth: true,
      });
    }
  }

  return Array.from(byRouteKey.values()).sort((a, b) => {
    const uriCompare = a.uri.localeCompare(b.uri);
    if (uriCompare !== 0) {
      return uriCompare;
    }
    return Number(a.requiresAuth) - Number(b.requiresAuth);
  });
}

function normalizeUri(value) {
  return String(value ?? '')
    .trim()
    .replace(/^\/+/, '')
    .replace(/\/+$/, '');
}

function shouldSkipUri(uri) {
  if (uri === '') {
    return false;
  }
  if (uri.includes('presentation')) {
    return true;
  }
  if (!INCLUDE_TEST_ROUTES && uri.startsWith('__test/')) {
    return true;
  }
  if (EXCLUDED_URIS.has(uri)) {
    return true;
  }
  return EXCLUDED_PREFIXES.some((prefix) => uri.startsWith(prefix));
}

async function captureRouteGroup({
  browser,
  routes,
  theme,
  mode,
  outputDir,
  credentials = null,
}) {
  const groupDir = path.join(outputDir, mode);
  await fs.mkdir(groupDir, { recursive: true });

  const context = await browser.newContext({ baseURL: BASE_URL, viewport: VIEWPORT });
  context.setDefaultTimeout(TIMEOUT_MS);
  await context.addInitScript((themeMode) => {
    localStorage.setItem('lc-theme', themeMode);
    document.documentElement.dataset.theme = themeMode;
  }, theme);
  if (DISABLE_ONBOARDING_MODALS) {
    await context.addInitScript(() => {
      try {
        localStorage.setItem('gr_welcome_seen', '1');
        localStorage.setItem('lc-tutorial-dismissed', '1');
        localStorage.setItem('lc-whats-new-version', '9999.9999.9999');
      } catch (error) {
        // ignore localStorage failures in restricted contexts
      }
    });
  }
  if (PRELOAD_LAST_VISITED_ROOMS) {
    await context.addInitScript((rooms) => {
      try {
        localStorage.setItem('gr:lastVisitedRooms', JSON.stringify(rooms));
      } catch (error) {
        // ignore localStorage failures in restricted contexts
      }
    }, DEFAULT_LAST_VISITED_ROOMS);
  }

  const captured = [];
  const skipped = [];
  const failed = [];

  try {
    if (mode === 'auth' && credentials) {
      await login(context, credentials.email, credentials.password);
    }

    const page = await context.newPage();
    page.setDefaultTimeout(TIMEOUT_MS);

    for (const route of routes) {
      const fileName = buildFileName(route);
      const screenshotPath = path.join(groupDir, `${fileName}.png`);

      try {
        const { response, attempts } = await gotoWithRetries(page, route.uri);
        await page.waitForTimeout(WAIT_MS);

        if (!response) {
          skipped.push({
            route: route.uri,
            reason: 'No HTTP response',
          });
          continue;
        }

        const headers = response.headers();
        const contentType = String(headers['content-type'] ?? '');
        const status = response.status();
        if (status >= 400) {
          failed.push({
            route: route.uri,
            status,
            attempts,
            finalUrl: page.url(),
            error: `HTTP ${status}`,
          });
          continue;
        }
        if (!contentType.includes('text/html')) {
          skipped.push({
            route: route.uri,
            reason: `Non-HTML response (${contentType || 'unknown'})`,
          });
          continue;
        }

        await page.screenshot({
          path: screenshotPath,
          fullPage: true,
        });

        captured.push({
          route: route.uri,
          file: path.relative(path.dirname(outputDir), screenshotPath).replace(/\\/g, '/'),
          status,
          attempts,
          finalUrl: page.url(),
        });
      } catch (error) {
        failed.push({
          route: route.uri,
          error: error instanceof Error ? error.message : String(error),
        });
      }
    }
  } finally {
    await context.close();
  }

  return {
    theme,
    mode,
    captured,
    skipped,
    failed,
  };
}

async function login(context, email, password) {
  const page = await context.newPage();
  page.setDefaultTimeout(TIMEOUT_MS);

  await page.goto(toNavigableRoute('/login'), { waitUntil: 'domcontentloaded' });

  const emailInput = page.locator('input[name="email"]');
  if ((await emailInput.count()) === 0) {
    throw new Error('Login form was not found at /login');
  }

  await emailInput.fill(email);
  await page.locator('input[name="password"]').fill(password);

  const startedAtInput = page.locator('input[name="form_started_at"]');
  if ((await startedAtInput.count()) > 0) {
    const startedAt = Math.floor(Date.now() / 1000) - 5;
    await startedAtInput.first().evaluate((element, value) => {
      element.value = value;
    }, String(startedAt));
  }

  const honeypotInput = page.locator('input[name="website"]');
  if ((await honeypotInput.count()) > 0) {
    await honeypotInput.first().evaluate((element) => {
      element.value = '';
    });
  }

  const submitButton = page.locator('form button[type="submit"], form input[type="submit"]').first();
  await Promise.all([
    submitButton.click(),
    page.waitForLoadState('domcontentloaded'),
  ]);

  if (new URL(page.url()).pathname.endsWith('/login')) {
    throw new Error('Login failed: still on /login after submit');
  }

  await page.close();
}

function buildFileName(route) {
  if (route.name) {
    return sanitizeFileName(route.name);
  }
  if (route.uri === '/') {
    return 'home';
  }
  return sanitizeFileName(route.uri.replace(/^\//, '').replace(/\//g, '__'));
}

function sanitizeFileName(value) {
  return value.replace(/[^a-zA-Z0-9_.-]/g, '_');
}

function toNavigableRoute(uri) {
  if (uri === '/' || uri === '') {
    return '';
  }
  return String(uri).replace(/^\/+/, '');
}

function parseViewport(raw) {
  const match = /^(\d{3,5})x(\d{3,5})$/i.exec(raw.trim());
  if (!match) {
    return { width: 1920, height: 1080 };
  }
  return {
    width: Number.parseInt(match[1], 10),
    height: Number.parseInt(match[2], 10),
  };
}

function parsePositiveInt(value, fallback) {
  const parsed = Number.parseInt(String(value ?? ''), 10);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    return fallback;
  }
  return parsed;
}

function parseRouteList(value) {
  const raw = String(value ?? '').trim();
  if (raw === '') {
    return [];
  }
  return raw
    .split(',')
    .map((item) => normalizeUri(item))
    .filter((item) => item !== '')
    .map((item) => `/${item}`);
}

async function gotoWithRetries(page, routeUri) {
  const target = toNavigableRoute(routeUri);
  let response = null;
  let attempts = 0;

  for (let attempt = 1; attempt <= NAV_RETRIES; attempt += 1) {
    attempts = attempt;
    response = await page.goto(target, { waitUntil: 'domcontentloaded' });
    if (!response) {
      break;
    }
    const status = response.status();
    if (status < 500) {
      break;
    }
    if (attempt < NAV_RETRIES) {
      await page.waitForTimeout(600 * attempt);
    }
  }

  return { response, attempts };
}

function seedRoomDemoData(roomSlug, count) {
  try {
    execFileSync(
      'php',
      ['artisan', 'chat:seed-demo', roomSlug, `--count=${count}`, '--delay=0'],
      { encoding: 'utf8' },
    );
    console.log(`[ui:screenshots] seeded room ${roomSlug} with demo data (count=${count})`);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.warn(`[ui:screenshots] warning: failed to seed room ${roomSlug}: ${message}`);
  }
}

async function disableViteHotFileIfNeeded() {
  if (USE_HMR) {
    return async () => {};
  }

  const hotPath = path.resolve(process.cwd(), 'public', 'hot');
  const tempHotPath = path.resolve(process.cwd(), 'public', 'hot.screenshots-disabled');

  try {
    await fs.access(hotPath);
  } catch {
    return async () => {};
  }

  try {
    await fs.rm(tempHotPath, { force: true });
  } catch {
    // ignore remove failures; rename below will throw if needed
  }

  await fs.rename(hotPath, tempHotPath);
  console.log('[ui:screenshots] temporarily disabled public/hot to use built assets');

  return async () => {
    try {
      await fs.rename(tempHotPath, hotPath);
      console.log('[ui:screenshots] restored public/hot');
    } catch {
      // ignore restore failures (e.g., if file already restored manually)
    }
  };
}

function normalizeBaseUrl(url) {
  const trimmed = String(url ?? '').trim();
  if (trimmed === '') {
    return 'http://127.0.0.1:8000/';
  }
  return trimmed.endsWith('/') ? trimmed : `${trimmed}/`;
}

main().catch((error) => {
  console.error('[ui:screenshots] failed:', error instanceof Error ? error.message : error);
  process.exitCode = 1;
});
