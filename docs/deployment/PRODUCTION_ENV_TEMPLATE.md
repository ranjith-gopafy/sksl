# SKSL — Production Environment Variables Template

**Purpose:** Reference for filling the production `.env` file on Hostinger.  
**NEVER commit the actual `.env` file to Git.**

---

> [!CAUTION]
> This file shows only key names and descriptions — **never the actual values**. Treat your `.env` like a password. Never share it or commit it to version control.

---

## Application Settings

| Variable | Required | Description | Example Value |
|---|:---:|---|---|
| `APP_ENV` | ✅ | Environment mode | `production` |
| `APP_DEBUG` | ✅ | Error display (must be `false` in production) | `false` |
| `APP_URL` | ✅ | Full production URL with HTTPS | `https://www.yourdomain.com` |
| `APP_TIMEZONE` | ✅ | PHP timezone for IST | `Asia/Kolkata` |
| `APP_SECRET` | ➖ | Reserved for future signed tokens. **Not used by the current code**: CSRF tokens, OTPs and reset tokens are random per-session / per-row values. Set a random value anyway so nothing ever ships with the placeholder. | Run: `php -r "echo bin2hex(random_bytes(32));"` |
| `HEALTH_CHECK_TOKEN` | ➖ | Unlocks env/db details on `/health` for uptime monitors (`X-Health-Token`). Public response is only `{"success","status"}`. | Random string |
| `SECURITY_CONTACT_EMAIL` | ➖ | Contact published in `/.well-known/security.txt` (falls back to `BUSINESS_SUPPORT_EMAIL`). | `security@yourdomain.com` |

---

## Database Settings

| Variable | Required | Description | How to Find |
|---|:---:|---|---|
| `DB_HOST` | ✅ | MySQL hostname | Hostinger hPanel → Databases → show details |
| `DB_PORT` | ✅ | MySQL port | Usually `3306` |
| `DB_DATABASE` | ✅ | Database name | e.g. `u123456789_sksl` |
| `DB_USERNAME` | ✅ | Database user | Set in hPanel when creating DB user |
| `DB_PASSWORD` | ✅ | Database user password | Set in hPanel when creating DB user |

---

## Razorpay Payment Gateway

| Variable | Required | Description | Where to Find |
|---|:---:|---|---|
| `RAZORPAY_KEY_ID` | ✅ | Live API Key ID | Razorpay Dashboard → Settings → API Keys → Generate Live Key |
| `RAZORPAY_KEY_SECRET` | ✅ | Live API Secret Key | Same — shown once on generation, save securely |
| `RAZORPAY_WEBHOOK_SECRET` | ✅ | Webhook signing secret | Razorpay Dashboard → Settings → Webhooks → Edit |

> [!IMPORTANT]
> **Test Keys** start with `rzp_test_`. **Live Keys** start with `rzp_live_`.
> The app will use mock mode if `RAZORPAY_KEY_ID` is empty.
> Switch to live keys only after full end-to-end testing is complete.

---

## SMTP Email Settings

| Variable | Required | Description | Hostinger Titan Mail Values |
|---|:---:|---|---|
| `SMTP_HOST` | ✅ | SMTP server hostname | `smtp.titan.email` |
| `SMTP_PORT` | ✅ | SMTP port | `587` (TLS) or `465` (SSL) |
| `SMTP_USERNAME` | ✅ | SMTP username / email address | `info@yourdomain.com` |
| `SMTP_PASSWORD` | ✅ | SMTP password | Set in hPanel → Email → Manage |
| `SMTP_ENCRYPTION` | ✅ | Encryption method | `tls` (port 587) or `ssl` (port 465) |
| `MAIL_FROM_ADDRESS` | ✅ | From email address on outgoing emails | `info@yourdomain.com` |
| `MAIL_FROM_NAME` | ✅ | From display name | `Sara Kinetic Sports Lab` |
| `ADMIN_EMAIL` | ✅ | Admin email for OTP delivery and booking alerts | Your admin email |

> [!TIP]
> For **Gmail SMTP**: Use port 587, TLS, and an App Password (not your Google account password). Enable 2FA on Gmail first, then generate an App Password under Security.
> 
> For **Hostinger Titan Mail**: Create an email account in hPanel → Emails, then use those credentials here.

---

## Session Settings

| Variable | Required | Description | Production Value |
|---|:---:|---|---|
| `SESSION_SECURE` | ✅ | Set to `true` in production HTTPS | `true` |

---

## Booking Business Rules

| Variable | Required | Description | Confirmed Value |
|---|:---:|---|---|
| `BOOKING_HOLD_MINUTES` | ✅ | Payment hold window in minutes | `10` |
| `FACILITY_OPEN` | ✅ | Facility opening time (24h) | `06:00` |
| `FACILITY_CLOSE` | ✅ | Facility closing time (24h) | `22:00` |
| `GST_RATE` | ✅ | GST percentage (fixed at 18% V1) | `18` |
| `CHECKIN_BUFFER_MINUTES` | ⚠️ | Buffer before session (pending client decision) | `0` |
| `CHECKOUT_BUFFER_MINUTES` | ⚠️ | Buffer after session (pending client decision) | `0` |
| `UPLOAD_MAX_SIZE_BYTES` | ✅ | Max service image upload size in bytes | `5242880` (5MB) |

---

## What Needs Business Input Before Go-Live

The following values require confirmation from the client before production launch:

1. **GSTIN** — Current invoices use a placeholder GSTIN. Update in `InvoiceService.php` with the official registered GSTIN.
2. **Business Address** — Update in `InvoiceService.php` with the official registered address.
3. **Admin Email** — The email address that receives booking notifications and OTP codes.
4. **Legal Policy Content** — Review and finalize `terms.php`, `privacy-policy.php`, `cancellation-refund.php`.
5. **Cancellation Policy Window** — Currently enforced at 2 hours before session.

---

## Generating the App Secret

Run this locally (not on production to avoid shell history exposure):

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Copy the output and paste it as `APP_SECRET=<output>` in your production `.env`.
