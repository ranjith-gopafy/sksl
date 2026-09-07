# Sara Kinetic Sports Lab (SKSL)
## Online Booking & Payment Platform — MVP Development Plan

**Version:** 1.0  
**Status:** Development Master Plan  
**Timeline:** 4 Weeks  
**Stack:** PHP, HTML5, Tailwind CSS, Vanilla JavaScript, MySQL  
**Local:** XAMPP + phpMyAdmin  
**Production:** Hostinger hPanel  
**Payment:** Razorpay  
**Email:** PHPMailer + SMTP

---

## 1. Project Objective

Build a lightweight, secure, responsive online booking platform for Sara Kinetic Sports Lab (SKSL).

Customers can:

- Create an account.
- Log in using email and password.
- Browse active services.
- Select a service, date and available fixed time slot.
- Complete online payment through Razorpay.
- Receive booking confirmation, payment confirmation and invoice in one consolidated email.
- View upcoming and past bookings.

Admins use a separate `/admin/` area with email OTP login to:

- View and manage bookings.
- Add/edit services.
- Activate/deactivate services.
- Configure price, duration and capacity.
- Upload optional service images.
- Close an entire date.
- View basic booking/revenue information.

All important business and booking rules must be enforced in the backend.

---

## 2. Final Technology Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5 |
| Styling | Tailwind CSS |
| JavaScript | Vanilla JS + Fetch/AJAX |
| Backend | PHP |
| Architecture | Lightweight MVC |
| Database | MySQL |
| DB access | PDO + prepared statements |
| Local development | XAMPP + phpMyAdmin |
| Payment | Razorpay |
| Email | PHPMailer + SMTP |
| Invoice | mPDF |
| Hosting | Hostinger hPanel |
| Scheduled tasks | Hostinger Cron Jobs |

---

## 3. Application Areas

### Customer Area

```text
/
├── index.php
├── register.php
├── login.php
├── logout.php
├── services.php
├── booking.php
├── booking-confirmation.php
├── my-bookings.php
├── booking-details.php
├── profile.php
├── forgot-password.php
├── reset-password.php
├── privacy-policy.php
├── terms.php
├── cancellation-refund.php
└── contact.php
```

### Admin Area

```text
/admin/
├── index.php
├── login.php
├── verify-otp.php
├── logout.php
├── dashboard.php
├── bookings/
├── services/
└── closed-dates/
```

The `/admin/` URL is only an entry point. Every admin page and action must verify an authenticated admin session.

---

## 4. Customer Authentication

### Registration

Initial fields:

- Full name
- Email
- Mobile number
- Password
- Confirm password

Additional profile fields will be added only after client confirmation.

V1 uses email + password. Mobile OTP verification is not required.

### Login

- Email + password.
- Secure password hashing.
- Secure session.
- Session ID regeneration after authentication.
- Logout/session destruction.

### Forgot Password

- Forgot-password form.
- Secure reset token.
- Token expiry.
- Single-use token.
- New password creation.

---

## 5. Admin Authentication

Admin has a separate authentication flow:

```text
/admin/login.php
        |
        v
Admin enters email
        |
        v
Generate 6-digit OTP
        |
        v
Send OTP by email
        |
        v
Admin enters OTP
        |
        v
Verify OTP
        |
        v
Admin Dashboard
```

OTP rules:

- 6 digits.
- 5-minute expiry.
- One-time use.
- Previous active OTP invalidated when a new OTP is generated.
- OTP request rate limiting.
- OTP stored securely.
- OTPs must not be written to application logs.

---

## 6. Initial Services

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

GST is fixed at 18% and is not configurable from the admin panel.

---

## 7. Service Management

Admin can:

- Add service.
- Edit service.
- Activate/deactivate service.
- Set price.
- Set duration.
- Set capacity.
- Upload/replace optional service image.

Image behaviour:

```text
Image provided
    -> Display service image

No image
    -> Do not display an empty image placeholder
```

Deactivating a service must not delete historical bookings.

Example:

```text
Ice Bath
Active -> OFF

New bookings -> Not allowed
Existing bookings -> Remain intact
Past history -> Remains intact
```

---

## 8. Facility & Operating Rules

Operating hours:

**6:00 AM – 10:00 PM**

Timezone:

**Asia/Kolkata (IST)**

Admin can mark a specific date as closed.

