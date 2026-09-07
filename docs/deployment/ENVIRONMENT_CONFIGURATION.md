# SKSL Environment Configuration

**Project:** Sara Kinetic Sports Lab (SKSL)  
**Version:** 1.0  
**Status:** MVP Standard

---

## 1. Purpose

This document defines application configuration and environment variables for local development and Hostinger production deployment.

Secrets must be separated from source code.

---

## 2. Configuration Principles

1. No secrets committed to Git.
2. Local and production configuration must be separate.
3. Production debug mode must be disabled.
4. Configuration must be validated at startup.
5. Sensitive files must not be publicly downloadable.
6. Application code should read configuration through one centralized configuration layer.
7. Do not hard-code credentials in PHP or JavaScript.
8. Never expose server-side environment values through API responses.

---

## 3. Environment Files

Recommended files:

```text
.env
.env.example
```

`.env`:

- Real local/production values
- Never commit

`.env.example`:

- Variable names
- Safe placeholder values
- Commit to Git

Example:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/sksl
APP_TIMEZONE=Asia/Kolkata

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sksl
DB_USERNAME=root
DB_PASSWORD=

RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

SMTP_HOST=
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=SKSL

ADMIN_EMAIL=

SESSION_SECURE=false

UPLOAD_MAX_SIZE=5242880
BOOKING_HOLD_MINUTES=10
GST_RATE=18
```

The exact variable names may be adapted to the implementation, but the configuration concept must remain centralized and documented.

---

## 4. Required Application Settings

### Application

- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `APP_TIMEZONE`

Production example:

```env
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Kolkata
```

### Database

- Host
- Port
- Database name
- Username
- Password

PDO must use these values.

### Razorpay

- Public key ID
- Secret key
- Webhook secret

Only the public key ID may be sent to the browser where required by Razorpay Checkout.

The secret and webhook secret remain server-side.

### SMTP

- SMTP host
- Port
- Username
- Password
- Encryption mode
- From email
- From name

### Session

Configuration should include appropriate secure-cookie behavior.

Production should use:

- HTTPS
- Secure cookies
- HttpOnly cookies
- SameSite policy

---

## 5. Business Configuration

The following are business rules and must not be scattered throughout code.

Centralize at minimum:

- Facility timezone
- Facility opening time
- Facility closing time
- GST rate
- Booking hold duration
- Upload size limit
- Approved operational buffers once finalized

Current known values:

```text
Timezone: Asia/Kolkata
Facility hours: 06:00–22:00
GST: 18%
Temporary booking hold: 10 minutes
```

Pending client decisions must not be silently hard-coded.

---

## 6. Money Configuration

Do not calculate financial values using floating-point arithmetic where precision matters.

Preferred approach:

- Store money in integer smallest units (for example paise) or use a carefully controlled decimal strategy.
- Calculate totals server-side.
- Store historical booking/payment amounts as snapshots.

Razorpay amount must be derived from the authoritative server-side calculation.

---

## 7. Time Configuration

Use one consistent business timezone:

> Asia/Kolkata

Store timestamps consistently, preferably using UTC for technical timestamps where appropriate, while converting to the facility timezone for booking/business operations.

Do not rely on the browser's timezone for booking rules.

---

## 8. Configuration Loader

Create one configuration layer responsible for:

1. Loading environment values.
2. Applying safe defaults where appropriate.
3. Validating required values.
4. Converting values to correct types.
5. Exposing configuration to application services.

Example conceptual access:

```php
$config->get('app.url');
$config->get('database.host');
$config->get('razorpay.key_id');
```

Avoid repeatedly calling `getenv()` throughout business logic.

---

## 9. Startup Validation

Production startup/configuration validation should verify required settings exist.

Examples:

- Database credentials available
- Razorpay credentials available
- SMTP configuration available
- Application URL available
- Timezone valid
- Required upload path exists
- Required storage path is writable

If a critical production configuration is missing, fail safely and log the configuration error.

Do not reveal the missing secret/value to the browser.

---

## 10. Local Environment

Recommended local stack:

- Windows
- XAMPP
- Apache
- PHP
- MySQL
- phpMyAdmin
- Composer
- Node.js/npm for Tailwind build

Local `.env` may use:

```env
APP_ENV=local
APP_DEBUG=true
SESSION_SECURE=false
```

Do not copy production secrets into local development unnecessarily.

---

## 11. Production Environment

Hostinger production should use:

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
```

Production must use:

- HTTPS
- Production database credentials
- Production Razorpay credentials only after go-live approval
- Production SMTP credentials
- Secure session cookies

---

## 12. Frontend Environment Exposure

Only values explicitly intended for the browser may be exposed.

Potentially public:

- Razorpay Key ID
- Public application URL
- Non-sensitive UI configuration

Never expose:

- Database credentials
- Razorpay secret
- Webhook secret
- SMTP password
- Admin credentials
- Session secrets
- `.env` contents

---

## 13. File Upload Configuration

Service images are optional.

Configuration should define:

- Maximum upload size
- Allowed MIME types
- Allowed extensions
- Storage directory
- Generated filename strategy

Uploads must be validated server-side.

Never trust a filename or MIME type supplied by the browser.

Do not allow uploaded files to execute as server-side scripts.

---

## 14. Cron Configuration

Hostinger Cron Jobs may be used for:

- Expired booking hold cleanup
- Other approved maintenance jobs

Cron jobs must:

- Be idempotent
- Be safe to run repeatedly
- Use server-side configuration
- Not expose credentials in command-line arguments
- Log meaningful failures

The application must not depend on a cron job for immediate booking correctness. Expired holds should also be treated as expired during availability/capacity checks.

---

## 15. `.gitignore`

At minimum:

```gitignore
.env
.env.*
!.env.example

/vendor/
/node_modules/

*.log

/uploads/*
!/uploads/.gitkeep
```

Adapt paths to the actual project structure.

Never commit:

- Production `.env`
- Database dumps containing real customer data
- Payment secrets
- SMTP credentials
- Private keys

---

## 16. Configuration Change Procedure

When adding a new environment variable:

1. Add it to `.env.example`.
2. Document its purpose.
3. Add validation if required.
4. Add local value.
5. Add production value through secure hosting configuration.
6. Test startup/configuration.
7. Update deployment documentation.

Do not silently introduce undocumented environment dependencies.

---

## 17. Environment Separation

Maintain separate:

- Local database
- Production database
- Local SMTP credentials where possible
- Production SMTP credentials
- Razorpay test credentials
- Razorpay production credentials

Never mix test and production payment environments accidentally.

---

## 18. Production Release Checklist

Before deployment verify:

- [ ] `.env` is not committed.
- [ ] Production debug is disabled.
- [ ] HTTPS is active.
- [ ] Secure cookies are enabled.
- [ ] Database credentials are correct.
- [ ] Razorpay production keys are correct.
- [ ] Webhook secret is configured.
- [ ] SMTP is configured.
- [ ] Application timezone is correct.
- [ ] GST configuration is correct.
- [ ] Booking hold is 10 minutes.
- [ ] Upload limits are configured.
- [ ] Sensitive directories are protected.
- [ ] Cron jobs are configured where required.
- [ ] Production smoke tests pass.

---

## 19. Configuration Acceptance Criteria

The implementation is complete when:

- Configuration is centralized.
- `.env` is excluded from Git.
- `.env.example` documents required values.
- Local and production environments are clearly separated.
- Secrets are never exposed to frontend code.
- Production errors do not reveal configuration.
- Required configuration is validated.
- Payment, email, database, timezone, session, upload, and booking settings are documented.
