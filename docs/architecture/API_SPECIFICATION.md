# SKSL — API Specification

## 1. Purpose

This document defines the application API contract for the SKSL MVP.

API implementation must follow this specification unless a documented decision changes it.

All APIs must validate input server-side and return safe, consistent responses.

---

# 2. API Principles

- HTTPS in production
- JSON responses for AJAX/API endpoints
- Correct HTTP methods
- Authentication enforced server-side
- Authorization enforced server-side
- CSRF protection for session-authenticated state-changing requests
- Server-side validation
- PDO prepared statements
- No sensitive information in responses
- Consistent error structure
- No stack traces in production

---

# 3. Response Format

### Success

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```

### Error

```json
{
  "success": false,
  "message": "Unable to complete the request.",
  "errors": {}
}
```

Do not expose SQL errors, filesystem paths, stack traces, secrets, or internal implementation details.

---

# 4. Customer Authentication APIs

## POST /api/register

Create customer account.

### Request

```json
{
  "name": "Customer Name",
  "email": "customer@example.com",
  "mobile": "9876543210",
  "password": "********",
  "password_confirmation": "********"
}
```

### Rules

- Validate required fields.
- Validate email.
- Normalize email appropriately.
- Validate mobile format.
- Validate password policy.
- Confirm password.
- Reject duplicate email.
- Hash password server-side.

Never return password/hash.

---

## POST /api/login

### Request

```json
{
  "email": "customer@example.com",
  "password": "********"
}
```

### Success

Create secure authenticated session.

---

## POST /api/logout

Requires customer authentication.

Destroy/invalidate session.

---

## POST /api/forgot-password

### Request

```json
{
  "email": "customer@example.com"
}
```

Response must not reveal whether the account exists.

---

## POST /api/reset-password

### Request

```json
{
  "token": "reset-token",
  "password": "********",
  "password_confirmation": "********"
}
```

Server validates token, expiry, one-time use, and password policy.

---

# 5. Customer Profile APIs

## GET /api/profile

Requires customer authentication.

Return only the authenticated customer's profile.

Never return password hash or security tokens.

---

## PUT /api/profile

Requires customer authentication.

Only approved editable fields may be changed.

Do not allow the customer to change protected database fields through mass assignment.

---

# 6. Service APIs

## GET /api/services

Public or customer-accessible endpoint.

Return active services intended for customer display.

Suggested fields:

```json
{
  "id": 1,
  "name": "Spa",
  "slug": "spa",
  "description": "...",
  "price": 999,
  "duration_minutes": 30,
  "capacity": 4,
  "image": null
}
```

Do not expose internal/security fields.

GST may be displayed if required by the approved UI, but the server remains authoritative.

---

# 7. Availability API

## GET /api/availability

Suggested query parameters:

```text
service_id
date
```

Example:

```text
GET /api/availability?service_id=1&date=2026-09-15
```

Server validates:

- service
- service status
- date
- closed date
- operating hours
- duration
- buffers
- existing bookings
- active holds
- capacity

Return dynamically generated available slots.

Do not store every possible slot as a database row.

---

# 8. Create Booking Hold

## POST /api/bookings/hold

Requires customer authentication.

### Request

```json
{
  "service_id": 1,
  "booking_date": "2026-09-15",
  "start_time": "10:00",
  "health_declared": "1"
}
```

`health_declared` is mandatory (`"1"`, `"true"` or `"on"`). It records that the
customer accepted the Terms & Health Declaration; the server answers **422** without
it. The acceptance time is stored on the hold (`booking_holds.health_declared_at`)
and copied to `bookings.health_declared_at` when the booking row is created at
`/api/payment/create-order`.

The server must recalculate:

- service duration
- end time
- buffers
- base price
- GST
- convenience fee
- total amount

The browser-provided amount must be ignored.

### Success

Return:

```json
{
  "success": true,
  "message": "Booking hold created.",
  "data": {
    "booking_reference": "SKSL-XXXXXXXX",
    "expires_at": "2026-09-15T10:10:00+05:30",
    "amount": 1178.82,
    "currency": "INR"
  }
}
```

The exact amount depends on the approved convenience-fee rule.

---

# 9. Customer Bookings

## GET /api/bookings

Requires customer authentication.

Return only the authenticated customer's bookings.

Support safe pagination/filtering if implemented.

---

## GET /api/bookings/{id}

Requires customer authentication.

Before returning the booking, verify ownership.

Do not expose another customer's booking through ID guessing.

---

# 10. Payment APIs

## POST /api/payment/create-order

Requires customer authentication.

The server must verify that:

- booking belongs to customer
- booking is still valid
- hold is active
- booking is payable
- amount matches server calculation

Create Razorpay order server-side.

Never accept an arbitrary amount from the browser.

---

## POST /api/payment/verify

Requires customer authentication where applicable.

### Request

```json
{
  "booking_reference": "SKSL-XXXXXXXX",
  "razorpay_order_id": "order_xxx",
  "razorpay_payment_id": "pay_xxx",
  "razorpay_signature": "signature"
}
```

Server verifies:

- booking ownership
- order relationship
- signature
- amount
- currency
- payment state

Never confirm based only on a frontend `success` flag.

---

# 11. Razorpay Webhook

## POST /api/payment/webhook

This endpoint is called by Razorpay.

Requirements:

- Read raw request body.
- Verify webhook signature.
- Validate event.
- Locate internal payment/order.
- Process idempotently.
- Update payment and booking state safely.
- Do not trust browser session.
- Do not expose internal errors.

Duplicate webhook events must be harmless.

---

# 12. Invoice

Invoices should normally be generated after successful payment/booking confirmation.

If an API is provided for invoice access, it must:

- authenticate customer
- verify booking ownership
- prevent path traversal
- prevent direct arbitrary file access
- serve only the requested authorized invoice

---

# 13. Admin Authentication APIs

## POST /admin/api/request-otp

Request an admin login OTP.

Rules:

- Validate email.
- Verify active admin.
- Generate secure 6-digit OTP.
- Store secure representation.
- Expire after 5 minutes.
- Rate-limit requests.
- Send by email.

Response must not reveal whether an email is an admin account.

---

## POST /admin/api/verify-otp

### Request

```json
{
  "email": "admin@example.com",
  "otp": "123456"
}
```

Rules:

- Validate OTP format.
- Validate expiry.
- Validate attempts.
- Validate one-time use.
- Authenticate admin.
- Regenerate session ID.

---

## POST /admin/api/logout

Destroy admin session.

---

# 14. Admin Booking APIs

Implemented routes (see `public/index.php`). There is no separate `/admin/api/...`
namespace; admin pages are server-rendered and state changes are CSRF-protected
POST forms.

## GET /admin/bookings

Requires admin authentication.

Supports:

- `search` (reference, customer name, email, mobile — `LIKE` wildcards are escaped)
- `date` filter
- `status` tab (`pending`, `confirmed`, `completed`, `cancelled`, `expired`, `all`)
- pagination (`page`)

Never expose secrets. Loading this page also runs the hold / pending-booking
expiry housekeeping.

---

## POST /admin/bookings/{id}/status

Requires admin authentication and a CSRF token.

Request field: `status` — the target booking status. Only the transitions in
`BookingModel::ADMIN_TRANSITIONS` are accepted:

| From        | Allowed targets           |
|-------------|---------------------------|
| `pending`   | `cancelled`               |
| `confirmed` | `completed`, `cancelled`  |
| `completed` | — (terminal)              |
| `cancelled` | — (terminal)              |
| `expired`   | — (terminal)              |

Cancellation is **admin-only**: customers do not cancel online. They contact SKSL
with their booking reference (see `/cancellation-refund`). Refunds, if any, are
processed manually in the Razorpay dashboard by SKSL; this application never
issues a refund itself and does not invent refund rules.

---

# 15. Admin Service APIs

## GET /admin/api/services

Admin-only.

Return service management fields.

---

## POST /admin/api/services

Admin-only.

Create service.

Validate:

- name
- slug
- price
- duration
- capacity
- GST
- image
- status

---

## PUT /admin/api/services/{id}

Admin-only.

Update service.

Historical bookings must remain unchanged.

---

## POST /admin/api/services/{id}/toggle

Admin-only.

Activate/deactivate service.

Deactivation must not delete historical bookings.

---

# 16. Admin Closed Date APIs

## GET /admin/api/closed-dates

Admin-only.

---

## POST /admin/api/closed-dates

Example:

```json
{
  "closed_date": "2026-09-20",
  "reason": "Private event"
}
```

A closed date applies to all services.

---

## DELETE /admin/api/closed-dates/{id}

Admin-only.

Only if deletion/removal is enabled in the approved UI.

---

# 17. API Authentication Matrix

| Endpoint | Public | Customer | Admin |
|---|---:|---:|---:|
| Register | Yes | No | No |
| Login | Yes | No | No |
| Logout | No | Yes | No |
| Services | Yes | Yes | No |
| Availability | Yes/Customer | Yes | No |
| Booking Hold | No | Yes | No |
| Customer Bookings | No | Own only | No |
| Payment Create Order | No | Own booking | No |
| Payment Verify | No | Own booking | No |
| Payment Webhook | Gateway | No | No |
| Admin OTP | Yes | No | No |
| Admin Bookings | No | No | Yes |
| Admin Services | No | No | Yes |
| Closed Dates | No | No | Yes |

---

# 18. HTTP Status Guidance

Suggested:

- `200` successful read/update
- `201` created
- `400` malformed/invalid request
- `401` unauthenticated
- `403` authenticated but unauthorized
- `404` resource not found
- `409` booking/conflict/duplicate conflict
- `422` validation failure
- `429` rate limit exceeded
- `500` safe internal error
- `503` temporary external dependency/service failure

Do not expose internal exception details.

---

# 19. API Security Requirements

Every endpoint must consider:

- authentication
- authorization
- CSRF where session-authenticated
- input validation
- output escaping
- rate limiting
- ownership checks
- database integrity
- safe error responses
- audit logging where appropriate

---

# 20. Versioning

Do not add API versioning complexity unless required.

If a public API contract is later exposed to external consumers, introduce an explicit version such as:

```text
/api/v1/
```

Do not break existing consumers without a documented migration strategy.

---

# 21. API Documentation Rule

Whenever an endpoint changes:

- Update this document.
- Update validation rules.
- Update request/response examples.
- Update frontend consumers.
- Add/update tests.