A closed date prevents new bookings for all services.

Individual slot blocking or partial capacity reduction is not included in V1.

---

## 9. Booking Flow

```text
Home
  |
  v
Services
  |
  v
Login / Register
  |
  v
Select Service
  |
  v
Select Date
  |
  v
Fetch Availability
  |
  v
Select Fixed Slot
  |
  v
Review Booking
  |
  v
Accept Terms
  |
  v
Create Temporary Hold
  |
  v
Create Razorpay Order
  |
  v
Razorpay Checkout
  |
  v
Server-side Payment Verification
  |
  v
Confirm Booking
  |
  v
Generate Invoice
  |
  v
Send Consolidated Email
  |
  v
Booking Confirmation
```

One service per booking.

Customers may make multiple separate, non-overlapping bookings.

---

## 10. Dynamic Slot Generation

Slots are generated dynamically by the backend.

Availability considers:

```text
Operating hours
+
Service duration
+
Check-in/check-out buffer
+
Existing bookings
+
Active payment holds
+
Capacity
+
Closed dates
+
Service status
```

Slots should not be permanently pre-created as large sets of database records.

The backend calculates the available slots for the requested service/date.

---

## 11. Buffer Logic

Current business assumption:

```text
5 min check-in
+
Service duration
+
5 min checkout
```

Example:

```text
Ice Bath = 10 min

5 min check-in
10 min service
5 min checkout
----------------
20 min operational occupancy
```

The client still needs to confirm the exact relationship between this operational buffer and the instruction to arrive 10 minutes early.

The final rule must be approved before the booking algorithm is locked.

---

## 12. Capacity Protection

Example:

```text
Ice Bath capacity = 8

Current confirmed bookings = 7

Available = 1
```

When the final available capacity is taken:

```text
8 / 8
Slot = Fully Booked
```

The backend must re-check capacity before confirming a booking.

The frontend must never be trusted to determine final availability.

---

## 13. Concurrent Booking Protection

Example:

```text
Ice Bath
Capacity = 8

Current bookings = 7

Customer A -> requests final capacity
Customer B -> requests final capacity
```

The system must ensure only one request receives the final available capacity.

Use:

- Database transactions.
- Appropriate locking/atomic operations.
- Temporary booking holds.
- Final availability validation.

---

## 14. Temporary Booking Hold

Payment hold duration:

**10 minutes**

Flow:

```text
Available slot
     |
     v
Temporary hold
     |
     v
Razorpay payment
```

Successful payment:

```text
Hold -> Confirmed Booking
```

Failed/expired payment:

```text
Hold -> Expired/Released
Capacity -> Available again
```

The application will also use a Hostinger cron job to clean up expired holds.

Availability logic must independently ignore expired holds so delayed cron execution cannot incorrectly block availability.

---

## 15. Razorpay Payment Architecture

Razorpay is handled through the PHP backend.

```text
Validate availability
        |
        v
Create temporary hold
        |
        v
Create Razorpay order
        |
        v
Razorpay Checkout
        |
        v
Payment
        |
        v
Server-side verification
        |
        v
Webhook reconciliation
        |
        v
Confirm booking
```

Never trust only the browser payment response.

The backend must verify the payment and maintain the authoritative payment status.

The Razorpay secret key must never be exposed to JavaScript/browser code.

---

## 16. Payment & Booking Status

### Payment

```text
pending
paid
failed
refunded
```

### Booking

```text
pending
confirmed
cancelled
completed
expired
```

Cancellation/refund rules will be finalized with the client.

---

## 17. GST & Convenience Fee

GST is fixed at **18%**.

Example:

```text
Base price: ₹449.00
GST: ₹80.82
Total: ₹529.82
```

All calculations must happen on the backend.

The frontend should display backend-calculated values.

Convenience-fee treatment is pending client confirmation.

The database should support a convenience-fee amount without making it mandatory.

---

## 18. Invoice / Payment Receipt

After successful payment:

```text
Payment verified
        |
        v
Generate PDF invoice/receipt
        |
        v
Attach to customer email
```

Potential invoice fields:

- Business name.
- Invoice number.
- Booking ID.
- Customer name.
- Customer email.
- Customer mobile.
- Service.
- Booking date/time.
- Base amount.
- GST.
- Convenience fee, if applicable.
- Total paid.
- Razorpay payment ID.
- Payment date.
- Business/tax details.

