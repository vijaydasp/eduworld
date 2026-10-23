# Edu World deployment and operations

This guide deploys the repository to ordinary Laravel-compatible hosting. It does not provision hosting, register a payment merchant or supply credentials. [ARCHITECTURE.md](ARCHITECTURE.md) describes the implementation and [PAYMENTS.md](PAYMENTS.md) defines the provider integration contract.

## Troubleshooting an uploaded installation

### HTTP 400 after selecting a compatible PHP version

The production host check accepts the hostname in `APP_URL`. Set it to the URL actually used for this deployment. The official business domain, a development subdomain and `localhost` are different hosts; do not leave the local or sample value in the server configuration.

For the deployment at `https://eduworld.osmiuminfotech.com/`, edit only these entries in `/home/sites/14a/3/386d32cb7a/public_html/Edu_World/.env`, preserving the existing application key, database settings and credentials:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://eduworld.osmiuminfotech.com
```

Then, from `/home/sites/14a/3/386d32cb7a/public_html/Edu_World` using the host's PHP 8.4.1+ CLI binary:

```sh
php artisan config:clear
php artisan config:cache
```

If SSH is unavailable, use the hosting file manager to delete only the generated file `/home/sites/14a/3/386d32cb7a/public_html/Edu_World/bootstrap/cache/config.php` after saving `.env`. Laravel will read the updated environment without that cache. Keep the other files in `bootstrap/cache`. This file can contain cached credentials, so do not move it into a public backup file. Host/CDN caches may also need purging if they retain an earlier response.

The current runtime in the HTTP response must be distinguished from older log entries. A `vendor/composer/platform_check.php` exception occurs before Laravel boots. This dependency lock requires PHP 8.4.1+, and both the domain's web PHP handler and the CLI used for Composer/Artisan must be compatible. If a fresh platform exception persists while the panel shows PHP 8.4.26, check the exception's first line, timestamp and the PHP handler selected for this exact subdomain. Do not disable Composer's check or change generated vendor files to bypass an incompatible runtime.

## Requirements

- PHP **8.4.1 or newer** compatible with `composer.lock`, available both to PHP-FPM/the web server and the CLI. The root Composer constraint and locked Symfony 8 dependencies enforce this minimum.
- Composer 2 and a production MySQL/MariaDB database with InnoDB, foreign keys, transactions and `utf8mb4`. Use a dedicated database account. Schema migrations need DDL privileges; routine runtime access can be more restricted after deployment.
- PHP extensions: Ctype, cURL, DOM, Fileinfo, Filter, GD with JPEG/PNG/WebP support, Hash, Mbstring, OpenSSL, PCRE, PDO with `pdo_mysql`, Session, Tokenizer and XML. JSON is built into this PHP generation. Zip is useful for Composer; `pdo_sqlite` is needed for the supplied tests. OPcache is recommended. Linux queue workers benefit from PCNTL for process signals/timeouts. Composer's platform check is the final dependency check.
- Node.js supported by the locked Vite version: **20.19+ within Node 20, or 22.12+**. Prefer a maintained Node LTS version satisfying the lock. Node/npm are build-time requirements; a production PHP host can receive the generated `public/build` directory instead.
- HTTPS, a durable writable storage volume, outbound SMTP or a configured supported mail transport, and a supervised queue worker. Turnstile requires outbound HTTPS to Cloudflare when enabled. A future payment adapter needs the provider's documented network access.

Verify the actual release runtime from the repository root, for example `/srv/eduworld/current`:

```sh
php -v
php -m
composer --version
composer check-platform-reqs
```

## First installation

Keep the repository outside the public document root. A useful layout is `/srv/eduworld/releases/<release>`, a `/srv/eduworld/current` release symlink, and persistent storage/environment files outside individual releases. The exact directories and ownership depend on the hosting provider. Do not upload the local `.tools`, `node_modules`, development databases or test output as web content.

At the repository root on a new host:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
cp .env.example .env
```

Populate the deployment's `.env` privately before continuing. Do not overwrite an existing environment file on upgrades. Set at least:

| Configuration | Production choice |
| --- | --- |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `APP_URL` | Exact canonical HTTPS origin used by customers, links and Turnstile |
| `APP_KEY` | Generated once per installation; preserve securely with backups |
| `DB_CONNECTION` | `mysql` for the configured MySQL connection; Laravel also has a MariaDB connection if selected and configured |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Real database endpoint and private credentials |
| `SESSION_DRIVER`, `SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` | `database`, `true`, `true`, `lax` |
| `FILESYSTEM_DISK` | `private` |
| `QUEUE_CONNECTION`, `CACHE_STORE` | `database`, `database` unless deliberately replacing infrastructure |
| `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, credentials | Verified mail-provider settings; `MAIL_MAILER=log` does not send customer mail |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Authorized sender/domain and Edu World branding |
| `ADMIN_EMAIL` | Fallback application/enquiry notification recipient |
| `PAYMENT_GATEWAY` | Leave `disabled` until an approved provider adapter is implemented and tested |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Set both for the correct production hostname, or leave both unset |

The settings UI's application notification address takes precedence over `ADMIN_EMAIL` when populated. Website settings never need to contain SMTP/payment secrets. The repository uses UTC for application timestamps in `config/app.php`; align operational reporting and staff expectations with that configuration.

Generate the application key only for the new installation, then create the schema and defaults. Commands run from `/srv/eduworld/current`:

```sh
php artisan key:generate --no-interaction
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan storage:link --no-interaction
```

Seeders create default settings, seven editorial categories, three published general guidance articles and five legal pages flagged for review. They do not create a default administrator. Review the seeded content before opening the site to visitors. Re-running seeders uses existing keys/slugs and does not intentionally overwrite edited content.

Create the first super administrator interactively. The password is entered through a hidden terminal prompt, confirmed and validated; no password is supplied in shell arguments or source code. Command at `/srv/eduworld/current`:

```sh
php artisan edu:admin
```

The command asks for name, email and a password of at least 12 characters containing mixed case, a number and a symbol. Optional `--email` and `--name` flags are supported; there is deliberately no password flag. Sign in at `/admin/login` and create additional named staff accounts with the appropriate role. There is no public registration or password-reset screen; a super administrator manages staff credentials and account deactivation.

## Frontend assets and release caches

Build in CI or another trusted build environment using the lockfile, then deploy `public/build`. Commands at the repository root:

```sh
npm ci --ignore-scripts
npm run build
```

Remove any development `public/hot` marker from the deployment artifact so production uses the compiled manifest. The application uses Vite-built CSS/JS and a separately served `public/js/application-form.js`; retain the complete public directory. Do not run the Vite development server on the public production origin.

After the final environment and migrations are in place, run from `/srv/eduworld/current`:

```sh
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan queue:restart --no-interaction
```

Rebuild the configuration cache after changing environment values. Long-running workers must be restarted after code or configuration changes. Do not regenerate `APP_KEY` during routine releases; doing so invalidates encrypted sessions and existing signed links and can make other encrypted data unreadable.

## Document root, permissions and uploads

The HTTP document root must be **`<project>/public`**. The web user needs write access to `storage` and `bootstrap/cache`; deployed source and vendor code normally need only read access. Grant ownership/group access appropriate to your host instead of making the whole repository world-writable. Protect `.env`, backup archives, logs, framework caches and the complete private storage tree from direct HTTP access.

The `private` and `local` disks both resolve to `storage/app/private` and do not expose automatic storage routes. Applicant files are under `applications`; CMS images/videos are under `cms`. Those files are served through authorization/publication-aware controllers. The public storage symlink must resolve only to `storage/app/public`, which holds deliberately public branding uploads.

Application validation accepts **10 MB per file, five files per category and twelve files total**, with PDF/JPEG/PNG extension and detected-MIME agreement. CMS validation separately allows images up to 5 MB, twelve gallery images, a featured image and an MP4 up to 30 MB. PHP's global limit must permit the larger CMS upload while application validation continues to enforce its smaller bound.

Example PHP-FPM configuration: `/etc/php/8.4/fpm/conf.d/99-eduworld.ini` (adjust the PHP version/path to the host):

```ini
upload_max_filesize = 32M
post_max_size = 128M
max_file_uploads = 20
memory_limit = 256M
display_errors = Off
log_errors = On
expose_php = Off
```

Apply corresponding limits in the hosting panel, reverse proxy and web server. A 128 MB request limit supports the full applicant file allowance and the configured CMS combination. Reload PHP-FPM after changes. Choose execution/input timeouts appropriate to the host and expected connection speeds, and monitor image-processing memory usage. Identity documents are stored without lossy image optimization.

## Nginx example

Use your host's certificate management and PHP-FPM socket. The following is a starting point, not an instruction to replace an unrelated server configuration. Set the real domain, certificate paths and application directory. Ensure the HTTP-to-HTTPS server and certificate renewal procedure are configured by your deployment platform.

Example server block: `/etc/nginx/sites-available/eduworld`:

```nginx
server {
    listen 443 ssl;
    server_name eduworld.bh;
    root /srv/eduworld/current/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/eduworld.bh/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/eduworld.bh/privkey.pem;
    client_max_body_size 128m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\.(?!well-known/) {
        deny all;
    }
}
```

Only the front controller executes PHP in this example. Never add aliases exposing private storage. Test the web-server configuration with the host's normal validation command before reloading it. If TLS terminates at a load balancer, configure Laravel trusted proxies for that actual infrastructure, preserve the original HTTPS scheme and host, and test secure cookies, redirects and signed URLs. Do not blindly trust proxy headers arriving directly from the public internet.

For Apache/shared hosting, select PHP 8.4+ and point the domain/subdomain document root at the repository's `public` directory; Laravel's supplied `.htaccess` requires rewrite support. If the panel forces a `public_html` layout, keep application code outside `public_html`, expose only the intended public files, and have the hosting administrator correctly configure the front-controller paths and persistent public storage link. Uploading the entire repository into `public_html` is not a substitute for a correct document root. Confirm SSH/CLI access or an equivalent controlled deployment mechanism for migrations, queues and the hidden-password admin command.

## Queue, mail and scheduled operations

With `QUEUE_CONNECTION=database`, notifications remain queued until a worker runs. Configure a supervised process, owned by an account with application/storage access, and restart it on unexpected exit. Worker timeout must be lower than the database queue's `retry_after` (90 seconds by default).

Example Supervisor file: `/etc/supervisor/conf.d/eduworld-worker.conf`:

```ini
[program:eduworld-worker]
process_name=%(program_name)s_%(process_num)02d
directory=/srv/eduworld/current
command=/usr/bin/php /srv/eduworld/current/artisan queue:work database --sleep=3 --tries=3 --timeout=60 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/srv/eduworld/shared/storage/logs/worker.log
stopwaitsecs=90
```

Adjust the PHP binary, process account and log path; ensure the log parent exists and is writable. Protect logs, rotate them, and monitor the failed-job table. Application acknowledgements and administrator notifications are queued; payment notifications are created only after a verified settlement or an explicitly verified offline record. Mail delivery is an operational dependency, so verify the sender domain and send a real staging acknowledgement before launch.

Operational commands at `/srv/eduworld/current`:

```sh
php artisan queue:failed
php artisan queue:restart
```

Inspect a failed job before selectively retrying its ID; confirm the underlying mail/provider problem is fixed. Do not treat an unprocessed queue as successful email delivery.

News and gallery publication uses the application timezone, UTC worldwide (`config/app.php`). Enter UTC in the publication date field, select **Publish this post**, and save. The editor and list show the timezone and current time; the save message confirms Draft, Scheduled, or Published. A date alone does not publish a draft. Posts and their gallery media become available on requests at or after the scheduled time. Refresh an already-open public page to see them.

After uploading a scheduling update, run `php artisan view:clear` and `php artisan config:cache` from the application root. Review existing schedules and re-enter the intended UTC time before saving; do not bulk-shift stored timestamps because earlier entries may already be UTC. For example, 15:00 Bahrain is 12:00 UTC; 17:30 India is 12:00 UTC.

No business scheduler job is currently required: publication dates are checked in database queries. If scheduled jobs are added, a cron entry may run Laravel's scheduler every minute. On hosting without a process manager, a non-overlapping periodic `queue:work --stop-when-empty` invocation is a fallback, with delayed email delivery; verify the host actually allows CLI execution and sufficient runtime. Do not run both worker approaches accidentally.

Optional future scheduler entry in the deployment account's crontab:

```cron
* * * * * cd /srv/eduworld/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## Settings, legal content and payment readiness

