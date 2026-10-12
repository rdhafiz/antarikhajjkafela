# Deployment (Hostinger + GitHub Actions)

Every push to `main` is tested in GitHub Actions. If the tests pass, the site is built (PHP dependencies and CSS/JS) and uploaded to Hostinger over SSH, then switched live in one step. The server never needs Node or Composer. Each deploy is kept in its own folder, so a rollback takes one command.

```
~/antarik/                       APP_DIR
├── shared/.env                  production settings (created once)
├── shared/storage/              uploads, logs, cache (kept between deploys)
├── releases/<date>-<commit>/    the last 5 deploys
└── current -> releases/…        the live release
~/domains/<domain>/public_html -> ~/antarik/current/public
```

## 1. One-time server setup

These steps need a Hostinger plan with **SSH access** (Premium, Business or Cloud). Turn it on in hPanel → Advanced → SSH Access.

1. **PHP version.** In hPanel → Advanced → PHP Configuration, choose **PHP 8.4** (8.3 also works). Make sure these extensions are enabled: `gd`, `intl`, `fileinfo`, `exif`, `bcmath`, `zip`, `pdo_mysql`.
2. **Database.** In hPanel → Databases → MySQL, create a database and a user. Write down the name, user and password.
3. **Email.** Create a mailbox such as `no-reply@<domain>` in hPanel → Emails. Booking and enquiry emails are sent from it.
4. **SSH key for deploys.** On your computer, run:
   ```bash
   ssh-keygen -t ed25519 -f antarik-deploy -C "github-deploy" -N ""
   ```
   Add the contents of `antarik-deploy.pub` in hPanel → SSH Access → SSH keys. The private key `antarik-deploy` goes into GitHub (step 2 below).
5. **Folders and settings.** Connect with SSH (the host, port and user are shown in hPanel), then run:
   ```bash
   mkdir -p ~/antarik/shared ~/antarik/releases
   nano ~/antarik/shared/.env        # paste the production .env below
   chmod 600 ~/antarik/shared/.env
   ```
6. **Web root.** After the first successful deploy, point the domain at the app:
   ```bash
   cd ~/domains/<domain>
   mv public_html public_html.old     # keep the old site until you're sure
   ln -s ~/antarik/current/public public_html
   ```
   If your plan doesn't allow replacing `public_html` with a link, ask Hostinger support to set the domain's document root to `~/antarik/current/public`.
7. **Admin account.** Create the first administrator:
   ```bash
   cd ~/antarik/current && php artisan admin:create
   ```
8. **Starting content** (first install only). This adds the pages, services, packages, blog posts and photos from the old website. It never overwrites changes made in the admin portal:
   ```bash
   cd ~/antarik/current && php artisan db:seed --force
   ```
   In production the seeder skips the local test login.

No cron job or queue worker is needed. Emails are sent immediately, and scheduled blog posts appear by themselves.

### Production `.env`

```dotenv
APP_NAME="Antarik Hajj Kafela"
APP_ENV=production
APP_KEY=                       # generate once: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://<domain>

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<database>
DB_USERNAME=<user>
DB_PASSWORD=<password>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=<domain>,www.<domain>

CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=no-reply@<domain>
MAIL_PASSWORD=<mailbox password>
MAIL_FROM_ADDRESS=no-reply@<domain>
MAIL_FROM_NAME="${APP_NAME}"
```

Payment gateway credentials, tracking IDs and reCAPTCHA keys are **not** set here. They're entered in the admin portal under Settings.

## 2. GitHub secrets

In the repository, go to Settings → Environments, create an environment called **`production`**, and add these secrets:

| Secret | Value |
| --- | --- |
| `SSH_HOST` | Server IP or host from hPanel → SSH Access |
| `SSH_PORT` | SSH port from hPanel (Hostinger usually uses `65002`) |
| `SSH_USER` | SSH username from hPanel |
| `SSH_PRIVATE_KEY` | Contents of the `antarik-deploy` private key |
| `SSH_KNOWN_HOSTS` | Output of `ssh-keyscan -p <port> <host>` |
| `APP_DIR` | Full path of the app folder, e.g. `/home/u123456789/antarik` |
| `PHP_BIN` | Optional. Only needed if `php -v` over SSH isn't 8.3/8.4; then use the full path, e.g. `/opt/alt/php84/usr/bin/php` |

Then go to Settings → Secrets and variables → Actions → **Variables** and add `DEPLOY_ENABLED` = `true`. Until it's set, pushes are only tested, not deployed.

Optionally, add yourself as a required reviewer on the environment so every deploy waits for your approval.

## 3. Deploying

- Push to `main`, or open Actions → **Test and deploy** → Run workflow.
- The deploy runs the database migrations, syncs the admin permissions, rebuilds the caches and switches the live folder.
- Follow the progress under the Actions tab. If any test fails, nothing is deployed.

### Rolling back

```bash
cd ~/antarik/releases && ls -1t                    # newest first
ln -sfn ~/antarik/releases/<previous> ~/antarik/current.tmp && mv -Tf ~/antarik/current.tmp ~/antarik/current
cd ~/antarik/current && php artisan optimize
```

Migrations are not undone automatically. Database changes are written to be backward compatible, so the previous release keeps working.

### Maintenance mode

```bash
cd ~/antarik/current && php artisan down     # shows the "We will be back shortly" page
php artisan up
```

## 4. After the first deploy

- **HTTPS.** Turn on SSL in hPanel → Security → SSL and force HTTPS.
- **Admin.** Sign in at `https://<domain>/ahk/admin/login` (the `ahk` part comes from `ADMIN_PREFIX` in `.env`), then in Settings:
  - check the branding, contact details, social links and SEO
  - add the Google Tag Manager, Analytics and Pixel IDs, and the reCAPTCHA v3 keys
  - add the payment gateway credentials (sandbox first, then live)
- **SSLCommerz.** In the merchant panel, set the IPN URL to `https://<domain>/payments/sslcommerz/ipn`.
- **Google Search Console.** Submit `https://<domain>/sitemap.xml`.
- **Old links.** If this replaces the old antarikhajjkafela.com site, its old addresses (for example `/about-us/` and `/services/hajj/`) redirect permanently to the new pages. The list is in `config/redirects.php`.
- **Checks.** Test a booking without payment, the contact form, and a sandbox payment end to end.
- **Health check.** `https://<domain>/up` returns 200 when the app is running.