Final invoice/tax details will be collected from the client.

---

## 19. Customer Email

One consolidated email should contain:

- Booking confirmation.
- Payment confirmation.
- Invoice/receipt.
- Final approved arrival/facility instructions.

V1 does not include automated booking reminder emails.

---

## 20. Admin Notifications

When a booking is successfully confirmed:

```text
New Booking
    |
    +--> Admin Dashboard
    |
    +--> Admin Email
```

Admin email should include:

- Booking ID.
- Customer.
- Service.
- Date.
- Time.
- Amount.
- Payment status.
- Booking status.

---

## 21. Customer Dashboard

### Upcoming Bookings

Display:

- Booking ID.
- Service.
- Date.
- Time.
- Amount.
- Booking status.
- Payment status.
- View details.

### Past Bookings

Display the same essential information for historical bookings.

### Profile

Customer can view/edit approved profile information.

---

## 22. Admin Dashboard

Keep the dashboard lightweight.

Suggested statistics:

- Today's bookings.
- Upcoming bookings.
- Total bookings.
- Today's revenue.

Recent booking list:

- Booking ID.
- Customer.
- Service.
- Date.
- Time.
- Amount.
- Payment status.
- Booking status.

---

## 23. Admin Booking Management

Admin can:

- View bookings.
- Search bookings.
- Filter bookings.
- View customer details.
- View payment details.
- Cancel booking.
- Mark booking completed.

Filters:

```text
Date
Service
Booking Status
Payment Status
```

---

## 24. Admin Service Management

Admin can:

- Add service.
- Edit service.
- Activate/deactivate service.
- Upload image.
- Set price.
- Set duration.
- Set capacity.

Historical bookings must retain their original price/duration values.

---

## 25. Database Structure

Core tables:

```text
admins
users
services
closed_dates
bookings
booking_holds
payments
password_resets
admin_otps
```

Optional:

```text
email_logs
```

---

## 26. Suggested Database Fields

### users

```text
id
name
email
mobile
password_hash
status
created_at
updated_at
```

### admins

```text
id
name
email
status
created_at
updated_at
```

### admin_otps

```text
id
admin_id
otp_hash
expires_at
used_at
attempts
created_at
```

### services

```text
id
name
slug
description
price
gst_percent
duration_minutes
capacity
image
status
created_at
updated_at
```

### closed_dates

```text
id
closed_date
reason
created_at
```

### bookings

```text
id
booking_reference
user_id
service_id
booking_date
start_time
end_time
service_duration_minutes
checkin_buffer_minutes
checkout_buffer_minutes
base_amount
gst_amount
convenience_fee
total_amount
booking_status
payment_status
created_at
updated_at
```

### booking_holds

```text
id
booking_reference
user_id
service_id
booking_date
start_time
end_time
expires_at
status
created_at
```

### payments

```text
id
booking_id
razorpay_order_id
razorpay_payment_id
razorpay_signature
amount
currency
status
gateway_response
paid_at
created_at
updated_at
```

### password_resets

```text
id
user_id
token_hash
expires_at
used_at
created_at
```

---

## 27. Lightweight MVC Folder Structure

```text
sksl/
│
├── app/
│   ├── controllers/
│   ├── models/
│   ├── services/
│   ├── middleware/
│   ├── helpers/
│   └── views/
│
├── admin/
│   ├── index.php
│   ├── login.php
│   ├── verify-otp.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── bookings/
│   ├── services/
│   └── closed-dates/
│
├── config/
│   ├── app.php
│   ├── database.php
│   ├── mail.php
│   └── razorpay.php
│
├── public/
│   └── assets/
│       ├── css/
│       ├── js/
│       └── images/
│
├── routes/
│   └── web.php
│
├── storage/
│   ├── logs/
│   ├── invoices/
│   └── uploads/
│
├── cron/
│   └── expire-holds.php
│
├── vendor/
├── .env
├── .gitignore
├── composer.json
└── index.php
```

---

## 28. Recommended PHP Services

```text
BookingService
AvailabilityService
PaymentService
EmailService
InvoiceService
```

Business logic should live in these services instead of being duplicated across PHP page files.

---

## 29. API / AJAX Actions

### Customer

