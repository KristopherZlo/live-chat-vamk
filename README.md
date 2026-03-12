<p align="center">
  <img src="resources/images/github-page-header.png" alt="Ghost Room" width="100%">
</p>

# Ghost Room

**Anonymous Q&A rooms for lectures and live sessions**

Ghost Room is a web application for university lectures, seminars, and live events that lets students ask questions anonymously while giving lecturers a structured way to manage them.

---

## Demo / Production

- Production: https://ghostroom.fi
- Repository: https://github.com/KristopherZlo/live-chat-vamk
  *(source code is publicly visible, but the license is not open-source)*

---

## Project status

**BETA**

---

## Screenshots

Latest automated capture: `interface-screenshots-auto/2026-03-12T01-01-43-604Z`

<details>
<summary>Light Theme</summary>

### Guest

`home.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/guest/home.png" alt="Light guest home" width="960">

`login.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/guest/login.png" alt="Light guest login" width="960">

`r__neo-zion-briefing.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/guest/r__neo-zion-briefing.png" alt="Light guest room" width="960">

`register.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/guest/register.png" alt="Light guest register" width="960">

`rooms.join.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/guest/rooms.join.png" alt="Light guest join" width="960">

`updates.index.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/guest/updates.index.png" alt="Light guest updates" width="960">

### Auth

`dashboard.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/auth/dashboard.png" alt="Light auth dashboard" width="960">

`password.confirm.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/auth/password.confirm.png" alt="Light auth password confirm" width="960">

`profile.edit.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/auth/profile.edit.png" alt="Light auth profile edit" width="960">

`r__neo-zion-briefing.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/auth/r__neo-zion-briefing.png" alt="Light auth room" width="960">

`rooms.create.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/light/auth/rooms.create.png" alt="Light auth room create" width="960">

</details>

<details>
<summary>Dark Theme</summary>

### Guest

`home.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/guest/home.png" alt="Dark guest home" width="960">

`login.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/guest/login.png" alt="Dark guest login" width="960">

`r__neo-zion-briefing.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/guest/r__neo-zion-briefing.png" alt="Dark guest room" width="960">

`register.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/guest/register.png" alt="Dark guest register" width="960">

`rooms.join.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/guest/rooms.join.png" alt="Dark guest join" width="960">

`updates.index.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/guest/updates.index.png" alt="Dark guest updates" width="960">

### Auth

`dashboard.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/auth/dashboard.png" alt="Dark auth dashboard" width="960">

`password.confirm.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/auth/password.confirm.png" alt="Dark auth password confirm" width="960">

`profile.edit.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/auth/profile.edit.png" alt="Dark auth profile edit" width="960">

`r__neo-zion-briefing.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/auth/r__neo-zion-briefing.png" alt="Dark auth room" width="960">

`rooms.create.png`  
<img src="interface-screenshots-auto/2026-03-12T01-01-43-604Z/dark/auth/rooms.create.png" alt="Dark auth room create" width="960">

</details>

---

## How it works (high level)

1. **Host (lecturer)** creates a room and shares a public link or QR code.
2. **Participants (students)** join the room and post chat messages or mark messages as questions.
3. Questions appear in a **private queue** visible only to the host.
4. The host reviews questions, changes their status, and responds when appropriate.

---

## Features

### Host (lecturer)

* Private question queue with statuses: `new`, `answered`, `ignored`, `later`
* Sound notification on new questions
* Picture-in-Picture question queue
* Moderation tools (remove questions, ban participants)

### Participant (student)

* Anonymous participation (chat + questions)
* Reactions and threaded replies
* Personal "My Questions" panel
* Delete own questions and rate answers

### Real-time + scale

* Live updates via WebSockets (polling fallback if unavailable)
* Optimistic UI updates for smooth interaction
* Designed for academic audiences (~150–250 participants); messages/questions are loaded in chunks
* Browser-only usage (no mobile apps required)

---

## Security & access control

* Invite-only registration
* CSRF / XSRF protection
* Throttling for registration, login, and messaging
* Ban enforcement using IP and fingerprint
* Strict permission checks for all moderation actions

---

## Tech stack

### Backend

* Laravel 12
* Blade templates
* REST APIs + Events
* Laravel Echo (WebSockets)

### Frontend

* Vite
* Tailwind CSS
* Vanilla JavaScript + TypeScript
* PostCSS

### Storage & infrastructure

* MySQL / MariaDB
* Redis (optional)
* Queue workers
* Cookies and sessions

---

## Server requirements

* PHP 8.2+ with extensions:
  * `pdo_mysql` / `pdo_pgsql`
  * `mbstring`
  * `openssl`
  * `json`
  * `ctype`
  * `fileinfo`
* Composer 2.x
* Node.js 18+ and npm
* MariaDB / MySQL 10.5+ (or compatible)
* Redis (optional — DB is used by default for cache, sessions, and queues)

---

## Quick start (local)

```bash
git clone https://github.com/KristopherZlo/live-chat-vamk.git
cd live-chat-vamk

