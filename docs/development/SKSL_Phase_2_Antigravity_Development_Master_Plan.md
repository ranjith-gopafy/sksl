# Sara Kinetic Sports Lab (SKSL)

## Phase 2 --- Extended Booking Engine & Advanced Booking Features

**Version:** 2.0\
**Status:** Phase 2 Development Master Plan\
**Purpose:** Implementation source of truth for Antigravity
AI/development.\
**Relationship to Phase 1:** Extend the existing Phase 1 booking system.
Do not build a separate booking engine.

------------------------------------------------------------------------

# 1. IMPORTANT INSTRUCTIONS FOR ANTIGRAVITY

You are extending an existing SKSL production-oriented booking platform.

## Rules

1.  **Do not rebuild the application from scratch.**
2.  **Do not replace the existing stack.**
3.  **Do not create a second booking engine.**
4.  Read the existing SKSL Phase 1 code and documentation before
    modifying anything.
5.  Preserve existing customer accounts, bookings, payments, invoices
    and admin data.
6.  Use database migrations for all schema changes.
7.  Do not silently delete or rename existing production data.
8.  Keep Phase 1 booking behaviour working unless a Phase 2 requirement
    explicitly changes it.
9.  All important rules must remain enforced server-side.
10. Do not invent client business rules that are marked as pending.
11. If the current implementation conflicts with this document, inspect
    the code/database first and report the conflict before making
    destructive changes.
12. Do not add unrelated features because they appear useful or
    professional.
13. Keep the approved stack: PHP + MySQL + Tailwind CSS + Vanilla JS +
    Razorpay + PHPMailer + mPDF + Hostinger.
14. Before major coding, inspect the existing project and produce a
    short implementation/status report.
15. Then implement Phase 2 incrementally and test each module.

The first report must contain:

-   Current architecture.
-   Current database schema.
-   Existing booking flow.
-   Existing availability logic.
-   Existing APIs/AJAX.
-   Existing admin features.
-   Existing payment flow.
-   Files/classes that will be modified.
-   Migrations required.
-   Risks and conflicts.
-   Recommended implementation order.

------------------------------------------------------------------------

# 2. PHASE 2 OBJECTIVE

Phase 2 extends the existing SKSL booking engine to support:

1.  Configurable duration and pricing per modality.
2.  Physiotherapy and Psychologist appointment booking without online
    payment.
3.  Admin-controlled professional schedules and date/weekday
    availability.
4.  Combo packages with modality credits and expiry.
5.  Package usage tracking.
6.  Admin Visited / No Show handling.
7.  No-show entitlement restoration.
8.  Manual time-range blocking per modality.
9.  Recurring manual blocking.
10. One unified booking engine supporting standard, professional and
    package bookings.

Core principle:

``` text
ONE BOOKING ENGINE
        |
        +-- Standard booking
        |
        +-- Professional booking
        |
        +-- Package booking
        |
        +-- Shared availability/conflict logic
```

------------------------------------------------------------------------

# 3. EXISTING PHASE 1 BASELINE

Phase 1 uses:

  Layer             Technology
  ----------------- ---------------------------
  Frontend          HTML5
  Styling           Tailwind CSS
  JavaScript        Vanilla JS + Fetch/AJAX
  Backend           PHP
  Architecture      Lightweight MVC
  Database          MySQL
  DB access         PDO + prepared statements
  Local             XAMPP + phpMyAdmin
  Payment           Razorpay
  Email             PHPMailer + SMTP
  Invoice           mPDF
  Hosting           Hostinger hPanel
  Scheduled tasks   Hostinger Cron Jobs

Existing core rules:

-   Facility hours: **6:00 AM--10:00 PM**.
-   Timezone: **Asia/Kolkata**.
-   One service per standard booking.
-   Customers may make separate non-overlapping bookings.
-   Server-side capacity enforcement is mandatory.
-   Existing temporary payment hold: **10 minutes**.
-   Razorpay verification is server-side.
-   Webhook reconciliation is required.
-   Admin uses `/admin/` with email OTP.
-   Existing closed-date functionality remains.
-   Existing customer/admin dashboards remain.
-   Existing invoice/email functionality remains.

Do not remove these behaviours.

------------------------------------------------------------------------

# 4. VARIABLE DURATION AND PRICING

Each modality can be configured as either fixed duration or multiple
durations.

Example:

