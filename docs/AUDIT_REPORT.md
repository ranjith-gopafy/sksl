# Sara Kinetic Sports Lab — Source Audit

**Date:** 26 September 2026  
**Scope:** Application source in this repository only. The live site was not reviewed.  
**Method:** Static review of routing, authentication, booking, payments, invoices, admin tools, views, schema, and deployment configuration. No application code was changed. No requests were sent to a running server, and dependency CVE databases were not queried.

This is a PHP 8.1 booking platform (Razorpay, email, GST invoices, customer accounts, admin OTP). The document root is expected to be `public/`.

## Verdict

The core design is sound: prepared SQL, server-side prices, ownership checks, hashed passwords, hashed reset tokens, and a real CSRF check. It is not safe to take payments or issue tax invoices until the items in **Critical** and **High** are fixed. Several of those items are silent: the code looks protected, then skips the check when a secret is missing, or writes a legal promise the payment code does not keep.

| Severity | Count | Meaning |
| --- | ---: | --- |
| Critical | 3 | Money, tax documents, or a broken checkout on a clean install |
| High | 8 | Account abuse, refunds, file upload, or deployment exposure |
| Medium | 16 | Booking rules, privacy accuracy, sessions, email, operations |
| Low | 12 | Hardening and content quality |

---

## What already holds

- Database access uses PDO prepared statements with `PDO::ATTR_EMULATE_PREPARES` turned off (`config/database.php`). Filter SQL is built from fixed fragments, not from raw user column names.
- Customer passwords use `password_hash()` / `password_verify()`. Password-reset tokens are 32 random bytes, stored as SHA-256, expire in one hour, and are single-use (`app/services/AuthService.php`, `app/models/PasswordResetModel.php`).
- Admin login is email OTP. The code is hashed with bcrypt, expires in five minutes, and locks after five wrong tries (`app/services/AdminAuthService.php`).
- Login regenerates the session id. Cookies are HttpOnly and `SameSite=Strict` (`bootstrap.php`).
- POST, PUT, PATCH, and DELETE require a session CSRF token compared with `hash_equals()`, except the Razorpay webhook (`app/middleware/CsrfMiddleware.php`).
- Slot holds lock the service row with `SELECT … FOR UPDATE` and count confirmed bookings plus unexpired holds (`app/services/BookingService.php`).
- Checkout amounts are calculated on the server. The browser cannot set the price (`app/services/PaymentService.php`). When the Razorpay secret is present, the checkout signature is checked with `hash_equals()`.
- Customers can only see, cancel, and download invoices for their own bookings. Admins are a separate session flag (`app/controllers/PaymentController.php`, `app/services/InvoiceService.php`).
- Most HTML output goes through `h()` / `htmlspecialchars`.
- `.env` is gitignored. `.env.example` contains no live secrets.
- Security response headers are set: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
- Phase tests and Playwright specs exist for auth, holds, payment, invoices, and admin flows.

---

## Critical

### C1. Payment confirmation is skipped when Razorpay secrets are empty

`PaymentService::verifyPayment()` only checks the Razorpay signature when `RAZORPAY_KEY_SECRET` is non-empty. `handleWebhook()` only checks `X-Razorpay-Signature` when `RAZORPAY_WEBHOOK_SECRET` is non-empty. If either value is blank, the method continues and marks the booking confirmed and paid.

`createOrder()` has the same pattern: empty keys create an `order_mock_…` id instead of refusing the request. There is no `APP_ENV=production` guard.

`.env.example` ships all three Razorpay values blank. A production deploy that forgets the webhook secret, or that leaves the key secret empty, lets a caller confirm a hold without a real payment. The two secrets are independent: checkout keys can be set while the webhook secret is still empty.

**Where:** `app/services/PaymentService.php` (order creation around the empty-key branch; signature block in `verifyPayment()`; signature block in `handleWebhook()`).  
**Also:** the webhook route is exempt from CSRF, which is correct only when the signature is mandatory.

**Needed:** If the app is not explicitly in local mock mode, refuse to create an order, refuse to verify, and reject the webhook when the matching secret is missing. Compare the captured amount to the stored order amount before confirming.

### C2. Creating a booking does not match the bookings table

`database/migrations/20260907_006_create_bookings.sql` defines `service_name_snapshot` and `gst_percent` as `NOT NULL` with no default.

`BookingModel::create()` does not insert either column. On MySQL with strict mode (the normal production setting), `PaymentService::createOrder()` fails at the insert, so checkout cannot create the pending booking.