cp .env.example .env
composer install
npm install

php artisan key:generate
# create database and set DB_* variables in .env
php artisan migrate

php artisan serve    # http://localhost:8000
npm run dev          # assets + HMR
```

If using queues (`QUEUE_CONNECTION=database`), run a worker in another terminal:

```bash
php artisan queue:work
```

---

## Build and deploy to your server

```bash
git clone https://github.com/KristopherZlo/live-chat-vamk.git
cd live-chat-vamk

cp .env.example .env
# set APP_ENV=production
# set APP_DEBUG=false
# set APP_URL=https://your-domain
# configure database credentials

composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force

npm install
npm run build
```

Deployment notes:

* Ensure `storage/` and `bootstrap/cache/` are writable by the web server.
* Point the web server document root to `public/`.
* Keep `php artisan queue:work` running under supervisor or systemd if queues are used.

---

## Useful commands

* Run tests: `php artisan test`
* Clear config cache: `php artisan config:clear`
* Rebuild frontend assets: `npm run build`

### Automated UI screenshots

Use Playwright to capture all static `GET` pages from `php artisan route:list --json` in both light and dark themes.

1. Install browser binaries once:
   `npm run ui:screenshots:install`
2. Start the app locally (for example in another terminal):
   `php artisan serve --host=127.0.0.1 --port=8000`
3. Optional for authenticated pages:
   set `SCREENSHOT_AUTH_EMAIL` and `SCREENSHOT_AUTH_PASSWORD`
4. Run capture:
   `npm run ui:screenshots`

Useful env vars:

* `SCREENSHOT_BASE_URL` (default `http://127.0.0.1:8000`)
* `SCREENSHOT_OUTPUT_DIR` (default `interface-screenshots-auto`)
* `SCREENSHOT_VIEWPORT` (default `1920x1080`)
* `SCREENSHOT_WAIT_MS` (default `450`)
* `SCREENSHOT_TIMEOUT_MS` (default `30000`)
* `SCREENSHOT_NAV_RETRIES` (default `3`, retries navigation on `5xx` responses)
* `SCREENSHOT_INCLUDE_TEST_ROUTES=1` (include `__test/*` routes)
* `SCREENSHOT_DISABLE_ONBOARDING_MODALS=0` (by default welcome/what's-new/tutorial modals are suppressed)
* `SCREENSHOT_PRELOAD_LAST_VISITED_ROOMS=0` (by default preloads 9 demo "Last visited" rooms for join page screenshots)
* `SCREENSHOT_SEED_ROOM_DEMO=0` (by default runs `chat:seed-demo` before capture)
* `SCREENSHOT_SEED_ROOM_SLUG` (default `neo-zion-briefing`)
* `SCREENSHOT_SEED_DEMO_COUNT` (default `90`)
* `SCREENSHOT_EXTRA_GUEST_ROUTES` (comma-separated, e.g. `/r/custom-room`)
* `SCREENSHOT_EXTRA_AUTH_ROUTES` (comma-separated, e.g. `/dashboard,/profile`)
* `SCREENSHOT_USE_HMR=1` (by default the script temporarily disables `public/hot` so screenshots use built assets)

By default, routes containing `presentation`, plus `/admin`, `/broadcasting/auth`, `/legal/privacy`, and `/verify-email`, are skipped.
The seeded public room route (`/r/<slug>`) is captured in both guest and auth modes.
Each run writes screenshots into a timestamped folder and a `manifest.json` with captured/skipped/failed pages.

### Dev / test only

* Seed a room with demo chat data (messages, replies, questions, reactions):
  `php artisan chat:seed-demo <room-id-or-slug> [--count=200] [--delay=0]`
  `php artisan chat:seed-questions <room-id-or-slug> [--count=50]`
  `php artisan chat:seed-messages <room-id-or-slug> [--count=200]`
  `php artisan chat:seed-poll <room-id-or-slug> [--options=] [--votes=] [--replies=] [--reactions=] [--participants=]`
* Stream continuous demo activity (messages, replies, questions, reactions, poll votes):
  `php artisan chat:stream-demo <room-id-or-slug> [--delay=1-3] [--participants=8]`
* Simulate a live question stream (with a delay between questions):
  `php artisan chat:seed-questions <room-id-or-slug> [--count=50] [--delay=1]`
* Generate invite codes for registration:
  `php artisan invite:generate {count=1} [--length=12]`

---

## TODO

* [x] Polls and live voting
* [ ] Further loading and performance optimizations
* [ ] Interactive onboarding tutorial for hosts and students
* [ ] Email verification

---

## License

The source code is publicly available for review and learning purposes.
The project is **not open-source**, and reuse or redistribution is restricted.

---

## Author

Created and maintained by **KristopherZlo**.
Originally developed for university use.
