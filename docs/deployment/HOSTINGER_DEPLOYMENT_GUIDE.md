# SKSL — Hostinger Production Deployment Guide

**Application:** Sara Kinetic Sports Lab (SKSL) Online Booking Platform  
**Target Hosting:** Hostinger Shared Hosting (hPanel)  
**Stack:** PHP 8.2, MySQL 8.0, Tailwind CSS  

---

## Pre-Deployment Checklist

Before starting, ensure you have:

- [ ] Hostinger account credentials
- [ ] Live Razorpay API credentials (Key ID + Secret starting with `rzp_live_...`)
- [ ] Production SMTP email credentials (Hostinger Titan Mail / Google Workspace)
- [ ] Official GSTIN, business name, and address for invoices
- [ ] Admin email address for OTP login
- [ ] SSH access enabled in Hostinger hPanel (or access to File Manager)

---

## Step 1: Prepare Files for Upload

### 1.1 — Build Production Tailwind CSS

On your local machine, run:

```bash
npm run build
```

This regenerates `public/css/app.css` as a minified production bundle.

### 1.2 — Install Production Composer Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

This removes development-only packages and optimizes the autoloader for production.

### 1.3 — Files to EXCLUDE from upload

The following must **never** be deployed to Hostinger:

| File/Directory | Reason |
|---|---|
| `.git/` | Version control internals |
| `.env` | Contains local credentials |
| `node_modules/` | Development tooling |
| `src/css/` | Tailwind source (only `public/css/app.css` needed) |
| `tests/` | Test suites not needed in production |
| `playwright.config.js` | E2E testing config |
| `composer.json` / `composer.lock` | If SSH is unavailable (use pre-built vendor/) |

### 1.4 — Required Upload List

```
app/
bootstrap.php
composer.json
config/
cron/
database/
public/
Router.php
storage/   (empty dirs — just .gitkeep files)
vendor/
```

---

## Step 2: Create the Production Database on Hostinger

### 2.1 — Create MySQL Database in hPanel

