# E-Itinerary

A shared Japan trip planner for a small group: day-by-day itinerary, personal shopping lists with budgets, Yen to Ringgit rates, tax refund receipts and a shopping overview. Works offline on a phone (PWA) and syncs when back online.

- **Stack:** Laravel 13 (PHP 8.4), Blade + vanilla JS + Vite, Sanctum (web session + mobile tokens), Supabase Postgres.
- **Design:** `docs/reference/e-itinerary-design.html` is the source of truth for layout, copy and colour tokens.
- **Roles:** owner (admin), editor, viewer. Shopping, budgets and receipts are personal; the owner can see everyone's.

## Local setup (Laravel Herd)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

1. Fill in the Supabase connection in `.env` (Supabase → **Connect** → *Session pooler*): `DB_HOST`, `DB_USERNAME` (`postgres.<project-ref>`), `DB_PASSWORD`. Keep `DB_SSLMODE=require`.
2. Create the tables and load the trip:

   ```bash
   php artisan migrate
   php artisan db:seed            # the Japan trip: 14 days, stops, shopping list, ideas
   ```

3. Register an account at `/register`, then make yourself the trip owner:

   ```bash
   php artisan trip:owner you@example.com
   ```

4. Run Vite while you work: `npm run dev` (or `composer run dev`). Herd serves the site at **https://my-itenary.test**.

Laravel is the only database client: no Supabase JS client, Supabase Auth or Row Level Security. All access rules live in `app/Policies/TripPolicy.php`.

## Tests

```bash
php artisan test --compact
vendor/bin/pint --dirty
```

Tests run on in-memory SQLite (see `phpunit.xml`) and never touch Supabase.

## Environment

| Key | What it is |
|---|---|
| `APP_URL` | Full https address of the site |
| `DB_*` | Supabase Postgres (session pooler), `DB_SSLMODE=require` |
| `SESSION_SECURE_COOKIE` | `true` in production |
| `SANCTUM_STATEFUL_DOMAINS` | Your domain, if it is not the `APP_URL` host |
| `BNM_API_URL`, `FRANKFURTER_API_URL`, `EXCHANGE_RATE_CACHE_MINUTES` | Exchange rate sources (no keys needed) |
| `TAX_REFUND_MIN_PRE_TAX_YEN`, `TAX_REFUND_DAYS_TO_EXPORT` | Tax refund rules (default ¥5,000 and 90 days) |
| `RECEIPTS_DISK` | `receipts` (private folder) locally; `s3` in production |
| `AWS_*` | Supabase Storage S3 keys for a **private** `receipts` bucket |

Never commit `.env`.

## Deploying

The app needs a server that runs **PHP 8.4**: it has a database, logins and an API, so it cannot be hosted as a static site (for example Cloudflare Pages alone). Any PHP host works, such as Laravel Cloud, a VPS with Laravel Forge, or a container host (Render, Fly.io, Railway). Cloudflare can sit in front of it for the domain, HTTPS and caching.

1. **Environment:** copy `.env.example`, then set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, `SESSION_SECURE_COOKIE=true`, the `DB_*` values and a new `APP_KEY`.
2. **Receipt photos:** in Supabase → Storage, create a **private** bucket called `receipts` and an S3 access key. Set `RECEIPTS_DISK=s3` and the `AWS_*` values. Photos are only ever streamed through the app to their owner and the trip owner.
3. **Build and migrate** on each deploy:

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```

4. **HTTPS behind a proxy:** the app trusts `X-Forwarded-*` headers from any proxy (`bootstrap/app.php`) and forces https links in production (`AppServiceProvider`). Only expose PHP through the proxy or host, never directly.
5. **Cloudflare in front:** point your domain's DNS at the host with the orange-cloud proxy on, and set SSL/TLS mode to **Full (strict)**. Don't cache `/api/*`, `/login`, `/register` or `/trips/*` (they are personal); the service worker already caches the app shell and `/build/*` assets on the phone.
6. **Health check:** `GET /up`.

## Offline and the phone app

- The trip is cached in `localStorage`, changes made offline are queued and sent when the connection returns, and receipt photos wait in IndexedDB until they upload (`resources/js/trip/sync.js`, `photo-queue.js`).
- `public/sw.js` is the service worker; bump its cache version when the app shell changes.

### NativePHP Mobile (later)

The API is ready for a native wrapper:

- **Login:** `POST /api/auth/token` returns a Sanctum bearer token; send it as `Authorization: Bearer …` on every `/api` call. Log out with `DELETE /api/auth/token`.
- **Same rules:** every endpoint applies the same policies as the web app. The receipt and shopping overview rules also exist on the server (`app/Services/`), so a native screen can use the API instead of the JavaScript copies.
- **Camera:** receipt photos are uploaded with `POST /api/receipts/{id}/photos` (multipart, part 1 or 2). A native app can use the device camera and on-device text recognition, then fill the same fields; `App\Services\TaxRefund\ReceiptReader` is the hook for automatic reading.
- **Network:** point the app at the production `APP_URL` over https.