``` text
Spa
30 minutes → Admin-defined price
60 minutes → Admin-defined price
90 minutes → Admin-defined price
```

Admin explicitly enters every duration price. Do not automatically
calculate longer-duration prices.

Admin must be able to:

-   Add duration option.
-   Edit duration option.
-   Set duration in minutes.
-   Set price.
-   Activate/deactivate duration option.

The selected duration controls availability and locks the complete
requested time range.

Example:

``` text
Selected: Spa / 60 minutes
Requested: 10:00–11:00
```

A conflicting booking must prevent that selection.

Historical bookings must retain the selected duration and price even if
the service is later changed.

------------------------------------------------------------------------

# 5. DURATION DATA MODEL

Prefer a normalized model:

``` text
services
service_duration_options
```

Suggested duration table:

``` text
service_duration_options
------------------------
id
service_id
duration_minutes
price
status
created_at
updated_at
```

If Phase 1 currently stores `price` and `duration_minutes` directly on
`services`, migrate carefully. Do not break existing services or
historical bookings.

Existing services should initially receive one duration option matching
their current Phase 1 configuration.

------------------------------------------------------------------------

# 6. HISTORICAL BOOKING SNAPSHOT

Every booking must preserve the values used when it was created.

At minimum:

``` text
service_name_snapshot
duration_minutes
base_amount
gst_percent
gst_amount
convenience_fee
total_amount
```

Changing a service price or duration option must not rewrite historical
bookings.

------------------------------------------------------------------------

# 7. PHYSIOTHERAPY & PSYCHOLOGIST

Physiotherapy and Psychologist are appointment-based services using the
same booking engine.

They reuse:

-   Customer authentication.
-   Availability calculation.
-   Overlap validation.
-   Booking table.
-   Booking status.
-   Customer dashboard.
-   Admin dashboard.

## Payment

These bookings do not require online payment.

Customer flow:

``` text
Login
  ↓
Select professional service
  ↓
Select date
  ↓
Select available appointment
  ↓
Review
  ↓
Confirm
  ↓
Booking confirmed
```

Do not open Razorpay for these bookings.

Represent payment as `not_required` or the equivalent existing status
supported by the current schema. Do not change the existing status
vocabulary without checking the code first.

------------------------------------------------------------------------

# 8. PROFESSIONAL AVAILABILITY

Admin controls professional availability.

Support:

## Recurring weekday schedules

Example:

``` text
Physiotherapy
Monday–Friday
10:00 AM–1:00 PM
4:00 PM–7:00 PM
```

## Specific date schedules

Example:

``` text
20 October
2:00 PM–6:00 PM
```

## Specific date exceptions

Example:

``` text
Normal Monday schedule
        ↓
20 October exception
        ↓
Unavailable
```

Specific-date rules must take precedence over recurring rules where
applicable.

------------------------------------------------------------------------

# 9. PROFESSIONAL DURATION

Professional appointment duration must be configurable.

Example only:

``` text
Physiotherapy → 30 minutes
Psychologist → 45 minutes
```

Do not hardcode these example values. Store them through the same
service/duration model used by the booking engine.

------------------------------------------------------------------------

# 10. REUSABLE AVAILABILITY MODEL

Prefer a reusable schedule model rather than separate logic for every
professional.

Conceptually:

``` text
availability_rules
------------------
id
service_id
rule_type
weekday
specific_date
start_time
end_time
status
created_at
updated_at
```

Possible rule types:

``` text
recurring
specific_date
```

If the existing Phase 1 architecture already contains equivalent
availability functionality, extend it instead of replacing it.

------------------------------------------------------------------------

# 11. COMBO PACKAGES

Add a separate customer-facing Combo Packages section.

Example:

``` text
Recovery Package
Price: Admin-defined
Validity: Admin-defined

Includes:
Spa × 2
Sauna × 2
Ice Bath × 2
Steam × 1
```

A customer purchases a package once and uses individual modality credits
later.

They do not need to schedule all package sessions at purchase time.

------------------------------------------------------------------------

# 12. PACKAGE DATA MODEL

Recommended conceptual structure:

``` text
packages
--------
id
name
description
price
gst_percent
validity_days
status
created_at
updated_at

package_items
-------------
id
package_id
service_id
quantity
created_at

customer_packages
-----------------
id
user_id
package_id
purchase_reference
purchased_at
expires_at
status
base_amount
gst_amount
total_amount
payment_status
created_at
updated_at

customer_package_credits
------------------------
id
customer_package_id
service_id
allocated_quantity
used_quantity
remaining_quantity
created_at
updated_at
```

