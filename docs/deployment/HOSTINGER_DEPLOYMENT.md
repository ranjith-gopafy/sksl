# SKSL — Hostinger Deployment Guide

## 1. Purpose

This document defines the deployment approach for SKSL from local XAMPP development to Hostinger shared hosting.

Production deployment must be performed carefully because the application handles customer data and payments.

---

# 2. Local Environment

Recommended local stack:

- XAMPP
- PHP
- MySQL
- phpMyAdmin
- Composer
- Node.js/npm only for Tailwind build tooling
- Git

Local environment should use separate development credentials and Razorpay test keys.

---

# 3. Production Requirements

Confirm before deployment:

- Hostinger PHP version
- MySQL version
- Required PHP extensions
- Composer/vendor strategy
- SSL availability
- Cron availability
- SMTP connectivity
- File permissions
- Document root configuration

Do not assume local and production PHP versions are identical.

---

# 4. Recommended Production Structure

Keep public web-accessible files limited to the intended web root.

Sensitive application directories should not be directly exposed.

Conceptually:

```text
public/
    index.php
    assets/

app/
config/
routes/
storage/
cron/
vendor/
.env
```

The exact Hostinger document-root arrangement must be adapted to the hosting configuration.

---

# 5. Environment Variables

Create production `.env` containing:

```text
APP_ENV=production
APP_DEBUG=false

APP_URL=https://your-domain.example

DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

RAZORPAY_KEY_ID=...
RAZORPAY_KEY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...

MAIL_HOST=...
MAIL_PORT=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=...
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME=...

APP_SECRET=...
```

Do not commit `.env`.

Use `.env.example` with placeholders only.

---

# 6. Database Deployment

1. Create production database in Hostinger.
2. Create database user.
3. Grant only required permissions.
4. Import schema/migrations.
5. Verify tables.
6. Verify foreign keys.
7. Verify indexes.
8. Verify unique constraints.
9. Create initial admin account through a secure process.
10. Do not import real customer/payment data into a development environment.

---

# 7. Composer Dependencies

Install production dependencies from the project's lock file where available.

Expected major dependencies:

- PHPMailer
- Razorpay PHP SDK
- mPDF

Do not manually upload random vendor packages without documenting the source/version.

---

# 8. Tailwind Production Build

Build Tailwind locally/through the approved build process.

Production should receive compiled CSS.

Do not rely on Tailwind CDN.

Do not expose development build tooling unnecessarily on production.

---

# 9. File Permissions

Review permissions for:

- application files
- configuration
- storage
- uploads
- logs
- invoices

Writable directories should be limited to those that actually require writes.

Never make the entire application directory world-writable.

---

# 10. Protect Sensitive Files

Verify that the public web server cannot directly download:

- `.env`
- database configuration
- private logs
- internal source files
- private invoices
- temporary files
- backups
- SQL dumps

Use the hosting/web-server configuration appropriate to Hostinger.

---

# 11. HTTPS

Before production use:

- Enable SSL.
- Force HTTPS where appropriate.
- Confirm secure cookies.
- Confirm payment/webhook endpoints use HTTPS.
- Test the site without mixed-content warnings.

---

# 12. Razorpay Production

Before switching to live payments:

- Replace test key ID with production key ID.
- Replace test secret with production secret.
- Configure production webhook URL.
- Configure webhook secret.
- Verify signature handling.
- Perform controlled live test if commercially appropriate.

Never expose the secret key in frontend code.

---

# 13. SMTP

Configure:

- SMTP host
- port
- encryption
- username
- password
- sender address
- sender name

Test:

- customer confirmation email
- invoice attachment
- admin notification
- SMTP failure behavior

Do not use personal email credentials casually in production.

---

# 14. Cron

Configure Hostinger Cron Jobs for:

```text
cron/expire-holds.php
```

The script should:

- identify expired active holds
- release/expire them
- log safe information
- exit cleanly

Cron is cleanup only. Booking availability must independently evaluate hold expiry.

Protect cron scripts from arbitrary public execution where possible.

---

# 15. Logging

Production logs should be:

- useful
- protected
- rotated/managed where practical
- free of secrets

Never log:

- passwords
- OTPs
- API secrets
- SMTP passwords
- database passwords

---

# 16. Deployment Workflow

Recommended:

```text
Local Development
      |
      v
Git Commit
      |
      v
Code Review / Tests
      |
      v
Build Production Assets
      |
      v
Deploy to Hostinger
      |
      v
Configure .env
      |
      v
Run Database Migration
      |
      v
Configure Cron
      |
      v
Configure SMTP
      |
      v
Configure Razorpay Webhook
      |
      v
Smoke Test
      |
      v
UAT
      |
      v
Production Approval
```

---

# 17. Pre-Deployment Checklist

- [ ] Git working tree reviewed
- [ ] `.env` excluded
- [ ] No secrets in source
- [ ] Production debug disabled
- [ ] Dependencies installed
- [ ] Tailwind compiled
- [ ] Database schema ready
- [ ] Backup plan ready
- [ ] HTTPS ready
- [ ] SMTP ready
- [ ] Razorpay production configuration ready
- [ ] Webhook ready
- [ ] Cron ready
- [ ] Upload directories protected
- [ ] Invoice storage protected

---

# 18. Post-Deployment Smoke Tests

Test:

- [ ] Homepage
- [ ] Registration
- [ ] Login
- [ ] Logout
- [ ] Password reset
- [ ] Service listing
- [ ] Availability
- [ ] Booking hold
- [ ] Razorpay payment
- [ ] Payment verification
- [ ] Webhook
- [ ] Booking confirmation
- [ ] Invoice
- [ ] Customer email
- [ ] Admin notification
- [ ] Admin login
- [ ] Admin dashboard
- [ ] Service management
- [ ] Closed date
- [ ] Cron

---

# 19. Rollback

Before risky production changes:

- database backup
- current code backup/version
- configuration backup where safe

If deployment fails:

1. Stop further changes.
2. Identify failure.
3. Restore previous known-good code if necessary.
4. Restore database only when required and safe.
5. Re-test payment/booking integrity.
6. Document the incident.

Never blindly restore an old database over newer valid customer/payment data.

---

# 20. Production Monitoring

Monitor:

- PHP errors
- application errors
- failed emails
- failed payments
- webhook failures
- expired holds
- database errors
- disk usage
- SSL status

Do not collect unnecessary personal information in monitoring logs.

---

# 21. Security Rule

Production deployment is not complete until:

- secrets are protected
- HTTPS works
- payment verification works
- webhook verification works
- overbooking protection works
- sensitive files are protected
- debug mode is disabled
- backups exist
- smoke tests pass
