# Edu World Bahrain

Database-driven Laravel 13 website and administration system for Edu World. The site uses the official deep navy `#0A2A66` and golden yellow `#FDB913`, including extracted brochure logos and photography.

## What is implemented

- Responsive home, detailed services, contact, news/gallery, application, payment and editable legal pages.
- Seven application paths with conditional fields, consent timestamps, annual collision-safe `EW-year-number` references, private documents and acknowledgements.
- Role-protected administration: applications, status history, enquiries, payment records, CMS, categories, tags, SEO, settings, team accounts and audit history.
- Published/scheduled/draft news, optimized image galleries, MP4 uploads and restricted YouTube embeds. Draft media requires editorial authorization.
- Exact three-decimal BHD records, signed receipts, explicit offline payments, a gateway interface and verified/idempotent callback handling.
- Queued branded email, optional Turnstile, rate limits, security headers, sitemap and structured data.

Online checkout is disabled until the approved provider's adapter, credentials and checkout hosts are configured. Pending requests never appear as successful payments. `MAIL_MAILER=log` stores email output locally; configure SMTP and a queue worker for delivery. The five seeded legal pages are marked for business review in the CMS.

## Requirements

- PHP **8.4.1 or newer** compatible with the lockfile; Composer 2.
- MySQL/MariaDB with InnoDB and `utf8mb4` for production. SQLite is supported for local development and tests.
- PHP extensions: cURL, Ctype, DOM, Fileinfo, Filter, GD, Hash, Mbstring, OpenSSL, PDO/MySQL, Session, Tokenizer and XML. Tests also need PDO/SQLite; Zip simplifies installation.
- Node 20.19+ or 22.12+ compatible with Vite 8; a current supported LTS is preferred.
- A queue worker, HTTPS and durable private storage in production.

PHP upload settings should allow `upload_max_filesize=32M`, `post_max_size=128M`, and `max_file_uploads=20`. Application validation still limits each applicant document to 10 MB, five per category, and twelve per application. CMS videos have a separate 30 MB limit.

## This Windows workspace

The project is already installed, migrated, seeded and built in `E:\WEBSITES\Edu_World`. An isolated PHP 8.4 runtime lives under ignored `.tools/php`; the machine's global PHP 8.2 cannot run this dependency lock. The ignored local `.env` uses SQLite, a local URL, and no live payment or SMTP credentials.

Run from `E:\WEBSITES\Edu_World`:

```powershell
.\.tools\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Open [the local website](http://127.0.0.1:8000) or [team sign-in](http://127.0.0.1:8000/admin/login).

Create your first Super Admin securely from the same directory:

```powershell
.\.tools\php\php.exe artisan edu:admin
```

The command asks for an email, name and hidden password confirmation. There is no seeded administrator or default password. Passwords require 12 characters, mixed case, a number and a symbol.

To process local queued mail (the default log transport does not deliver email):

```powershell
.\.tools\php\php.exe artisan queue:work --tries=3 --timeout=90
```

## Fresh installation

From the repository root on a host with a supported PHP runtime:

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with the real database, URL and mail configuration. For local HTTP use `APP_ENV=local`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=false` and an appropriate `APP_URL`. For SQLite, create `database/database.sqlite`, set `DB_CONNECTION=sqlite` and `DB_DATABASE` to its absolute path. Never commit `.env`.

Then run from the repository root:

```sh
php artisan migrate --seed
php artisan storage:link
php artisan edu:admin
npm ci --ignore-scripts
npm run build
php artisan serve
```

Only `storage/app/public` is linked to public storage. Applicant documents and unpublished editorial media remain under `storage/app/private`. Serve the application's `public` directory; never expose the repository root.

## Team workflow

| Role | Access |
| --- | --- |
| Super Admin | All modules, settings, accounts, audit log and explicit offline receipt/refund records |
| Admin | Applications, private documents, enquiries, payment review/linking and dashboard reports |
| Content Manager | News, galleries, categories, editable legal pages and their SEO |

After setup, review Website settings, configure the notification email and social URLs, and update business hours and the map. Review the legal pages under Pages & SEO. Sample published articles are general preparation guides, not advertised vacancies or claimed visa-rule updates.

Applications are handled under Applications. Status changes record the actor and history; payment state comes from linked payment records. Private document downloads require the application gate and create audit records. Disable a team account to revoke access.

CMS bodies are plain text with paragraph breaks, deliberately rendered safely. Editors can add images, galleries, videos, tags, CTA URLs, publication dates and SEO metadata. Only published records whose date has arrived appear publicly.

## Verification

From this Windows workspace:

```powershell
.\.tools\php\php.exe artisan test --compact
npm.cmd run build
```

The test suite covers all service types, validation, references, upload rollback/privacy, authentication/roles, enquiries, CMS publication/media, payment verification/replays, offline records, settings and notification rendering. It uses an isolated in-memory SQLite database by default. The core suite has also been exercised against an isolated MariaDB database. Never point tests at live business data.

PHP formatting uses Laravel Pint. This folder is not yet a Git repository, so use explicit source paths:

```powershell
.\.tools\php\php.exe vendor/bin/pint --format agent app config database routes tests
```

## Deployment and integration

- [Architecture, ER relationships and tables](docs/ARCHITECTURE.md)
- [Production deployment, queues, storage, backup and release operations](docs/DEPLOYMENT.md)
- [Payment provider contract and activation](docs/PAYMENTS.md)

The build is local. Production hosting, domain/DNS, SMTP delivery and a live merchant integration require the business's deployment configuration. Do not upload `.tools`, local databases or development test artifacts to a public web directory.