Use the existing project naming conventions if different.

------------------------------------------------------------------------

# 13. PACKAGE EXPIRY

Packages must have an expiry date.

After expiry:

``` text
remaining credits → cannot be used
```

Historical usage remains visible.

Do not delete expired package records.

The application must validate expiry using the current timestamp/date;
do not rely only on a cron job to prevent expired usage.

------------------------------------------------------------------------

# 14. PACKAGE PURCHASE

Package purchase is a paid transaction.

Use the existing Razorpay architecture:

``` text
Select package
   ↓
Create order
   ↓
Razorpay
   ↓
Server-side verification
   ↓
Confirm package purchase
   ↓
Create package credits
```

Package credits must never be created solely because the browser reports
success.

If the existing payment table can safely support package payments,
extend it with a payment source/type rather than creating an unnecessary
second payment system.

------------------------------------------------------------------------

# 15. PACKAGE BOOKING FLOW

``` text
Customer Dashboard
        ↓
My Packages
        ↓
Select Package
        ↓
Select Eligible Modality
        ↓
Select Duration if applicable
        ↓
Select Date
        ↓
Select Available Slot
        ↓
Confirm
        ↓
Reserve/consume package credit
        ↓
Create booking
```

Package credit does not bypass normal availability rules.

A package booking must still respect:

-   capacity
-   overlap rules
-   closed dates
-   manual blocks
-   service status
-   professional availability
-   selected duration

------------------------------------------------------------------------

# 16. PACKAGE CREDIT ACCOUNTING

Example:

``` text
Spa credits = 2

Customer books Spa
        ↓
Credit becomes reserved/consumed according to the implemented transaction model
```

For a no-show:

``` text
Spa credits = 1

Customer books Spa
        ↓
Admin marks No Show
        ↓
Credit restored
        ↓
Spa credits = 2
```

Credit changes must be transactional and idempotent.

Recommended conceptual states:

``` text
Available
Reserved
Consumed
Restored
Expired
```

A quantity-based implementation is acceptable if it produces the same
safe behaviour.

------------------------------------------------------------------------

# 17. PACKAGE CONCURRENCY

Protect the final available package credit with a transaction and row
locking/atomic update.

Example:

``` text
Remaining credits = 1

Customer A → booking request
Customer B → booking request
```

Only one request may consume the final credit.

Never rely on frontend credit counts.

------------------------------------------------------------------------

# 18. BOOKING TYPE AND PAYMENT MODE

Do not create separate booking tables for each booking type.

Recommended `booking_type` values:

``` text
standard
professional
package
```

Recommended payment modes:

``` text
payment_required
payment_not_required
package_credit
```

Examples:

``` text
Normal Spa
booking_type = standard
payment_mode = payment_required

Physiotherapy
booking_type = professional
payment_mode = payment_not_required

Spa through package
booking_type = package
payment_mode = package_credit
```

Keep booking type and payment mode separate.

------------------------------------------------------------------------

# 19. MANUAL SLOT BLOCKING

Admin can block a time range for a specific modality.

Example:

``` text
Service: Spa
Date: 20 October
From: 2:00 PM
To: 4:00 PM
Reason: Maintenance
```

Any booking overlapping the range must be rejected.

Do not store every blocked slot as separate records. Store a time range.

Suggested fields:

``` text
manual_blocks
-------------
id
service_id
block_type
specific_date
weekday
start_time
end_time
reason
status
created_at
updated_at
```

------------------------------------------------------------------------

# 20. RECURRING MANUAL BLOCKS

Support recurring blocks.

Examples:

``` text
Every Sunday
Spa
6:00 AM–10:00 PM
```

or:

``` text
Every Monday
Physiotherapy
2:00 PM–4:00 PM
```

Evaluate recurring rules during availability calculation rather than
creating unnecessary future rows.

------------------------------------------------------------------------

# 21. OVERLAP RULE

Use one overlap rule everywhere:

``` text
existing_start < requested_end
AND
existing_end > requested_start
```

Apply it to:

-   confirmed bookings
-   active holds
-   manual blocks
-   professional appointments
-   package bookings

Do not implement separate overlap algorithms.

------------------------------------------------------------------------

# 22. AVAILABILITY PRIORITY

Recommended availability calculation:

``` text
1. Service active?
        ↓
2. Date globally closed?
        ↓
3. Service availability schedule
        ↓
4. Recurring/specific-date professional rules
        ↓
5. Manual specific-date blocks
        ↓
6. Recurring manual blocks
        ↓
7. Existing confirmed bookings
        ↓
8. Active non-expired holds
        ↓
9. Capacity
        ↓
10. Return available slots
```

Specific-date schedules/exceptions override recurring schedules where
applicable.

Manual blocks override otherwise valid availability.

------------------------------------------------------------------------

# 23. VARIABLE DURATION SLOT GENERATION

Availability must be generated for the selected duration.

Example:

``` text
Spa
Selected duration = 60 minutes

10:00–11:00 → available
10:30–11:30 → unavailable because of conflict
11:00–12:00 → available
```

Do not assume that a 30-minute slot can simply be combined with another
slot unless that matches the existing implementation.

The requested duration must be part of the availability calculation.

------------------------------------------------------------------------

# 24. CAPACITY

Capacity remains service-specific.

Example:

``` text
Spa capacity = 4

10:00–10:30
Confirmed = 3
Available = 1
```

For longer bookings, capacity must be checked across the complete
requested duration/operational occupancy.

The frontend must never be trusted for final capacity.

------------------------------------------------------------------------

# 25. TEMPORARY HOLDS

Existing 10-minute payment holds remain for paid standard bookings and
package purchases.

Professional no-payment bookings do not require a Razorpay payment hold,
but they still require transactional conflict protection.

Professional booking flow:

``` text
Availability check
        ↓
Database transaction
        ↓
Final conflict check
        ↓
Create reservation
        ↓
Commit
```

------------------------------------------------------------------------

# 26. CANCELLATION & REFUND --- CLIENT DECISION REQUIRED

Do not invent these rules.

Before implementing cancellation-related Phase 2 behaviour, ask SKSL to
confirm:

1.  How many hours/days before an appointment can a customer cancel?
2.  Is cancellation free?
3.  Is there a partial refund?
4.  Is there no refund after a cutoff?
5.  What happens to a package credit after cancellation?
6.  Does early cancellation restore the package credit?
7.  What happens to a package credit after late cancellation?
8.  What happens when Admin cancels a booking?
9.  Should paid bookings be automatically refunded through Razorpay?
10. Should refunds be manually processed by Admin?
11. What happens to a professional no-payment booking after
    cancellation?
12. Is rescheduling allowed?
13. If rescheduling is allowed, does it release and re-use the same
    package credit?

Until the client confirms these rules:

``` text
DO NOT implement assumed cancellation/refund behaviour.
```

------------------------------------------------------------------------

# 27. ADMIN EXTENSIONS

Extend the existing admin panel with:

``` text
Services
  └── Duration Options

Availability
  ├── Professional Schedules
  ├── Specific Dates
  └── Manual Blocks

Packages
  ├── Package List
  ├── Create/Edit Package
  ├── Package Items
  └── Package Purchases

Bookings
  ├── Standard
  ├── Professional
  └── Package
```

Admin must be able to:

-   Configure duration and price.
-   Configure professional schedules.
-   Configure specific-date exceptions.
-   Create/edit/delete/deactivate manual blocks.
-   Create/edit/activate/deactivate packages.
-   View package purchases.
-   View package balances.
-   Mark package bookings Visited or No Show.

------------------------------------------------------------------------

# 28. ADMIN DURATION UI

Example:

``` text
Spa
Capacity: 4

Duration Options

[Active] 30 min — ₹999
[Active] 60 min — Admin-defined price
[Active] 90 min — Admin-defined price
```

Do not use example prices above unless provided by the client.

------------------------------------------------------------------------

# 29. ADMIN PROFESSIONAL SCHEDULE UI

Admin should be able to configure:

``` text
Service: Physiotherapy
Schedule type: Recurring
Days: Monday–Friday
Time: 10:00 AM–1:00 PM
Time: 4:00 PM–7:00 PM
```

Specific date example:

``` text
Date: 20 Oct 2026
Availability: 2:00 PM–6:00 PM
```

Admin should also be able to mark a specific date unavailable.

------------------------------------------------------------------------

# 30. ADMIN MANUAL BLOCK UI

Specific date:

``` text
Service: Spa
Date: 20 Oct 2026
Start: 02:00 PM
End: 04:00 PM
Reason: Maintenance
[ Block Time ]
```

Recurring:

``` text
Service: Physiotherapy
Day: Monday
Start: 02:00 PM
End: 04:00 PM
Reason: Staff unavailable
```

------------------------------------------------------------------------

# 31. ADMIN PACKAGE UI

Admin can create:

``` text
Name
Description
Price
GST
Validity
Status
```

Then add package items:

``` text
Spa × 2
Sauna × 2
Ice Bath × 2
```

Purchased packages must retain historical package terms. Editing the
package later must not rewrite old purchases.

------------------------------------------------------------------------

# 32. ADMIN PACKAGE USAGE

Booking details should show:

``` text
Booking Type: Package
Package: Recovery Package
Service: Spa
Credit: 1 of 2
Status: Confirmed
Visit Status: Pending
```

Actions:

``` text
[ Mark Visited ]
[ Mark No Show ]
```

Visited consumes the entitlement.

No Show restores the entitlement once.

Duplicate No Show actions must not restore twice.

------------------------------------------------------------------------

# 33. CUSTOMER PACKAGE DASHBOARD

Example:

``` text
My Packages

Recovery Package
Expires: 09 Nov 2026

Spa
Used: 1
Remaining: 1

Sauna
Used: 0
Remaining: 2

Ice Bath
Used: 2
Remaining: 0
```

A service can be booked using package credit only when:

``` text
remaining_quantity > 0
AND
package not expired
AND
service active
AND
availability exists
```

------------------------------------------------------------------------

# 34. CUSTOMER BOOKING FLOWS

## Standard

``` text
Service
  ↓
Duration
  ↓
Date
  ↓
Available slots
  ↓
Review
  ↓
Payment
```

## Professional

``` text
Professional service
  ↓
Date
  ↓
Available appointment
  ↓
Review
  ↓
Confirm without payment
```

## Package

``` text
My Package
  ↓
Eligible service
  ↓
Duration if applicable
  ↓
Date
  ↓
Available slot
  ↓
Confirm using package credit
```

Reuse existing booking UI components where possible.

------------------------------------------------------------------------

# 35. DATABASE MIGRATION STRATEGY

Before migration:

1.  Back up database.
2.  Inspect current schema.
3.  Identify foreign keys.
4.  Identify existing booking/payment dependencies.
5.  Create migration.
6.  Run locally.
7.  Verify existing data.
8.  Run Phase 1 regression.
9.  Apply to production only after testing.

Possible new tables:

``` text
service_duration_options
availability_rules
manual_blocks
packages
package_items
customer_packages
customer_package_credits
```

Possible `bookings` additions:

``` text
booking_type
payment_mode
duration_option_id
package_credit_id
visit_status
```

Do not add duplicate fields when an equivalent existing implementation
already exists.

------------------------------------------------------------------------

# 36. RECOMMENDED BOOKING FIELDS

Conceptually:

``` text
bookings
--------
id
booking_reference
user_id
service_id
duration_option_id
booking_type
payment_mode
booking_date
start_time
end_time
service_duration_minutes

service_name_snapshot
base_amount
gst_percent
gst_amount
convenience_fee
total_amount

package_credit_id

booking_status
payment_status
visit_status

created_at
updated_at
```

Suggested `visit_status`:

``` text
pending
visited
no_show
```

Adapt to existing naming conventions.

------------------------------------------------------------------------

# 37. PAYMENT ARCHITECTURE FOR PACKAGES

A package purchase must have its own payment/order reference.

If the existing payment table supports multiple payment sources safely,
extend it with a source/type such as:

``` text
booking
package
```

Do not create a second payment verification system.

Razorpay secrets must never reach browser code.

------------------------------------------------------------------------

# 38. API/AJAX EXTENSIONS

Adapt endpoints to the existing routing system.

Customer examples:

``` text
GET  /api/services/{id}/durations
GET  /api/availability
POST /api/bookings/hold
POST /api/bookings/create
GET  /api/packages
GET  /api/packages/{id}
POST /api/packages/{id}/purchase
POST /api/packages/{id}/book
```

Admin examples:

``` text
GET  /admin/api/services/{id}/durations
POST /admin/api/services/{id}/durations
PUT  /admin/api/durations/{id}
POST /admin/api/durations/{id}/toggle

GET  /admin/api/availability
POST /admin/api/availability
PUT  /admin/api/availability/{id}
DELETE /admin/api/availability/{id}

GET  /admin/api/blocks
POST /admin/api/blocks
PUT  /admin/api/blocks/{id}
DELETE /admin/api/blocks/{id}

GET  /admin/api/packages
POST /admin/api/packages
PUT  /admin/api/packages/{id}
POST /admin/api/packages/{id}/toggle

GET  /admin/api/package-purchases
GET  /admin/api/package-purchases/{id}

POST /admin/api/bookings/{id}/visited
POST /admin/api/bookings/{id}/no-show
```

Exact routes may differ according to the current project.

------------------------------------------------------------------------

# 39. SERVER-SIDE VALIDATION

Never trust browser-submitted:

``` text
service_id
duration_option_id
price
GST
total
capacity
booking_type
payment_mode
package_id
package_credit_id
start_time
end_time
```

For package booking:

``` text
Authenticated user
        ↓
Package belongs to user
        ↓
Package active
        ↓
Package not expired
        ↓
Service belongs to package
        ↓
Credit available
        ↓
Service active
        ↓
Duration valid
        ↓
Date/time valid
        ↓
Availability valid
        ↓
No block conflict
        ↓
No booking conflict
        ↓
Atomic credit reservation
        ↓
Booking created
```

------------------------------------------------------------------------

# 40. SECURITY REQUIREMENTS

Continue Phase 1 security:

-   `password_hash()`
-   `password_verify()`
-   PDO prepared statements
-   CSRF protection
-   Secure sessions
-   Session ID regeneration
-   HttpOnly cookies
-   SameSite cookies
-   Login rate limiting
-   Admin OTP rate limiting
-   XSS output escaping
-   Authorization checks
-   Server-side payment verification
-   Razorpay webhook verification
-   Secure environment variables
-   Production error hiding

Phase 2 must additionally protect:

-   Package ownership.
-   Package expiry.
-   Package credit concurrency.
-   Duplicate no-show restoration.
-   Duration manipulation.
-   Price manipulation.
-   Booking-type manipulation.
-   Professional schedule manipulation.
-   Manual block authorization.

Never expose DB credentials, Razorpay secrets, SMTP passwords, OTPs, SQL
errors, PHP stack traces or sensitive payment information.

------------------------------------------------------------------------

# 41. CONCURRENCY AND IDEMPOTENCY

Concurrency protection is mandatory for:

-   Standard capacity.
-   Professional appointments.
-   Package credits.
-   Manual block conflict.

Use:

``` text
database transaction
+
appropriate row locks / atomic updates
+
final server-side conflict check
```

Idempotent actions include:

``` text
Repeated Razorpay webhook → one payment result
Repeated No Show → one credit restoration
Repeated Visited → one visit state
Repeated package booking request → no duplicate credit consumption
Repeated payment callback → no duplicate booking
```

------------------------------------------------------------------------

# 42. CRON

Keep the existing:

``` text
cron/expire-holds.php
```

Package expiry may have a cleanup task if useful, but expiry must be
checked directly during package booking.

A delayed cron must never allow expired credits to be used.

------------------------------------------------------------------------

# 43. TESTING PLAN

## Duration

-   Fixed duration service.
-   Multiple duration service.
-   30/60/90 minute options.
-   Correct duration-specific price.
-   Invalid duration.
-   Disabled duration.
-   Price change.
-   Historical snapshot.
-   Longer-duration overlap.

## Professional

-   Available weekday.
-   Unavailable weekday.
-   Specific-date schedule.
-   Specific-date exception.
-   Multiple time ranges.
-   Overlapping appointment.
-   Concurrent appointment requests.
-   Booking without payment.
-   Customer dashboard.
-   Admin dashboard.

## Packages

-   Package creation.
-   Package purchase.
-   Successful payment.
-   Failed payment.
-   Credit creation.
-   Expiry.
-   Expired package rejection.
-   Remaining quantity.
-   Package booking.
-   Final-credit concurrency.
-   Visited.
-   No Show.
-   No-show restoration.
-   Duplicate No Show.
-   Package ownership.
-   Historical package terms.

## Manual Blocks

-   Specific-date block.
-   Recurring block.
-   Full-day block.
-   Partial block.
-   Booking before block.
-   Booking after block.
-   Booking overlapping block.
-   Edit.
-   Delete/deactivate.
-   Concurrent conflict.

## Regression

After each major module test:

-   Customer login.
-   Admin login.
-   Existing standard booking.
-   Razorpay.
-   Webhook.
-   Invoice.
-   Email.
-   Dashboard.
-   Closed dates.

