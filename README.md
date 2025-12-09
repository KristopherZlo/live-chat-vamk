<p align="center">
  <img src="public/assets/ghostup_logo_white.svg" alt="Ghost Room" width="260">
</p>

# Ghost Room
Anonymous Q&A chat for lectures: rooms for students and teachers with questions, moderation, and fast messaging.

## Server requirements
- PHP 8.2+ with extensions: `pdo_mysql`/`pdo_pgsql` (example uses MariaDB/MySQL), `mbstring`, `openssl`, `json`, `ctype`, `fileinfo`
- Composer 2.x
- Node.js 18+ and npm
- MariaDB/MySQL 10.5+ (or compatible)
- Redis optional (cache/session/queue use DB by default)

## Quick start (local)
```bash
git clone https://github.com/KristopherZlo/live-chat-vamk.git
cd live-chat-vamk
cp .env.example .env
composer install
npm install
php artisan key:generate
# create DB and set DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD in .env
php artisan migrate
php artisan serve   # opens http://localhost:8000
npm run dev         # assets + HMR
```
If using queues (`QUEUE_CONNECTION=database`), run a worker in another terminal: `php artisan queue:work`.

## Build and deploy to your server
```bash
git clone https://github.com/KristopherZlo/live-chat-vamk.git
cd live-chat-vamk
cp .env.example .env
# set APP_ENV=production, APP_DEBUG=false, APP_URL=https://your-domain
# configure DB credentials
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
npm install
npm run build
```
- Ensure `storage/` and `bootstrap/cache/` are writable by the web server user.
- Point your web server document root to `public/`.
- For background jobs under supervisor/systemd keep `php artisan queue:work` running (if queues are used).

## Useful commands
- Run tests: `php artisan test`
- Clear config cache: `php artisan config:clear`
- Rebuild frontend: `npm run build`

### Dev/test-only commands
- Seed a room with live-broadcast demo chat data (messages, replies, questions, deletions, reactions): `php artisan chat:seed-demo <room-id-or-slug> [--count=20]` (works in testing/local/dev only).
- Generate invite codes for registration: `php artisan invite:generate {count=1} [--length=12]`
