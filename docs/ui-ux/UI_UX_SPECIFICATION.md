# SKSL UI/UX Specification

**Project:** Sara Kinetic Sports Lab (SKSL)  
**Tagline:** Recover. Recharge. Perform.  
**Version:** 1.0  
**Status:** MVP Specification

---

## 1. Purpose

This document defines the UI/UX behavior for the SKSL online booking and payment MVP.

The interface must be simple, mobile-friendly, fast, trustworthy, and focused on one primary action:

> Select a service → choose an available time → confirm details → pay → receive confirmation.

Do not add UI features that are outside the approved MVP scope.

---

## 2. UX Principles

1. **Booking first** — users should reach service selection quickly.
2. **Minimal steps** — avoid unnecessary forms and screens.
3. **Clear pricing** — show base price, GST, applicable fees, and total separately.
4. **Availability clarity** — unavailable slots must be visibly disabled.
5. **Trust** — clearly show booking, payment, and confirmation states.
6. **Mobile-first** — the booking flow must work comfortably on phones.
7. **Accessible controls** — buttons, inputs, labels, errors, and focus states must be usable with keyboard and assistive technology.
8. **No misleading states** — payment success must never be displayed solely because a browser callback says success.
9. **Fast feedback** — loading, processing, success, failure, and empty states must be explicit.
10. **No scope creep** — memberships, coupons, packages, loyalty, WhatsApp/SMS, and other excluded features must not appear in the UI.

---

## 3. Public Website

### 3.1 Home Page

Recommended sections:

- Header/navigation
- Hero section
- Short SKSL introduction
- Services/facilities
- How booking works
- Key benefits
- Call-to-action
- Contact/footer

Primary CTA:

> Book a Service

The exact marketing copy, images, contact information, address, and business details are client-provided content.

### 3.2 Header

Customer-facing navigation may include:

- Home
- Services
- About
- Contact
- Login / Dashboard
- Book Now

Do not expose admin navigation publicly.

### 3.3 Service Listing

Each service card should display:

- Service image, only when an image exists
- Service name
- Short description, if supplied
- Duration
- Starting/base price
- Booking CTA

If no service image exists, do not render an empty image placeholder.

Service data must come from the server.

---

## 4. Customer Authentication UX

### 4.1 Signup

Fields:

- Full name
- Email
- Mobile
- Password
- Confirm password

Requirements:

- Clear labels
- Inline validation
- Password visibility toggle may be provided
- Duplicate email must show a useful message
- Password rules must be communicated before submission
- Server-side validation remains authoritative

No mobile OTP is required for MVP signup.

### 4.2 Login

Fields:

- Email
- Password

Links/actions:

- Forgot password
- Create account

After successful login, redirect the user to the intended booking flow when appropriate.

### 4.3 Forgot Password

Flow:

1. User enters registered email.
2. Server creates a secure, expiring reset token.
3. User receives reset email.
4. User opens reset page.
5. User sets a new password.
6. Token becomes unusable.

Do not reveal whether an email exists through overly specific account-enumeration messages.

---

## 5. Booking UX

### 5.1 Booking Entry

A user may start booking from:

- Home CTA
- Service card
- Services page

If authentication is required and the user is not logged in, send them through login/signup while preserving the intended booking context safely.

### 5.2 Service Selection

Display:

- Service name
- Duration
- Price
- GST
- Capacity information only when useful to the customer
- Service description/image when available

The customer selects exactly one service per booking.

### 5.3 Date Selection

The date picker must:

- Prevent past dates.
- Respect closed dates.
- Respect configured advance-booking rules once finalized.
- Use Asia/Kolkata business timezone.
- Clearly indicate the selected date.

A globally closed date must not show bookable slots.

### 5.4 Slot Selection

Slots are dynamically generated from:

- Facility operating hours
- Service duration
- Existing confirmed/pending bookings
- Active booking holds
- Service capacity
- Closed dates
- Finalized operational rules

Do not store every possible slot as a permanent database record unless a later approved design explicitly requires it.

Slot states:

- Available
- Selected
- Unavailable
- Loading
- Error

Unavailable slots must not be selectable.

### 5.5 Booking Summary

Before payment, show:

- Service
- Date
- Start time
- End time
- Base price
- GST
- Convenience fee, if applicable and approved
- Total amount
- Customer details
- Applicable policy/terms acknowledgement

The displayed total is informational. The server recalculates the authoritative amount.

### 5.6 Hold and Payment

When the customer proceeds:

1. Server validates the booking request.
2. Server checks availability again.
3. Server creates a temporary hold.
4. Hold expires after 10 minutes.
5. Server creates the Razorpay order using the server-calculated amount.
6. Razorpay Checkout opens.
7. Browser callback is processed by the server.
8. Server verifies payment.
9. Webhook may reconcile the transaction.
10. Booking becomes confirmed only after authoritative payment verification.

Show a clear countdown or expiry warning only if implemented reliably. Never claim a hold exists when the server did not create one.

---

## 6. Payment States

### Processing

Message example:

> Processing your payment. Please do not close this page.

Disable duplicate submission.

### Success

Only display confirmed success after server-side verification.

Show:

- Booking reference
- Service
- Date/time
- Amount paid
- Confirmation status
- Invoice/receipt availability
- Email confirmation information

### Failure

Show:

- Payment failed/could not be confirmed
- Booking/hold status if relevant
- Retry action when safe
- Support/contact option

Do not expose raw Razorpay, PHP, SQL, or exception details.

### Unknown / Pending

If payment state cannot yet be determined:

> Your payment is being verified. Please check your booking status shortly.

Do not incorrectly label it as failed or successful.

---

## 7. Customer Dashboard

Dashboard sections:

### Upcoming Bookings

Show:

- Booking reference
- Service
- Date
- Time
- Amount
- Status
- View details

### Past Bookings

Show completed/expired/cancelled historical bookings as applicable.

### Profile

Allow the customer to view/update approved profile information.

Password changes should require appropriate authentication and validation.

### Booking Details

Display the historical booking snapshot, not today's current service price.

---

## 8. Admin UI

Admin interface is separate under `/admin/`.

### 8.1 Admin Login

Flow:

1. Enter admin email.
2. Request OTP.
3. Receive 6-digit OTP.
4. Enter OTP.
5. Server validates expiry, one-time use, and rate limits.
6. Establish secure admin session.

OTP expiry: 5 minutes.

### 8.2 Admin Dashboard

Show:

- Today's bookings
- Upcoming bookings
- Total bookings
- Today's revenue

Use server-side data.

### 8.3 Booking Management

Admin should be able to:

- Search
- Filter
- View booking details
- Cancel booking
- Mark booking completed

Do not expose actions that are not approved for MVP.

### 8.4 Service Management

Admin can:

- Add service
- Edit service
- Activate/deactivate service
- Upload/replace image
- Set price
- Set duration
- Set capacity

Deactivation must not delete historical bookings.

### 8.5 Closed Dates

Admin can:

- View closed dates
- Add a globally closed date
- Remove a closed date when appropriate

A closed date applies to all services.

No individual slot blocking in V1.

---

## 9. Responsive Design

The system must be designed for:

- Mobile
- Tablet
- Desktop

Booking actions should remain reachable without excessive scrolling.

Avoid desktop-only interactions such as hover-only critical controls.

Tables in admin should become horizontally scrollable or responsive on smaller screens.

---

## 10. Loading, Empty, and Error States

Every asynchronous operation must have an intentional UI state.

### Loading

Use button-level loading or skeletons where appropriate.

### Empty

Examples:

> No upcoming bookings.

> No available slots for this date.

### Validation Error

Display the error close to the relevant field.

### System Error

Use a friendly message:

> Something went wrong. Please try again.

Provide a retry action when appropriate.

Never display:

- SQL errors
- stack traces
- filesystem paths
- API secrets
- server configuration
- internal exception messages

---

## 11. Accessibility

Minimum requirements:

- Semantic HTML
- Proper `<label>` elements
- Keyboard-accessible controls
- Visible focus state
- Sufficient text/background contrast
- Meaningful button text
- Error messages associated with fields
- Do not communicate status using color alone
- Images require appropriate `alt` text when meaningful
- Decorative images should use empty alt text

---

## 12. Client-Side Security Rules

JavaScript must never be treated as authoritative for:

- Price
- GST
- Duration
- Capacity
- Availability
- Booking status
- Payment success

The browser may display these values, but the server must recalculate and verify them.

Do not place:

- Razorpay secret key
- SMTP password
- Database credentials
- `.env` contents
- private API credentials

in frontend JavaScript.

---

## 13. UX Copy Rules

Use clear, human language.

Prefer:

> This time slot is no longer available.

Avoid:

> SQL constraint violation.

Prefer:

> Your payment is being verified.

Avoid:

> Webhook reconciliation pending.

Prefer:

> Your booking could not be completed. Please try again.

Avoid:

> Transaction rollback exception.

---

## 14. Confirmation Screen

The confirmation screen should make the next action obvious.

Recommended information:

- Success indicator
- Booking reference
- Service
- Date
- Time
- Payment status
- Total paid
- Invoice/receipt action
- Return to dashboard

The email confirmation should contain the same authoritative booking/payment information.

---

## 15. Design System

The final visual design should be consistent across:

- Typography
- Buttons
- Form controls
- Cards
- Alerts
- Modals
- Tables
- Badges
- Spacing
- Border radius
- Shadows
- Responsive breakpoints

Use Tailwind CSS through the approved build process.

Do not add a large UI framework unless explicitly approved.

---

## 16. Pending Content

The following must be supplied/approved by the client:

- Logo
- Brand colors
- Final typography preferences
- Service descriptions
- Service images
- Contact details
- Address/location
- Terms
- Privacy policy
- Cancellation/refund policy
- Safety declaration
- Invoice/business details
- Final CTA/content copy

Do not invent legally significant business content.

---

## 17. UI/UX Acceptance Criteria

The UI is complete when:

- Customer can register and log in.
- Customer can recover password.
- Customer can browse services.
- Customer can select a date.
- Customer can see dynamically generated availability.
- Customer cannot select unavailable capacity.
- Customer can review a correct server-derived booking total.
- Customer can complete Razorpay payment.
- Customer sees an authoritative confirmation state.
- Customer can access booking history.
- Admin can authenticate through OTP.
- Admin can manage services.
- Admin can close dates.
- Admin can manage bookings.
- All important loading/error/empty states exist.
- Mobile and desktop layouts are usable.
- No sensitive information is exposed in the browser.