Phase 2 must not break Phase 1.

------------------------------------------------------------------------

# 44. FIVE-WEEK DEVELOPMENT TIMELINE

## Week 1 --- Database & Core Booking Engine Extension

### Days 1--2

-   Inspect existing implementation.
-   Review database and migrations.
-   Review booking/availability/payment services.
-   Identify conflicts.
-   Finalize migration design.

### Days 3--5

-   Duration option structure.
-   Booking type/payment mode extensions.
-   Availability rule structure.
-   Professional schedule structure.
-   Manual block structure.
-   Package structure.
-   Package credit structure.

**Output:** Phase 2 database and engine foundation.

## Week 2 --- Variable Duration & Professional Booking

### Days 6--8

-   Admin duration management.
-   Customer duration selection.
-   Duration-aware availability.
-   Duration-specific pricing.
-   Historical snapshots.

### Days 9--10

-   Recurring professional schedules.
-   Specific-date schedules.
-   Exceptions.
-   No-payment professional booking.
-   Conflict protection.

**Output:** Variable-duration and professional booking working.

## Week 3 --- Combo Packages

### Days 11--13

-   Package CRUD.
-   Package items.
-   Pricing.
-   Validity.
-   Package purchase.
-   Razorpay.
-   Payment verification.

### Days 14--15

-   Customer package dashboard.
-   Credits.
-   Package booking.
-   Credit concurrency.
-   Expiry.

**Output:** Packages, credits, expiry and package bookings working.

## Week 4 --- Manual Blocking & Admin Operations

### Days 16--18

-   Specific-date blocks.
-   Recurring blocks.
-   Availability integration.
-   Admin block UI.

### Days 19--20

-   Visited.
-   No Show.
-   Credit restoration.
-   Idempotency.
-   Admin package management.
-   Booking dashboard enhancements.

**Output:** Manual blocking and package operational controls working.

## Week 5 --- QA, Security & Deployment

### Days 21--23

-   Full functional testing.
-   Concurrency testing.
-   Package testing.
-   Payment testing.
-   Regression testing.

### Day 24

-   Security review.
-   Authorization tests.
-   CSRF/XSS/SQL injection checks.
-   Package ownership tests.
-   Payment verification tests.

### Day 25

-   UAT fixes.
-   Production backup.
-   Production migration.
-   Hostinger deployment.
-   Cron verification.
-   Final end-to-end tests.

**Output:** Production-ready Phase 2.

------------------------------------------------------------------------

# 45. DEFINITION OF DONE

## Duration

-   [ ] Admin configures duration options.
-   [ ] Admin configures price per duration.
-   [ ] Customer selects duration.
-   [ ] Correct price is displayed.
-   [ ] Availability respects duration.
-   [ ] Historical values are preserved.

## Professional

-   [ ] Physiotherapy is available.
-   [ ] Psychologist is available.
-   [ ] Admin configures recurring availability.
-   [ ] Admin configures specific-date availability.
-   [ ] Admin configures exceptions.
-   [ ] Customer books without payment.
-   [ ] Professional appointments cannot overlap.

## Packages

-   [ ] Admin creates package.
-   [ ] Admin adds service quantities.
-   [ ] Package purchase works.
-   [ ] Razorpay payment is verified.
-   [ ] Credits are created only after successful payment.
-   [ ] Package has expiry.
-   [ ] Expired package cannot be used.
-   [ ] Customer sees remaining credits.
-   [ ] Package booking works.
-   [ ] Credit concurrency is protected.
-   [ ] Admin marks Visited.
-   [ ] Admin marks No Show.
-   [ ] No Show restores credit once only.

## Manual Blocks

-   [ ] Specific-date block works.
-   [ ] Recurring block works.
-   [ ] Admin edits blocks.
-   [ ] Admin removes/deactivates blocks.
-   [ ] Blocked time cannot be booked.
-   [ ] Block conflict is enforced server-side.

## Regression

-   [ ] Existing Phase 1 booking still works.
-   [ ] Existing Razorpay payment still works.
-   [ ] Existing invoice still works.
-   [ ] Existing email still works.
-   [ ] Existing customer dashboard still works.
-   [ ] Existing admin dashboard still works.
-   [ ] Existing closed dates still work.

------------------------------------------------------------------------

# 46. PHASE 2 OUT OF SCOPE

Unless separately approved, do not add:

-   Mobile apps.
-   WhatsApp automation.
-   SMS automation.
-   Memberships.
-   Loyalty points.
-   Coupons.
-   Multi-location.
-   Staff payroll.
-   Advanced CRM.
-   Advanced analytics.
-   AI recommendations.
-   Automatic cancellation/refund rules without client approval.
-   Unapproved package rules.
-   Unapproved professional payment collection.
-   Unapproved rescheduling rules.

New requirements are change requests.

------------------------------------------------------------------------

# 47. AI-ASSISTED DEVELOPMENT WORKFLOW

Use this workflow for every important feature:

``` text
Requirement
    ↓
Inspect existing code
    ↓
Plan change
    ↓
Implement
    ↓
Run tests
    ↓
Review database effects
    ↓
Review security
    ↓
Run regression
    ↓
Commit
```

Manually review AI-generated code for:

-   migrations
-   payment verification
-   booking concurrency
-   package credit accounting
-   authorization
-   sessions
-   SQL
-   uploads
-   webhooks

------------------------------------------------------------------------

# 48. GIT WORKFLOW

Recommended branches:

``` text
main
develop
feature/*
```

Examples:

``` text
feature/phase2-duration-options
feature/phase2-professional-schedules
feature/phase2-manual-blocks
feature/phase2-packages
feature/phase2-package-usage
```

Example commits:

``` text
feat: add service duration options
feat: add professional availability rules
feat: add manual booking blocks
feat: add combo packages
feat: add package credits
feat: add package no-show restoration
fix: prevent duplicate package credit consumption
fix: prevent booking during blocked ranges
```

------------------------------------------------------------------------

# 49. PRODUCTION DEPLOYMENT

Before deployment:

1.  Back up production database.
2.  Back up `.env` safely.
3.  Test migrations locally.
4.  Test staging if available.
5.  Deploy code.
6.  Run migrations.
7.  Verify existing Phase 1 records.
8.  Verify services.
9.  Verify duration options.
10. Verify availability.
11. Verify Razorpay.
12. Verify webhook.
13. Verify email.
14. Verify invoice generation.
15. Verify cron.
16. Test standard booking.
17. Test professional booking.
18. Test package booking.
19. Test manual block.
20. Verify logs.

Never deploy an untested database migration directly to production.

------------------------------------------------------------------------

# 50. FINAL ARCHITECTURE

``` text
                         CUSTOMER
                            |
                            v
                  HTML + Tailwind + JS
                            |
                            v
                       PHP MVC APP
                            |
       +--------------------+--------------------+
       |                    |                    |
       v                    v                    v
 Availability           Booking             Package
   Service              Service              Service
       |                    |                    |
       +--------------------+--------------------+
                            |
                            v
                          MySQL
                            |
        +-------------------+-------------------+
        |                   |                   |
        v                   v                   v
     Razorpay           PHPMailer             mPDF
        |                   |                   |
        v                   v                   v
     Payments         Notifications          Invoice

                         ADMIN
                           |
                           v
                       /admin/
                           |
                           v
                    Admin Authentication
                           |
                           v
                     Admin Dashboard
                           |
       +-------------------+--------------------+
       |                   |                    |
       v                   v                    v
    Services          Availability           Packages
       |                   |                    |
       v                   v                    v
 Durations/Prices   Schedules/Blocks       Credits/Usage
                           |
                           v
                    Booking Engine
```

------------------------------------------------------------------------

# 51. FIRST ACTION FOR ANTIGRAVITY

Do not start by creating Phase 2 tables blindly.

Start with:

``` text
SKSL PHASE 2 IMPLEMENTATION START REPORT

1. Existing project structure
2. Existing database tables
3. Existing migrations
4. Existing booking flow
5. Existing availability logic
6. Existing payment flow
7. Existing admin flow
8. Existing customer flow
9. Existing reusable services/classes
10. Required Phase 2 changes
11. Required database migrations
12. Potential conflicts
13. Recommended implementation order
14. Risks
```

After review, implement in this order:

``` text
Phase 2.1
→ Database + booking engine foundation

Phase 2.2
→ Duration + professional availability

Phase 2.3
→ Packages

Phase 2.4
→ Manual blocks + admin operations

Phase 2.5
→ Testing + security + deployment
```

Do not expand the scope beyond this document.

**Final goal: extend the existing SKSL booking engine safely and
cleanly. Do not build a bigger system just for the sake of adding
features.**