1. Log in to [hPanel](https://hpanel.hostinger.com)
2. Navigate to **Databases → MySQL Databases**
3. Create a new database, e.g. `u123456789_sksl`
4. Create a database user with a strong password
5. Assign the user to the database with **All Privileges**
6. Note the Host, Database name, Username, and Password

### 2.2 — Import the Schema & Seeds

1. In hPanel, navigate to **Databases → phpMyAdmin**
2. Select your new database
3. Click **Import** → choose `database/sksl_export.sql`
4. Click **Go**

**Verify tables were created:**

```sql
SHOW TABLES;
-- Expected: users, admins, admin_otps, services, closed_dates,
--           bookings, booking_holds, payments, password_resets,
--           email_logs, hero_banners
```

### 2.3 — Update Admin Email

```sql
UPDATE admins SET email = 'your-admin-email@yourdomain.com' WHERE id = 1;
```

---

## Step 3: Upload Application Files

### Option A — Via FTP (FileZilla recommended)

1. Download FileZilla from https://filezilla-project.org
2. Connect to Hostinger FTP:
   - Host: FTP host from hPanel (e.g. `ftp.yourdomain.com`)
   - Username: Your Hostinger FTP username
   - Password: Your FTP password
   - Port: `21`
3. Navigate to `public_html/` on the server
4. Upload all project files (excluding items listed in Step 1.3)

### Option B — Via Hostinger File Manager

1. hPanel → **Files → File Manager**
2. Navigate to `public_html/`
3. Click **Upload** → select all files as a ZIP, then extract

### Option C — Via SSH (Recommended for large projects)

```bash
# Connect to Hostinger SSH
ssh u123456789@srv123.hostinger.com

# Navigate to public_html
cd public_html

# Clone or upload via rsync from local machine:
rsync -avz --exclude='.git' --exclude='node_modules' --exclude='.env' \
  ./  u123456789@srv123.hostinger.com:~/public_html/
```

---

## Step 4: Configure Document Root

Hostinger shared hosting serves from `public_html/` by default. Since SKSL's web root is the `public/` subdirectory, you need one of these:

### Option A — Redirect via .htaccess in public_html root

Create `public_html/.htaccess`:

```apache
RewriteEngine On
RewriteRule ^(.*)$ /public/$1 [L,QSA]
```

### Option B — Set Document Root to public/ (Preferred — via hPanel)

1. hPanel → **Websites → Your Domain → Manage**
2. Look for **Document Root** or **Web Root** setting
3. Set it to: `public_html/public`
4. Save and restart Apache

> [!IMPORTANT]
> Option B is strongly preferred. It ensures no application code is accidentally accessible from the web root.

---

## Step 5: Create Production `.env`

Via SSH or File Manager, create `/public_html/.env`:

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.yourdomain.com
APP_TIMEZONE=Asia/Kolkata
APP_SECRET=<generate-a-64-char-random-string-here>

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_sksl
DB_USERNAME=u123456789_sksluser
DB_PASSWORD=<your-db-password>

# Razorpay (LIVE keys)
RAZORPAY_KEY_ID=rzp_live_XXXXXXXXXXXX
RAZORPAY_KEY_SECRET=<your-live-secret>
RAZORPAY_WEBHOOK_SECRET=<your-webhook-secret>

# SMTP (Hostinger Titan Mail)
SMTP_HOST=smtp.titan.email
SMTP_PORT=587
SMTP_USERNAME=info@yourdomain.com
SMTP_PASSWORD=<your-email-password>
SMTP_ENCRYPTION=tls
MAIL_FROM_ADDRESS=info@yourdomain.com
MAIL_FROM_NAME=Sara Kinetic Sports Lab

# Admin
ADMIN_EMAIL=admin@yourdomain.com

# Session (HTTPS required)
SESSION_SECURE=true

# Business rules
BOOKING_HOLD_MINUTES=10
FACILITY_OPEN=06:00
FACILITY_CLOSE=22:00
GST_RATE=18
CHECKIN_BUFFER_MINUTES=0
CHECKOUT_BUFFER_MINUTES=0
UPLOAD_MAX_SIZE_BYTES=5242880
```

> [!CAUTION]
> Never commit `.env` to Git. Never display it publicly. The file should have restricted permissions:
> ```bash
> chmod 600 .env
> ```

---

## Step 6: Generate APP_SECRET

Generate a strong random secret using PHP (run in Hostinger SSH or local terminal):

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Copy the output and use it as `APP_SECRET` in production `.env`.

---

## Step 7: Configure Cron Job for Hold Expiry

In hPanel → **Advanced → Cron Jobs**, add:

| Field | Value |
|---|---|
| **Command** | `php /home/u123456789/public_html/cron/expire-holds.php` |
| **Minute** | `*/5` |
| **Hour** | `*` |
| **Day** | `*` |
| **Month** | `*` |
| **Weekday** | `*` |

This runs every 5 minutes to expire stale booking holds.

> [!NOTE]
> The application's availability engine independently ignores expired holds during slot calculation, so even if the cron is temporarily delayed, capacity is never incorrectly blocked.

---

## Step 8: Set Directory Permissions

```bash
# Via SSH
chmod 755 public_html/public
chmod 755 public_html/public/css
chmod 755 public_html/public/images
chmod 777 public_html/storage
chmod 777 public_html/storage/logs
chmod 777 public_html/storage/invoices
```

---

## Step 9: Configure Razorpay Webhook

1. Log in to [Razorpay Dashboard](https://dashboard.razorpay.com)
2. Navigate to **Settings → Webhooks**
3. Click **Add New Webhook**
4. Set URL to: `https://www.yourdomain.com/api/payment/webhook`
5. Set the Webhook Secret (same as `RAZORPAY_WEBHOOK_SECRET` in `.env`)
6. Enable events: `payment.captured`, `order.paid`
7. Save

---

## Step 10: Force HTTPS Redirect

Add to `public_html/public/.htaccess` (add BEFORE existing rules):

```apache
# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## Step 11: Post-Deployment Verification

Run these checks after deploying:

```bash
# 1. Diagnostic script (via SSH)
php public_html/tests/diagnostic/check_credentials.php

# 2. Send test email
php public_html/tests/diagnostic/test_smtp_send.php

# 3. Test PHP test suites
php public_html/tests/test_phase3_auth.php
php public_html/tests/test_phase7_payment.php
```

**Browser checks:**

- [ ] Visit `https://www.yourdomain.com` — homepage loads
- [ ] Visit `https://www.yourdomain.com/services` — all 10 modalities
- [ ] Register a new customer account
- [ ] Complete a booking with Razorpay test card `4111 1111 1111 1111`
- [ ] Verify confirmation email received
- [ ] Verify invoice PDF downloads correctly
- [ ] Login to `/admin/login` with admin email
- [ ] Verify OTP email received and login succeeds
- [ ] Admin can view bookings, manage services, close dates

---

## Step 12: Go-Live Checklist

Before switching to LIVE Razorpay keys:

- [ ] End-to-end booking flow tested with test keys
- [ ] Confirmation emails delivered to real inbox
- [ ] Invoice PDFs generated correctly with correct GSTIN/address
- [ ] Admin OTP login working
- [ ] All 14 PHP test suites passing
- [ ] HTTPS active with valid SSL certificate
- [ ] Cron job running (`*/5 * * * *` for expire-holds.php)
- [ ] `APP_DEBUG=false` in production `.env`
- [ ] Razorpay Live keys set (replace `rzp_test_` with `rzp_live_`)
- [ ] Webhook configured with live endpoint
- [ ] Final Terms & Privacy Policy content reviewed by client
- [ ] GSTIN and business address verified on invoices

---

## Common Troubleshooting

| Issue | Solution |
|---|---|
| 500 Internal Server Error | Check `APP_DEBUG=true` temporarily, review Apache error logs |
| Database connection failed | Verify `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env` |
| Emails not sending | Run `test_smtp_send.php`, check SMTP credentials |
| Razorpay payment fails | Verify keys are correct mode (test vs live), check webhook URL |
| Invoice PDF not generating | Ensure `storage/invoices/` is writable (`chmod 777`) |
| OTP not arriving | Check `ADMIN_EMAIL` in `.env`, verify SMTP credentials |
| CSS not loading | Check `public/css/app.css` was uploaded; run `npm run build` locally first |
| File upload fails | Check `UPLOAD_MAX_SIZE_BYTES` and `upload_max_filesize` in `php.ini` |