`database/sksl_export.sql` quietly adds defaults (`''` and `18.00`). A database built from the export will insert, but the service-name snapshot stays empty because the application never writes it. Later pages read the live service name through a join, so a rename in admin rewrites history. The migration comment says those fields are frozen at booking time. They are not.

**Where:** `app/models/BookingModel.php` `create()`; `app/services/PaymentService.php` `createOrder()`; migration `20260907_006`; `database/sksl_export.sql`.

**Needed:** One schema, and an insert that writes the snapshot name, GST percent, and buffers taken from the service at hold time.

### C3. Tax invoices use placeholder legal identity

Every PDF is built from hardcoded values in `InvoiceService::getInvoiceData()`:

- GSTIN `33AATCS1234F1Z9`. State code `33` is Tamil Nadu. The facility is described as Bengaluru, Karnataka (state code `29`). The number pattern is a sample, not a Karnataka GSTIN.
- Phone `+91 98765 43210`.
- Address “Athletic Recovery & Thermal Therapy Center, Bengaluru…”, which does not match the contact page.
- Email `support@sk-sports-lab.com`.
- CGST and SGST are fixed at 9% + 9%, not read from the booking’s `gst_percent`.
- If no payment row is found, the PDF still prints payment id `ONLINE-GATEWAY`.

The contact page uses `support@sksl.in` and a different address. Confirmation emails link to `https://sk-sports-lab.com/my-bookings` instead of `app_url()`.

Issuing GST invoices with this identity is a tax and consumer-law problem, not a cosmetic one.

**Where:** `app/services/InvoiceService.php`; `app/views/pages/contact.php`; `app/services/EmailService.php`.

**Needed:** Business legal name, GSTIN, address, SAC, and support contacts from configuration that has been checked against the registration certificate. Split GST from the stored rate. Do not emit an invoice when payment is not `paid`.

---

## High

### H1. Cancellation promises a refund the code never makes

The public policy says an eligible cancellation is credited or refunded to the original payment method (`app/views/pages/cancellation-refund.php`).

`CustomerBookingController::cancel()` only sets `booking_status` to `cancelled`. It does not:

- call Razorpay’s refund API
- set `payment_status` to `refunded`
- send a cancellation email
- release anything beyond the status change (the hold is already gone after payment)

Admin status changes have the same gap (`AdminBookingController::updateStatus()`). An admin can also move a cancelled or pending booking back to `confirmed` with no payment check.

`verifyPayment()` and the webhook also confirm a booking without checking that it is still `pending`. A late webhook, or a verify call, can mark a cancelled booking confirmed again and send another “you’re booked” email.

**Needed:** One status machine: pending → confirmed → completed, and confirmed → cancelled. Cancellation of a paid booking must create a Razorpay refund (or an explicit manual-refund state) and set `payment_status`. Ignore capture events for bookings that are already cancelled.

### H2. A paid booking can be charged again

`payments.booking_id` is not unique. `createOrder()` reuses an order only when the latest payment row is `pending`. If that row is `paid`, it creates another Razorpay order and another payment row for the same booking.

**Where:** `app/services/PaymentService.php` `createOrder()`; `app/models/PaymentModel.php` `findByBookingId()`; migration `20260907_008`.

**Needed:** If the booking is already `paid` or `confirmed`, return the existing confirmation and do not open a new order.

### H3. Uploaded images are stored in the web root with the client’s extension

Admin service images are saved to `public/uploads/services/`. The extension comes from the original filename. The check is `mime_content_type()` only. There is no `public/uploads/.htaccess` denying script execution. Config says uploads belong in `storage/uploads/services/` (`config/app.php`); the controller does not use that path.

A file whose bytes look like an image but whose name ends in `.php` (or `.phtml`, `.phar`) is written where Apache will execute PHP, if the host does that for the uploads folder.

**Where:** `app/controllers/AdminServiceController.php` `update()`.

**Needed:** Derive the extension from the detected type, reject anything that is not a real image (`getimagesize()`), and store files outside the document root or with PHP execution disabled.

### H4. Login and reset limits live only in the session

`RateLimit` stores counters in `$_SESSION`. A new session, or no cookie, starts a fresh counter. Customer login (5 / 15 minutes) and forgot-password (3 / 15 minutes) can be brute-forced or used to mail-bomb a known address.

Admin OTP requests have a second, real limit in the database (five codes per 15 minutes per admin). That part holds. The session limiter in front of it does not.

**Where:** `app/helpers/RateLimit.php`; `app/services/AuthService.php`.

**Needed:** Count attempts by account and by IP in the database or a shared store, not in the caller’s session.

### H5. Hero image URL is written into HTML without encoding

Banner text is escaped. The image URL is not:

```php
src="<?= asset($slideImage) ?>"
```

