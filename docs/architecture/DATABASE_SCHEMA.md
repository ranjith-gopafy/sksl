# SKSL — Database Schema Specification

## Purpose
Define the initial MySQL structure for the MVP. Refinements to indexes/constraints are allowed when justified and documented. Business meaning must not change without approval.

## General Rules
- MySQL/InnoDB
- Foreign keys where appropriate
- Appropriate indexes
- PDO prepared statements
- Transactions for critical booking/payment operations
- Use `DECIMAL` for money
- Never store passwords, OTPs, or reset tokens in plain text

## 1. users
Customer accounts.

Suggested fields:
- `id` BIGINT UNSIGNED PK
- `name` VARCHAR(150)
- `email` VARCHAR(255) UNIQUE
- `mobile` VARCHAR(30)
- `password_hash` VARCHAR(255)
- `status`
- `created_at`
- `updated_at`

Never expose `password_hash`.

## 2. admins
Admin accounts.

Suggested:
- `id` BIGINT UNSIGNED PK
- `name` VARCHAR(150)
- `email` VARCHAR(255) UNIQUE
- `status`
- `created_at`
- `updated_at`

## 3. admin_otps
Secure admin OTP authentication.

Suggested:
- `id`
- `admin_id` FK
- `otp_hash`
- `expires_at`
- `used_at` nullable
- `attempts`
- `created_at`

Store only a secure representation of OTP.

## 4. services
Facility configuration.

Suggested:
- `id`
- `name`
- `slug` UNIQUE
- `description` nullable
- `price` DECIMAL(10,2)
- `gst_percent` DECIMAL(5,2)
- `duration_minutes`
- `capacity`
- `image` nullable
- `status`
- `created_at`
- `updated_at`

V1 GST is 18%.

## 5. closed_dates
Full facility date closure.

Suggested:
- `id`
- `closed_date` DATE UNIQUE
- `reason` nullable
- `created_at`

## 6. bookings
Authoritative historical booking record.

Suggested:
- `id`
- `booking_reference` UNIQUE
- `user_id` FK
- `service_id` FK
- `booking_date`
- `start_time`
- `end_time`
- `service_name_snapshot`
- `service_duration_minutes`
- `checkin_buffer_minutes`
- `checkout_buffer_minutes`
- `base_amount`
- `gst_percent`
- `gst_amount`
- `convenience_fee`
- `total_amount`
- `booking_status`
- `payment_status`
- `created_at`
- `updated_at`

Historical snapshots must not change when services are edited.

## 7. booking_holds
Temporary reservation during payment.

Suggested:
- `id`
- `booking_reference`
- `user_id` FK
- `service_id` FK
- `booking_date`
- `start_time`
- `end_time`
- `expires_at`
- `status`
- `created_at`

Only active, non-expired holds affect availability.

## 8. payments
Gateway/payment records.

Suggested:
- `id`
- `booking_id` FK
- `razorpay_order_id`
- `razorpay_payment_id` nullable
- `razorpay_signature` nullable
- `amount`
- `currency`
- `status`
- `gateway_response` nullable
- `paid_at` nullable
- `created_at`
- `updated_at`

Use appropriate indexes/uniqueness for gateway identifiers and idempotency.

## 9. password_resets
Suggested:
- `id`
- `user_id` FK
- `token_hash`
- `expires_at`
- `used_at` nullable
- `created_at`

## 10. email_logs (optional)
Suggested:
- `id`
- `booking_id` nullable
- `recipient`
- `email_type`
- `status`
- `error_message` nullable
- `created_at`

Never store credentials.

## Relationships
```text
users
  |
  +----< bookings >---- services
  |
  +----< password_resets

admins
  |
  +----< admin_otps

bookings
  |
  +----< payments
  |
  +----< booking_holds

closed_dates
  |
  +---- applies globally to booking availability
```

## Important Indexes
Review:
- users.email UNIQUE
- admins.email UNIQUE
- services.slug UNIQUE
- services.status
- closed_dates.closed_date UNIQUE
- bookings.booking_reference UNIQUE
- bookings.user_id + booking_date
- bookings.service_id + booking_date + start_time
- bookings.booking_status
- bookings.payment_status
- booking_holds.service_id + booking_date
- booking_holds.expires_at
- booking_holds.status
- payments.booking_id
- payments.razorpay_order_id
- payments.razorpay_payment_id

Validate indexes against actual query patterns.

## Money
Server calculates:

`base amount + GST + convenience fee = total amount`

Convenience-fee treatment is pending client confirmation.

Never trust frontend totals.

## Booking References
Generate server-side. They must be unique and should not expose sequential database IDs as the only public identifier.

## Transactions
Use transactions for:
- creating holds
- confirming booking after verified payment
- updating payment and booking state
- webhook reconciliation

## Schema Change Rule
Any schema change must be:
1. Documented.
2. Reproducible via SQL migration/schema file.
3. Tested locally.
4. Reviewed for historical-data impact.
5. Reflected in this document.
