import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { promises as fs } from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const BASE_URL = normalizeBaseUrl(process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000');
const ROOM_SLUG = String(process.env.SMOKE_ROOM_SLUG ?? 'smoke-room').trim();
const TIMEOUT_MS = parsePositiveInt(process.env.SMOKE_TIMEOUT_MS, 30000);
const POLL_WAIT_MS = parsePositiveInt(process.env.SMOKE_POLL_WAIT_MS, 7500);
const USE_HMR = process.env.SMOKE_USE_HMR === '1';

async function main() {
  const restoreHotFile = await disableViteHotFileIfNeeded();
  const fixture = seedRoomFixture();
  const browser = await chromium.launch({ headless: true });

  try {
    await runGuestSmoke(browser, fixture);
    await runHostSmoke(browser, fixture);
  } finally {
    await browser.close();
    await restoreHotFile();
  }

  console.log('[ui:smoke] room smoke checks passed');
}

async function runGuestSmoke(browser, fixture) {
  const context = await createContext(browser);
  const page = await context.newPage();
  page.setDefaultTimeout(TIMEOUT_MS);
  const diagnostics = attachDiagnostics(page);

  try {
    await page.goto(toNavigableRoute(fixture.room.path), { waitUntil: 'domcontentloaded' });
    await page.locator('#chat-form').waitFor();
    const roomPageConfig = await readRoomPageConfig(page);
    const guestParticipantId = Number.parseInt(String(roomPageConfig?.currentParticipantId ?? ''), 10);
    assert.equal(Number.isFinite(guestParticipantId) && guestParticipantId > 0, true, 'Guest participant id is missing from room page config');

    await page.locator('[data-chat-tab="replies"]').click();
    await expectVisible(page, '[data-chat-panel="replies"]');

    await page.locator('[data-chat-tab="chat"]').click();
    await expectVisible(page, '[data-chat-panel="chat"]');

    await page.locator('#chatEmojiToggle').click();
    await expectVisible(page, 'emoji-picker#chatEmojiPicker');
    assert.equal(diagnostics.cdnRequests.length, 0, `Unexpected CDN requests: ${diagnostics.cdnRequests.join(', ')}`);

    const reactionResponse = page.waitForResponse((response) =>
      response.request().method() === 'POST' && response.url().includes('/reactions'),
    );
    await page.locator('[data-reaction-chip]').first().click();
    assertSuccessful(await reactionResponse, 'Reaction toggle failed');
    await expectClass(page, '[data-reaction-chip]', 'is-active');

    const pollResponse = page.waitForResponse((response) =>
      response.request().method() === 'POST' && response.url().includes('/polls/') && response.url().includes('/vote'),
    );
    await page.locator('[data-poll-option-id]:not([disabled])').first().click();
    assertSuccessful(await pollResponse, 'Poll vote failed');
    await expectPressed(page, '[data-poll-option-id]');

    const messageText = `Smoke message ${Date.now()}`;
    await page.locator('#chatInput').fill(messageText);
    const messageResponse = page.waitForResponse((response) =>
      response.request().method() === 'POST' && /\/rooms\/[^/]+\/messages$/.test(new URL(response.url()).pathname),
    );
    await page.locator('#sendButton').click();
    assertSuccessful(await messageResponse, 'Message send failed');
    await page.locator('.message .message-text').filter({ hasText: messageText }).first().waitFor();
    assertAtRoute(page.url(), fixture.room.path, 'Message send triggered a full page reload');

    const myQuestionsPolling = await page.waitForResponse((response) =>
      response.request().method() === 'GET' && response.url().includes('/my-questions-panel'),
      { timeout: POLL_WAIT_MS },
    );
    assert.equal(myQuestionsPolling.status(), 200, 'My questions polling did not succeed');

    banGuestParticipant(fixture.room.slug, guestParticipantId);
    await page.locator('[data-reaction-chip]').first().click();
    await page.locator('text=Access denied').waitFor({ timeout: POLL_WAIT_MS });

    assertDiagnosticsClean(diagnostics, 'guest room smoke');
  } finally {
    await context.close();
  }
}

async function runHostSmoke(browser, fixture) {
  const context = await createContext(browser);
  const loginPage = await context.newPage();
  loginPage.setDefaultTimeout(TIMEOUT_MS);

  try {
    await login(loginPage, fixture.host.email, fixture.host.password);

    const page = await context.newPage();
    page.setDefaultTimeout(TIMEOUT_MS);
    const diagnostics = attachDiagnostics(page);
    await page.goto(toNavigableRoute(fixture.room.path), { waitUntil: 'domcontentloaded' });

    await expectVisible(page, '[data-chat-tab="bans"]');
    await expectVisible(page, '#questions-panel');
    await expectVisible(page, '[data-poll-toggle]');
    await expectVisible(page, '.queue-item[data-question-id]');
    assert.equal(diagnostics.cdnRequests.length, 0, `Unexpected CDN requests: ${diagnostics.cdnRequests.join(', ')}`);

    assertDiagnosticsClean(diagnostics, 'host room smoke');
  } finally {
    await context.close();
  }
}

async function createContext(browser) {
  const context = await browser.newContext({
    baseURL: BASE_URL,
    viewport: { width: 1440, height: 1000 },
  });

  await context.addInitScript(() => {
    try {
      localStorage.setItem('gr_welcome_seen', '1');
      localStorage.setItem('lc-tutorial-dismissed', '1');
      localStorage.setItem('lc-whats-new-version', '9999.9999.9999');
      localStorage.setItem('lc-theme', 'light');
      document.documentElement.dataset.theme = 'light';
    } catch (error) {
      // Ignore localStorage failures in restricted contexts.
    }
  });

  return context;
}

function seedRoomFixture() {
  const raw = execFileSync(
    'php',
    ['artisan', 'smoke:seed-room', `--slug=${ROOM_SLUG}`, '--json'],
    { encoding: 'utf8' },
  );

  return parseLastJsonLine(raw);
}

function banGuestParticipant(roomSlug, participantId) {
  execFileSync(
    'php',
    ['artisan', 'smoke:ban-room-participant', roomSlug, String(participantId)],
    { encoding: 'utf8' },
  );
}

async function login(page, email, password) {
  await page.goto('login', { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);

  const startedAtInput = page.locator('input[name="form_started_at"]');
  if (await startedAtInput.count()) {
    await startedAtInput.first().evaluate((element, value) => {
      element.value = value;
    }, String(Math.floor(Date.now() / 1000) - 5));
  }

  const honeypotInput = page.locator('input[name="website"]');
  if (await honeypotInput.count()) {
    await honeypotInput.first().evaluate((element) => {
      element.value = '';
    });
  }

  await Promise.all([
    page.locator('form button[type="submit"], form input[type="submit"]').first().click(),
    page.waitForLoadState('domcontentloaded'),
  ]);

  assert.notEqual(new URL(page.url()).pathname, '/login', 'Smoke host login failed');
}

function attachDiagnostics(page) {
  const consoleErrors = [];
  const pageErrors = [];
  const cdnRequests = [];

  page.on('console', (message) => {
    if (message.type() === 'error' && !isIgnorableConsoleError(message.text())) {
      consoleErrors.push(message.text());
    }
  });
  page.on('pageerror', (error) => {
    pageErrors.push(error.message);
  });
  page.on('request', (request) => {
    if (request.url().includes('cdn.jsdelivr.net')) {
      cdnRequests.push(request.url());
    }
  });

  return {
    consoleErrors,
    pageErrors,
    cdnRequests,
  };
}

function isIgnorableConsoleError(message) {
  return /^Failed to load resource: the server responded with a status of (403|404)/.test(message);
}

function assertDiagnosticsClean(diagnostics, label) {
  assert.equal(
    diagnostics.pageErrors.length,
    0,
    `${label} page errors: ${diagnostics.pageErrors.join(' | ')}`,
  );
  assert.equal(
    diagnostics.consoleErrors.length,
    0,
    `${label} console errors: ${diagnostics.consoleErrors.join(' | ')}`,
  );
}

function assertSuccessful(response, message) {
  const status = response.status();
  assert.equal(status >= 200 && status < 300, true, `${message} (status ${status})`);
}


function assertAtRoute(currentUrl, expectedRoutePath, message) {
  const pathname = new URL(currentUrl).pathname;
  assert.equal(pathname.endsWith(expectedRoutePath), true, `${message}\nactual path: ${pathname}\nexpected suffix: ${expectedRoutePath}`);
}

async function expectVisible(page, selector) {
  await page.locator(selector).waitFor();
  assert.equal(await page.locator(selector).first().isVisible(), true, `Expected ${selector} to be visible`);
}

async function expectClass(page, selector, className) {
  await page.waitForFunction(
    ([targetSelector, targetClass]) => {
      const element = document.querySelector(targetSelector);
      return Boolean(element && element.classList.contains(targetClass));
    },
    [selector, className],
  );
}

async function expectPressed(page, selector) {
  await page.waitForFunction((targetSelector) => {
    const element = document.querySelector(targetSelector);
    return Boolean(element && element.getAttribute('aria-pressed') === 'true');
  }, selector);
}

async function readRoomPageConfig(page) {
  return page.locator('#roomPageConfig').evaluate((element) => {
    const rawConfig = element.getAttribute('data-room-page-config');
    if (!rawConfig) {
      return null;
    }

    return JSON.parse(rawConfig);
  });
}

function parseLastJsonLine(raw) {
  const line = String(raw)
    .trim()
    .split(/\r?\n/)
    .map((item) => item.trim())
    .filter(Boolean)
    .reverse()
    .find((item) => item.startsWith('{'));

  if (!line) {
    throw new Error('Smoke fixture command did not return JSON');
  }

  return JSON.parse(line);
}

function toNavigableRoute(uri) {
  if (uri === '/' || uri === '') {
    return '';
  }

  return String(uri).replace(/^\/+/, '');
}

function parsePositiveInt(value, fallback) {
  const parsed = Number.parseInt(String(value ?? ''), 10);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    return fallback;
  }

  return parsed;
}

async function disableViteHotFileIfNeeded() {
  if (USE_HMR) {
    return async () => {};
  }

  const hotPath = path.resolve(process.cwd(), 'public', 'hot');
  const tempHotPath = path.resolve(process.cwd(), 'public', 'hot.smoke-disabled');

  try {
    await fs.access(hotPath);
  } catch {
    return async () => {};
  }

  try {
    await fs.rm(tempHotPath, { force: true });
  } catch {
    // Ignore stale temp files.
  }

  await fs.rename(hotPath, tempHotPath);

  return async () => {
    try {
      await fs.rename(tempHotPath, hotPath);
    } catch {
      // Ignore restore failures.
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
  console.error('[ui:smoke] failed:', error instanceof Error ? error.message : String(error));
  process.exitCode = 1;
});
