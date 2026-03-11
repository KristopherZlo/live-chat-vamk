import { execFileSync } from 'node:child_process';
import { promises as fs } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const BASE_URL = process.env.SCREENSHOT_BASE_URL ?? 'http://127.0.0.1:8000';
const OUTPUT_ROOT = process.env.SCREENSHOT_OUTPUT_DIR ?? 'interface-screenshots-auto';
const VIEWPORT = parseViewport(process.env.SCREENSHOT_VIEWPORT ?? '1440x900');
const WAIT_MS = parsePositiveInt(process.env.SCREENSHOT_WAIT_MS, 450);
const TIMEOUT_MS = parsePositiveInt(process.env.SCREENSHOT_TIMEOUT_MS, 30000);
const INCLUDE_TEST_ROUTES = process.env.SCREENSHOT_INCLUDE_TEST_ROUTES === '1';
const AUTH_EMAIL = process.env.SCREENSHOT_AUTH_EMAIL ?? '';
const AUTH_PASSWORD = process.env.SCREENSHOT_AUTH_PASSWORD ?? '';

const EXCLUDED_URIS = new Set([
  '_boost/browser-logs',
  'sitemap.xml',
  'up',
]);

const EXCLUDED_PREFIXES = [
  'storage/',
];

async function main() {
  const timestamp = new Date().toISOString().replace(/[.:]/g, '-');
  const runDir = path.resolve(process.cwd(), OUTPUT_ROOT, timestamp);
  await fs.mkdir(runDir, { recursive: true });

  const routes = loadStaticGetRoutes();
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
        const response = await page.goto(route.uri, { waitUntil: 'domcontentloaded' });
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
          status: response.status(),
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

  await page.goto('/login', { waitUntil: 'domcontentloaded' });

  const emailInput = page.locator('input[name="email"]');
  if ((await emailInput.count()) === 0) {
    throw new Error('Login form was not found at /login');
  }

  await emailInput.fill(email);
  await page.locator('input[name="password"]').fill(password);

  const startedAtInput = page.locator('input[name="form_started_at"]');
  if ((await startedAtInput.count()) > 0) {
    const startedAt = Math.floor(Date.now() / 1000) - 5;
    await startedAtInput.fill(String(startedAt));
  }

  const honeypotInput = page.locator('input[name="website"]');
  if ((await honeypotInput.count()) > 0) {
    await honeypotInput.fill('');
  }

  const submitButton = page.locator('form button[type="submit"], form input[type="submit"]').first();
  await Promise.all([
    submitButton.click(),
    page.waitForLoadState('domcontentloaded'),
  ]);

  if (new URL(page.url()).pathname === '/login') {
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

function parseViewport(raw) {
  const match = /^(\d{3,5})x(\d{3,5})$/i.exec(raw.trim());
  if (!match) {
    return { width: 1440, height: 900 };
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

main().catch((error) => {
  console.error('[ui:screenshots] failed:', error instanceof Error ? error.message : error);
  process.exitCode = 1;
});