Before public launch, a super administrator should verify contact details, WhatsApp, social links, office location/map, branding, business hours, default SEO metadata and the application notification address under `/admin/settings`. The currency is BHD. Confirm sitemap/canonical URLs use the production origin.

The seeded privacy, terms, payment terms, refund and disclaimer pages are editable starting text. The business must review them against its actual services, fees, refund decisions, information recipients, retention practices and applicable requirements. The `review_required` flag is an administrative reminder; these pages are already publicly accessible. A content manager or super administrator can replace the text and clear the flag after the review is completed. Establish a process for privacy requests and document retention; the application does not automatically decide or enforce retention periods.

Leave `PAYMENT_GATEWAY=disabled` until an approved Bahrain-compatible provider is selected and its adapter is implemented. Adding secrets alone does not activate checkout. Required work includes registration in `config/payments.php`, exact HTTPS checkout-host allowlisting, authenticated raw-body webhook verification, merchant/currency/amount/session checks, replay handling and sandbox acceptance tests. Configure the provider's HTTPS webhook at `/payments/webhook/{gateway}`. Read [PAYMENTS.md](PAYMENTS.md) before enabling it. Offline receipt/refund entries record already verified money movements; they do not initiate bank transfers or provider refunds.

## Release verification and upgrades

Run feature tests in an isolated test/CI environment with development dependencies. Tests use SQLite by default and must never target the live database. Also exercise critical flows against a staging MySQL/MariaDB database before deploying changes involving transactions or locking. Verify PHP/platform requirements with the production dependency set.