`asset()` concatenates the string. It does not escape quotes. An admin-saved `image_url` containing a quote can break out of the attribute on the public homepage. That page is shown to every visitor, so a compromised admin account becomes a stored cross-site scripting bug against customers.

`cta_link` values that start with `http` are escaped, which is good. They are still accepted as any external URL, so the homepage button can be pointed at another site. Values that do not start with `http` are prefixed with the app URL, which blocks `javascript:` links.

**Where:** `app/views/pages/home.php`; `app/views/admin/banner.php`; `app/controllers/AdminBannerController.php` (no allow-list on the link or the path).

**Needed:** Escape every attribute with `h()`. Allow only relative image paths and relative or same-site links.

### H6. The document root must be `public/`, and nothing enforces that

There is no `.htaccess` at the repository root. `public/.htaccess` blocks `.env`, `.sql`, `.log`, and `.md` only for files inside `public/`. If Hostinger’s document root is the project folder, these are directly requestable:

- `.env`
- `storage/invoices/` (customer invoices)
- `storage/logs/mail.log` (failed mail, including admin OTP subject and body)
- `tests/diagnostic/check_credentials.php`
- `database/*.sql`

**Needed:** Document root = `public/`. Deny PHP execution and HTTP access to `storage/`, `cron/`, `tests/`, and `database/`. Do not deploy diagnostic scripts.

### H7. Session cookie is not Secure unless the environment says so

`SESSION_SECURE` defaults to false (`bootstrap.php`, `.env.example`). On HTTPS, a missed setting sends the session cookie on plain HTTP. The privacy policy states that cookies are secure. That is only true after this flag is turned on.

There is no `Strict-Transport-Security` header and no Content-Security-Policy.

**Needed:** Force `Secure` when `APP_ENV` is production. Add HSTS on HTTPS. Add a CSP that allows the Razorpay checkout script and Google Fonts and nothing else.

### H8. Withdrawn — image files are present

Correction: `public/images/sksl-logo.png`, `public/images/hero-banner.jpg`, and `public/images/services/*.jpg` exist in the working tree. The first pass did not see them. Confirm they are committed so a fresh deploy includes them; that is the only remaining action.

---

## Medium

### Booking rules disagree with each other

Three different advance-window mechanisms exist, and the one that actually reserves a slot uses none of them.

| Surface | Rule |
| --- | --- |
| Booking page date picker | Hard-coded 30 days (`app/views/pages/booking.php`) |
| `GET /api/availability` | `MAX_ADVANCE_DAYS`, default 30 (`AvailabilityService`) |
| `config/app.php` | `ADVANCE_BOOKING_DAYS`, default null, unused by the booking service |
| `POST /api/bookings/hold` | Any future date, any `HH:MM` inside opening hours |

`SAME_DAY_CUTOFF_MINUTES` is also unused. A hold is rejected only when the start time is already in the past.

The availability grid steps by duration plus buffer. The hold endpoint does not require the start time to be one of those grid times, and overlap checks do not expand by the buffer. With the default buffer of 0 this is latent. If a buffer is turned on later, the API can still book through the gap.

A signed-in customer can place a hold on every non-overlapping slot of every service. Nothing caps holds per user. Each hold blocks capacity for `BOOKING_HOLD_MINUTES` (default 10). That is enough to empty the calendar for a short period.

A customer may also book two different modalities at the same clock time. Overlap is checked per service only. That may be intentional. It is not written down as a decision.

Same-day sessions that have already ended stay in the “upcoming” tab until the next calendar day. The upcoming query treats `booking_date >= today` as upcoming even when `end_time` has passed (`BookingModel::findByUser()`).

Pending booking rows are never expired. Abandoned checkouts leave `pending` rows forever. Capacity is not stuck, because only `confirmed` bookings count, but the admin list fills with them.

The webhook confirms a booking and does not release the hold. Until the hold expires, that seat is counted twice (confirmed booking plus active hold).

Both verify and the webhook send the confirmation email and the admin email. A normal Razorpay payment often hits both paths, so customers and staff can receive duplicates.

### Authentication and sessions

