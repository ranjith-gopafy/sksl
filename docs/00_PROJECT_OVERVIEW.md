# SKSL — Project Overview

## Project
Sara Kinetic Sports Lab (SKSL)

**Tagline:** Recover. Recharge. Perform.

**Type:** Online Service Booking & Payment MVP

## Objective
Build a secure production-ready MVP for customer registration, service selection, dynamic availability, booking, Razorpay payment, invoice generation, email confirmation, customer booking history, and admin management.

## Approved Stack
- PHP
- Lightweight MVC
- PDO + MySQL
- HTML5
- Tailwind CSS (CLI/build, not CDN in production)
- Vanilla JavaScript
- Fetch API/AJAX
- Razorpay
- PHPMailer + SMTP
- mPDF
- XAMPP + phpMyAdmin
- Composer + Git
- Hostinger shared hosting + hPanel
- Hostinger Cron Jobs

Do not introduce Laravel, React, Vue, Node.js, or another major framework without explicit approval.

## Initial Services

| Service | Price | GST | Duration | Capacity |
|---|---:|---:|---:|---:|
| Spa | ₹999 | 18% | 30 min | 4 |
| Sauna | ₹499 | 18% | 10 min | 4 |
| Steam | ₹299 | 18% | 10 min | 8 |
| Ice Bath | ₹449 | 18% | 10 min | 8 |
| Hot Bath | ₹199 | 18% | 10 min | 4 |
| Endless Pool | ₹499 | 18% | 30 min | 2 |
| Cycle | ₹149 | 18% | 15 min | 1 |
| Treadmill | ₹149 | 18% | 15 min | 1 |
| Walker | ₹149 | 18% | 15 min | 1 |
| Lap Pool | ₹499 | 18% | 45 min | 1 |

GST is fixed at 18% in V1.

## Operating Rules
- Facility hours: 06:00 AM–10:00 PM
- Timezone: Asia/Kolkata
- Slots are generated dynamically.
- Closed dates apply to all services.
- One service per booking.
- Multiple separate non-overlapping bookings may be made on the same day.
- Temporary payment hold: 10 minutes.
- Capacity must be enforced server-side with concurrency protection.

## Customer
- Email/password registration and login
- Logout
- Forgot/reset password
- Service browsing
- Availability
- Booking
- Payment
- Upcoming/past bookings
- Profile

Customers may access only their own records.

## Admin
Separate `/admin/` area.

- Email OTP login
- 6-digit OTP
- 5-minute expiry
- One-time use
- Dashboard
- Booking search/filter/view/cancel/complete
- Service CRUD/status/image/price/duration/capacity
- Full-date closure

One admin role in V1.

## Payment Flow
1. Validate availability server-side.
2. Create temporary hold.
3. Create Razorpay order.
4. Customer pays.
5. Verify payment server-side.
6. Reconcile webhook idempotently.
7. Confirm booking.
8. Generate invoice/receipt.
9. Send one consolidated customer email.
10. Notify admin.

Never trust browser-supplied amount, price, GST, duration, capacity, or payment success.

## Security Baseline
- PDO prepared statements
- password_hash/password_verify
- Secure sessions and session regeneration
- HttpOnly/Secure/SameSite cookies
- CSRF protection
- Server-side validation
- Output escaping
- Authorization checks
- Login/OTP rate limiting
- Secure password reset tokens
- Secure file uploads
- HTTPS
- `.env` for secrets
- Safe production error handling
- Razorpay signature/webhook verification
- Concurrency protection

## Explicit V1 Exclusions
- Memberships
- Subscriptions
- Packages
- Loyalty
- Coupons/discounts
- Multi-location
- Advanced analytics
- Staff accounts/scheduling
- CRM
- Individual slot blocking
- Partial capacity reduction
- Advanced refund automation
- Automated reminders
- WhatsApp/SMS
- Android/iOS apps

## Pending Client Decisions
Do not silently assume:
- Arrival/check-in/checkout/cleaning buffer
- 10-minute early arrival treatment
- Last slot before 10 PM
- Advance booking period
- Same-day cutoff
- Cancellation/rescheduling/refund rules
- Convenience fee treatment
- Formal invoice vs receipt and tax details
- Additional profile fields
- Safety declaration and policy text
- Admin email/SMTP details

See `docs/requirements/PENDING_CLIENT_DECISIONS.md`.

## Definition of Done
The MVP is complete only when authentication, service management, availability, capacity/concurrency, holds, Razorpay verification/webhooks, booking confirmation, invoice, email, dashboards, closed dates, security tests, and deployment are all working and documented.