Commands at an isolated test checkout:

```sh
composer install --prefer-dist --no-interaction
php artisan test --compact
npm ci --ignore-scripts
npm run build
```

For the deployed environment, verify `/up`, the homepage/services/news/legal pages, application/contact forms and their validation, receipt sessions, admin sign-in and role restrictions, private file downloads/audits, queue processing and branding media. Ensure unauthenticated requests cannot read a private file, draft news/media remain inaccessible, and `/admin`/receipt pages carry appropriate private/no-store responses. `/up` is an application boot check, not proof that mail or an external payment provider is working.

Before an upgrade, take a coordinated database/file backup and review migration compatibility. Build a new release, preserve `.env`/storage, install the locked dependencies, migrate deliberately, switch to the tested release, rebuild caches and restart workers. A code rollback is safe only while the database schema remains compatible. Do not run `migrate:fresh`, broad rollbacks, or restore commands against production as a routine deployment step.

## Backups, restoration and retention

Back up the database, `storage/app/private`, `storage/app/public`, deployment configuration and the application key as one recoverable system. Private storage contains identity and education/employment documents as well as nonpublic editorial media. Encrypt backups, restrict access, keep an off-host copy, and set a business-approved retention schedule. Audit logs, queue payloads and operational logs can also contain personal information and require restricted access.

Use a consistent database snapshot and coordinate it with file writes; a maintenance window or infrastructure snapshot can prevent a restore from referencing files that were not backed up. Store database credentials in a protected option file or secret manager rather than command-line password arguments. Never put backup archives in `public` or `storage/app/public`.

A restoration drill should use a separate environment with outbound mail and payments disabled. Restore the matching release/schema, database, both storage trees and the original application key; apply correct permissions, rebuild caches and verify representative applications/documents, editorial media and payment event records. Review queued notifications before starting workers to avoid replaying customer mail. Reconcile any real provider activity occurring after the backup before resuming live payment processing. Record the tested recovery procedure and actual recovery time.

Monitor free space, database backups, queue failures, application errors, TLS renewal and repeated authorization/validation failures. The supplied audit viewer supports review of important staff operations; it is not a complete monitoring or backup system.