- Registration tells the user when an email is already taken. Forgot-password does not. Attackers can harvest registered emails from `/register`.
- Admin OTP request returns a generic success for unknown emails, but a database rate-limit failure only happens for a real admin. That difference reveals which addresses are administrators.
- Password reset and password change do not invalidate other sessions. A stolen session keeps working after the password changes.
- `APP_SECRET` is loaded and never used to sign anything.
- Registration has no rate limit and no email verification. There is no acceptance of the privacy policy or terms on the register form. The booking health declaration is a browser checkbox only; `createHold()` does not check it.
- Password policy is length ≥ 8 and nothing else.
- Customer and admin share one PHP session. Logging in as one role does not clear the other.
- `GET /logout` and `GET /admin/logout` log the user out. `SameSite=Strict` blocks a foreign site from sending the cookie, so this is limited. A same-site request can still log someone out.
- After login, `Location` is the raw `intended_url` taken from `REQUEST_URI` (`AuthController::login()`). CSRF failures redirect to the `Referer` header (`CsrfMiddleware`). Both should allow only same-site relative paths.
- `/health` is public and returns `config('app.env')` plus whether MySQL connected (`public/index.php`).
- Production sets `error_reporting(0)`, which also suppresses errors that should go to the log. `display_errors` off is right; reporting level `E_ALL` with display off is the usual pair.
- A database failure in `getDb()` returns JSON and `die()`s, including for normal page loads.

### Privacy and email

The privacy policy says cookies are secure and that there are no third-party tracking requests. Pages load Google Fonts from `fonts.googleapis.com`, which receives the visitor’s IP. Checkout sends the customer’s name, email, and mobile to Razorpay. Neither processor is named beyond “Razorpay” for card data.

There is no account-deletion or data-export flow.

Failed emails are appended in full to `storage/logs/mail.log`, including the admin OTP, which is also placed in the email subject (`EmailService::sendAdminOtp()`). The `email_logs` table exists and is never written. Successful mail is not recorded there either.

Customer and admin names are interpolated into HTML emails without `htmlspecialchars`.

The fallback admin notification address is `admin@sk-sports-lab.com` when `ADMIN_EMAIL` is empty, not the seeded `admin@sksl.in`.

### Schema and money details

- `users.mobile` is not unique.
- `payments` has no status transition guard inside `markPaid()`. A second call overwrites `razorpay_payment_id`.
- Invoice numbers are `INV-` plus a date plus a CRC32 fragment. They are not a stored sequence, so the same booking can be regenerated, but there is no ledger row that says “this invoice number was issued.”
- Service price `0` is allowed (`price < 0` is the only reject). Capacity must be at least 1.
- GST math rounds to paise (`ServiceModel::calculatePricing()`), and the Razorpay amount uses `round(rupees * 100)`. That part is consistent. The invoice’s fixed 9/9 split is not, if a service’s `gst_percent` is ever not 18.

### Accessibility

- `lang="en"` is set. Many controls have accessible names (carousel, calendar, toast region `aria-live="polite"`).
- There is no skip link.
- Every carousel slide is an `<h1>`, including slides that are visually hidden. One page should have one `h1`.
- The booking service `<select>` is `sr-only` and `aria-hidden`, so the actual service choice may have no accessible name.
- Flash text is passed through `json_encode` into script, which is safe. The toast then assigns `innerHTML`. That is safe only while every message stays free of HTML. Prefer `textContent`.
- Admin booking details put `booking_reference` and `booking_status` into `innerHTML` (`app/views/admin/bookings.php`). Those values are constrained today. They should still be inserted as text.
- Gold `#D6981E` used as small text on white or pale surfaces is likely below WCAG AA contrast. This was not measured in a browser.
- Contact information uses colour and icons without a phone number or a map link.

### SEO and sharing

- The layout sets a title per page and one site-wide meta description. That description is the same on login, booking, legal pages, and admin.
- The PHP app has no canonical URL, Open Graph tags, Twitter card, `robots.txt`, XML sitemap, or LocalBusiness structured data. `coming soon.html` at the repo root has Open Graph tags and is not routed by the application.
- No favicon link in `app/views/layouts/main.php`.
- The homepage nav highlights Home by comparing the path to `/` or `/sksl/public/`. Other environments can fail that check.
- Service and location content is thin for local search: no full street address, no pin code, no phone, and the three public addresses do not match (contact page, invoice, email footer).

### Performance

- Google Fonts CSS is render-blocking in `<head>`. `display=swap` is set, which avoids invisible text, but the request is still on the critical path.
- Hero slides are all in the DOM and all request their images up front. No `width` / `height` (or aspect-ratio reservation) on those images, so layout shifts when they load.
- `public/.htaccess` does not set long-cache headers or compression for `css/app.css` and images.
- `app.css` is the compiled Tailwind file. That is fine if it is purged. Confirm the production file is the minified build (`npm run build`), not the watch output.
- Availability recomputes every slot with several queries each. Acceptable for one day and ten services. It will get slow if this is called in a tight loop or the catalogue grows a lot.

### Deployment and operations