```text
POST /api/register
POST /api/login
POST /api/logout
POST /api/forgot-password
POST /api/reset-password

GET  /api/services
GET  /api/availability

POST /api/bookings/hold
GET  /api/bookings
GET  /api/bookings/{id}

POST /api/payment/create-order
POST /api/payment/verify
POST /api/payment/webhook
```

### Admin

```text
POST /admin/api/request-otp
POST /admin/api/verify-otp

GET  /admin/api/bookings
GET  /admin/api/bookings/{id}

GET  /admin/api/services
POST /admin/api/services
PUT  /admin/api/services/{id}
POST /admin/api/services/{id}/toggle

GET  /admin/api/closed-dates
POST /admin/api/closed-dates
DELETE /admin/api/closed-dates/{id}
```

Exact routing can be adapted to the lightweight MVC implementation.

---

## 30. Backend Validation

Validate all important data server-side:

- Email.
- Mobile.
- Password.
- Service ID.
- Date.
- Time.
- Service status.
- Closed date.
- Capacity.
- Booking overlap.
- Amount.
- Payment state.
- Customer authorization.
- Admin authorization.

Never trust browser-submitted:

```text
price
gst
total_amount
capacity
booking_status
payment_status
```

---

## 31. Security Implementation

Implement:

- `password_hash()`.
- `password_verify()`.
- PDO prepared statements.
- CSRF protection.
- Secure sessions.
- Session regeneration.
- HttpOnly cookies.
- SameSite cookies.
- Login rate limiting.
- Admin OTP rate limiting.
- XSS output escaping.
- Secure file uploads.
- Server-side payment verification.
- Razorpay webhook verification.
- Authorization checks.
- Production error hiding.
- Secure environment variables.

Never expose:

- Database credentials.
- Razorpay secret.
- SMTP password.
- PHP stack traces.
- SQL errors.
- OTPs.
- Sensitive payment information.

---

## 32. File Upload Security

Service images must be validated for:

- MIME type.
- Extension.
- File size.
- Actual image validity.

Use generated filenames.

Do not allow executable PHP files to be uploaded.

---

## 33. Tailwind CSS

Use Tailwind CLI/build process.

```text
resources/
└── css/
    └── input.css

public/
└── assets/
    └── css/
        └── app.css
```

Do not use Tailwind CDN for production.

---

## 34. JavaScript Strategy

Use Vanilla JavaScript + Fetch API for:

- Availability loading.
- Slot selection.
- Booking hold.
- Form submission.
- Admin filtering.
- Service status actions.
- Razorpay initialization.

Keep all business rules on the backend.

---

## 35. Local Development Setup

Use:

```text
XAMPP
├── Apache
├── PHP
└── MySQL
```

Example project:

```text
C:\xampp\htdocs\sksl\
```

Use phpMyAdmin for local database inspection.

Development sequence:

```text
Create database
      ↓
Create schema
      ↓
Configure environment
      ↓
Install Composer dependencies
      ↓
Build modules
      ↓
Test locally
```

---

# 36. Four-Week Development Plan

## Week 1 — Foundation, UI & Authentication

### Days 1–2

- Final requirement confirmation.
- Freeze client business rules.
- Git repository.
- Project structure.
- XAMPP setup.
- MySQL schema.
- Composer.
- Environment configuration.
- MVC foundation.
- Tailwind build.

### Days 3–4

- Home page.
- Header/footer.
- Services UI.
- Responsive design.
- Common UI components.

### Days 5–7

- Customer registration.
- Customer login.
- Logout.
- Sessions.
- Password hashing.
- Forgot password.
- Reset password.
- Profile foundation.

---

## Week 2 — Admin, Services & Booking Engine

### Days 8–9

- Admin login.
- OTP generation.
- OTP email.
- OTP verification.
- Admin session.
- Dashboard foundation.

### Days 10–11

- Service CRUD.
- Activate/deactivate.
- Price.
- Duration.
- Capacity.
- Image upload.

### Days 12–14

- Date selection.
- Closed dates.
- Operating hours.
- Dynamic slot generation.
- Capacity calculation.
- Buffer logic.
- Customer overlap prevention.
- Temporary holds.

---

## Week 3 — Payment, Invoice & Email

### Days 15–16

- Razorpay order creation.
- Checkout.
- Payment response.
- Signature verification.

### Day 17

- Webhook.
- Payment reconciliation.
- Duplicate callback handling.
- Failed payment handling.