- `cron/expire-holds.php` is safe to run from CLI and is not required for correctness, because holds are filtered with `expires_at > NOW()`. It should still be scheduled so the table does not grow, and it must not be reachable over HTTP.
- `composer.lock` is present. Good.
- `storage/` directories are created at runtime with mode `0775`.
- Tests under `tests/diagnostic/` include credential and SMTP checks. They must not be on a public host.
- `coming soon.html` is unused by the router. Shipping it in the web root would publish a second, older page.

---

## Low

- `X-XSS-Protection` is set. Modern browsers ignore it; it does not replace escaping or CSP.
- Logout does not clear the entire session, only the role keys, then regenerates the id. Prefer `session_unset()` for that role’s keys and a new CSRF token after privilege changes.
- CSRF tokens last for the whole session and are not rotated after login.
- `isApiRequest()` treats any `Accept: application/json` as an API call, including browser form posts that happen to send that header. Responses change shape. CSRF still applies.
- Admin search `LIKE` patterns do not escape `%` and `_`. This is not SQL injection. It does make search wildcards surprising.
- Booking references are exactly 20 characters (`SKSL-` + `YYYYMMDD` + `-` + 6 hex). The column is `VARCHAR(20)`. It fits, with no room left.
- `GuestMiddleware` is applied to login and register pages. Authenticated customers are kept out. Good. It was not re-checked for every edge route in this pass beyond the router list.
- Register, profile, and password forms use labelled inputs. Good. Error text is a flash toast, not tied to the field with `aria-describedby`.
- No `security.txt`.
- No account lock that staff can undo, other than `users.status`. Inactive users are rejected at login. There is no admin UI in the router for disabling a customer. Status can only change if something else writes the column.
- Closed dates block new holds. They do not cancel or refund bookings already confirmed on that date, despite the policy saying affected athletes are notified and refunded.
- The health declaration and “I agree to terms” control is not stored on the booking row.
- `Router` method override via `_method` is limited to PUT and DELETE and still requires CSRF. Low risk.
- 404 and 500 responses are generic. Good. The 500 path for a missing controller is a plain heading, not the branded layout.

---

## Area notes

### Security

The dangerous pattern is fail-open secrets (C1), not missing prepared statements. XSS is mostly handled. The homepage image attribute (H5) and email HTML are the exceptions. Session cookies and rate limits are the other practical account risks (H4, H7). IDOR on bookings and invoices was checked and is enforced for customers.

### Booking

Concurrency for a single service is taken seriously (row lock, overlap math, hold expiry in the query). The holes are off-grid times, no advance-window on the hold itself, no cap on how many holds one account can open, and same-day “upcoming” classification.

### Payments

Server-side amounts and ownership checks are right. Confirmation must fail closed (C1), must not double-charge (H2), and must not resurrect a cancellation (H1). Refunds are a policy commitment with no code behind them.

### Privacy

Collection of name, email, and mobile matches the policy. The policy overclaims cookie security and under-discloses Google Fonts and Razorpay prefill. There is no erasure path. OTP and reset links can land in `mail.log` when SMTP fails.

### Accessibility, SEO, performance, UX

The interface is structured and has a lot of correct labelling. It is not a finished public site yet: missing image files (H8), three conflicting addresses, a contact page with no form and no phone, one meta description for every URL, and no sharing or sitemap metadata. Legal pages exist (privacy, terms, cancellation), which Razorpay onboarding expects, but the cancellation page describes a refund that does not happen.

### Deployment

Hostinger guides under `docs/deployment/` already say to use HTTPS, real Razorpay secrets, and `SESSION_SECURE=true`. The code does not enforce those. A wrong document root is the sharpest operational risk (H6).

---

## Suggested order of work

1. Fail closed on missing Razorpay secrets, and reject webhooks with a bad or missing signature (C1).
2. Make the booking insert match the table and write real snapshots (C2).
3. Replace the invoice GSTIN, address, and phone with the registered business details (C3). Stop generating invoices until that is true.
4. Implement refunds, or change the public policy so it does not promise them. Block confirmations of cancelled bookings (H1, H2).
5. Move uploads out of the executable web root (H3).
6. Move rate limits out of the session (H4).
7. Escape banner URLs and lock the document root to `public/` (H5, H6).
8. Force secure cookies and HSTS in production (H7).
9. Add the real image files, one address, and one domain, then align emails and the contact page (H8 and the content mismatches).

---

## What this audit did not do

- No click-through of a running site, so contrast, focus order, and mobile layout were not measured in a browser.
- No payment against Razorpay test or live keys.
- No `composer audit` / npm advisory run.
- No review of the Hostinger account, DNS, TLS certificate, or actual cron schedule.
- Secrets in a local `.env` were not opened.