### Days 18–19

- Booking confirmation.
- Booking reference.
- Invoice PDF.
- Payment record.
- Historical pricing.

### Days 20–21

- PHPMailer.
- SMTP.
- Consolidated customer email.
- Invoice attachment.
- Admin booking email.

---

## Week 4 — Dashboards, Testing & Deployment

### Days 22–23

- Upcoming bookings.
- Past bookings.
- Booking details.
- Profile.

### Days 24–25

- Admin booking management.
- Search.
- Filters.
- Cancel.
- Mark completed.
- Closed dates.
- Dashboard statistics.

### Day 26

Security testing:

- Authentication.
- Authorization.
- CSRF.
- SQL injection.
- XSS.
- File uploads.
- Session security.
- Rate limiting.

### Day 27

Booking/payment testing:

- Successful payment.
- Failed payment.
- Expired hold.
- Duplicate callback.
- Full capacity.
- Last available unit.
- Concurrent booking.
- Closed date.
- Deactivated service.
- Overlapping booking.

### Day 28

Production:

- Hostinger setup.
- Database.
- Environment.
- SSL.
- Cron.
- SMTP.
- Razorpay production credentials.
- Webhook.
- Final UAT.
- Deployment.

---

## 37. Testing Checklist

### Customer

- [ ] Registration.
- [ ] Duplicate email.
- [ ] Login.
- [ ] Logout.
- [ ] Forgot password.
- [ ] Reset password.
- [ ] Profile.
- [ ] Service selection.
- [ ] Date selection.
- [ ] Slot availability.
- [ ] Booking.
- [ ] Payment.
- [ ] Confirmation.
- [ ] Invoice.
- [ ] Email.
- [ ] Upcoming bookings.
- [ ] Past bookings.

### Admin

- [ ] OTP login.
- [ ] Expired OTP.
- [ ] Reused OTP.
- [ ] OTP rate limiting.
- [ ] Dashboard.
- [ ] Booking list.
- [ ] Search.
- [ ] Filters.
- [ ] Booking details.
- [ ] Cancel.
- [ ] Mark completed.
- [ ] Add service.
- [ ] Edit service.
- [ ] Activate/deactivate.
- [ ] Image upload.
- [ ] Close date.

### Booking Engine

- [ ] Capacity respected.
- [ ] Fully booked slot.
- [ ] Temporary hold.
- [ ] Hold expiry.
- [ ] Closed date.
- [ ] Inactive service.
- [ ] Outside operating hours.
- [ ] Customer overlap prevention.
- [ ] Concurrent booking protection.

### Payment

- [ ] Successful payment.
- [ ] Failed payment.
- [ ] Cancelled checkout.
- [ ] Payment timeout.
- [ ] Invalid signature.
- [ ] Webhook.
- [ ] Duplicate callback.
- [ ] Amount mismatch.
- [ ] Correct payment status.

---

## 38. Hostinger Deployment

Production checklist:

- Client domain connected.
- Hosting configured.
- SSL active.
- Production database created.
- Database backup.
- PHP version/extensions checked.
- Composer dependencies installed.
- `.env` configured.
- SMTP configured.
- Razorpay production credentials configured.
- Webhook configured.
- Cron configured.
- Admin account configured.
- Services imported.
- Capacities verified.
- Business hours verified.
- Legal pages published.
- End-to-end booking tested.

---

## 39. Cron Job

Use Hostinger Cron Jobs for:

```text
cron/expire-holds.php
```

Purpose:

```text
Find active holds
      ↓
Check expires_at
      ↓
Mark expired
      ↓
Release capacity
```

Availability logic must independently ignore expired holds so delayed cron execution cannot incorrectly block a slot.

---

## 40. Environment Variables

Example:

```text
APP_ENV=production
APP_URL=https://example.com

DB_HOST=
DB_NAME=
DB_USER=
DB_PASSWORD=

RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

SMTP_HOST=
SMTP_PORT=
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_FROM_EMAIL=
SMTP_FROM_NAME=
```

`.env` must never be committed to Git.

---

## 41. Git Workflow

Recommended:

```text
main
develop
feature/*
```

Flow:

```text
feature
   ↓
development
   ↓
testing
   ↓
develop
   ↓
UAT
   ↓
main
   ↓
production
```

Example commits:

```text
feat: add customer registration
feat: add service management
feat: implement dynamic availability
feat: integrate razorpay
fix: prevent duplicate booking
fix: expire payment holds
```

---

## 42. AI-Assisted Development Workflow

AI can accelerate development, but every generated feature must be reviewed and tested.

Workflow:

```text
Requirement
      ↓
Developer defines expected behaviour
      ↓
AI implementation
      ↓
Code review
      ↓
Testing
      ↓
Security review
      ↓
Commit
```

AI is useful for:

- Boilerplate.
- CRUD.
- Tailwind UI.
- SQL/schema.
- Validation.
- Test cases.
- Email templates.
- Documentation.
- Debugging.

Manually review AI-generated code especially for:

- Authentication.
- Authorization.
- Payment verification.
- Booking concurrency.
- SQL.
- File uploads.
- Security.

---

## 43. MVP Scope Control

Not included in V1:

- Android application.
- iOS application.
- WhatsApp automation.
- SMS automation.
- Memberships.
- Subscriptions.
- Packages.
- Loyalty points.
- Coupons/discounts.
- Multi-location support.
- Advanced analytics.
- Staff accounts/scheduling.
- CRM.
- Individual slot blocking.
- Partial capacity reduction.
- Advanced refund automation.
- Automated booking reminders.

New requirements discovered after scope approval should be treated as change requests.

---

## 44. Pending Client Decisions

Before finalizing the booking engine, collect:

1. Exact arrival/check-in instruction.
2. Exact interpretation of the 5-minute check-in and checkout buffers.
3. Whether customer-facing service duration excludes buffers.
4. Final last-slot/10 PM rule.
5. Maximum advance booking period.
6. Same-day booking cutoff.
7. Cancellation policy.
8. Rescheduling policy.
9. Refund policy.
10. Convenience-fee treatment.
11. Customer profile fields.
12. Safety declaration requirements.
13. Final Terms & Conditions.
14. Privacy Policy content.
15. Cancellation/Refund Policy content.
16. Invoice/tax/business details.
17. Admin email.
18. SMTP/email provider details.

These must be approved before production.

---

## 45. Definition of Done

The MVP is complete when:

- Customer registration/login works.
- Password reset works.
- Active services display correctly.
- Date and fixed slot selection works.
- Backend correctly calculates availability.
- Capacity cannot be exceeded.
- Temporary payment holds work.
- Razorpay payment works.
- Payment is verified server-side.
- Successful payment creates a confirmed booking.
- Failed/expired payment releases the hold.
- Invoice PDF is generated.
- Consolidated confirmation email is sent.
- Admin receives new booking notification.
- Admin can manage services.
- Admin can manage bookings.
- Admin can close dates.
- Customer can view upcoming/past bookings.
- Required legal pages are available.
- Security testing is completed.
- Hostinger deployment is verified.

---

## 46. Final MVP Architecture

```text
                    CUSTOMER
                       |
                       v
             HTML + Tailwind + JS
                       |
                       v
                  PHP MVC APP
                       |
        +--------------+--------------+
        |              |              |
        v              v              v
   Availability     Booking        Payment
      Service        Service       Service
        |              |              |
        +--------------+--------------+
                       |
                       v
                     MySQL
                       |
          +------------+------------+
          |                         |
          v                         v
       Razorpay                 PHPMailer
          |                         |
          v                         v
       Payment                  Customer/Admin
      Verification                Email
          |
          v
      Confirmed Booking
          |
          v
       mPDF Invoice


                    ADMIN
                      |
                      v
                   /admin/
                      |
                      v
               Email OTP Login
                      |
                      v
              Admin Dashboard
                /         \
               v           v
          Bookings      Services
                            |
                            v
                       Closed Dates
```

## Final Development Goal

The MVP should remain **simple, secure, fast and production-ready**.

The core business flow must be reliable:

```text
Customer
   ↓
Login
   ↓
Select Service
   ↓
Select Date
   ↓
Select Available Slot
   ↓
Temporary Hold
   ↓
Razorpay
   ↓
Server-side Verification
   ↓
Confirmed Booking
   ↓
Invoice + Confirmation Email
   ↓
Customer Dashboard

                    ↘
                     Admin Dashboard
                     + Email Notification
```

This architecture keeps the MVP lightweight while providing a clean foundation for future enhancements.
