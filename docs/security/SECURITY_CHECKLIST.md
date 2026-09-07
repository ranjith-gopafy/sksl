# SKSL — Security Checklist

This checklist is a release gate for a system handling customer accounts, personal data, bookings, payments and invoices.

## Authentication
- [ ] Customer passwords use `password_hash()` / `password_verify()`
- [ ] No plain-text passwords
- [ ] Session ID regenerated after login
- [ ] Secure logout/session invalidation
- [ ] Login rate limiting
- [ ] Admin authentication is separate
- [ ] Admin OTP is 6 digits
- [ ] OTP expires after 5 minutes
- [ ] OTP is one-time use
- [ ] OTP is securely stored
- [ ] OTP request/verification is rate-limited
- [ ] Inactive admins cannot authenticate

## Password Reset
- [ ] Cryptographically random token
- [ ] Only token hash stored
- [ ] Expiration enforced
- [ ] One-time use
- [ ] Safe response that does not reveal account existence
- [ ] Rate limiting

## Sessions
- [ ] HttpOnly
- [ ] Secure in production
- [ ] SameSite configured
- [ ] Session fixation prevented
- [ ] Customer/admin access separated

## SQL Injection
- [ ] PDO prepared statements everywhere
- [ ] No SQL concatenation with user input
- [ ] Dynamic sort/filter fields use allow-lists

## CSRF/XSS
- [ ] CSRF protection on state-changing requests
- [ ] Server-side CSRF validation
- [ ] Output escaping
- [ ] User/service/booking content escaped
- [ ] Unsafe HTML is not rendered

## Authorization
- [ ] Every protected endpoint checks authentication
- [ ] Every sensitive operation checks authorization
- [ ] Customers can only access their own bookings/invoices
- [ ] Customers cannot access admin APIs
- [ ] Admin endpoints require admin session

## Booking Integrity
- [ ] Availability calculated server-side
- [ ] Price/GST/duration/capacity calculated server-side
- [ ] Closed dates enforced server-side
- [ ] Inactive services cannot be booked
- [ ] Operating hours enforced server-side
- [ ] Temporary holds included in capacity
- [ ] Database transaction/locking strategy prevents race-condition overbooking
- [ ] Duplicate booking requests are safe

## Razorpay
- [ ] Secret key is server-side only
- [ ] Amount is calculated server-side
- [ ] Razorpay order created server-side
- [ ] Signature verified server-side
- [ ] Amount/currency/order/booking relationship verified
- [ ] Webhook signature verified
- [ ] Webhook processing is idempotent
- [ ] Duplicate callbacks cannot duplicate booking/payment/invoice/email

## File Uploads
- [ ] MIME type checked
- [ ] Actual image content checked
- [ ] Extension allow-list
- [ ] File size limit
- [ ] Random server-side filename
- [ ] Executable uploads rejected
- [ ] Upload directory cannot execute scripts
- [ ] Only authorized admins can upload
- [ ] Path traversal prevented

## Secrets
- [ ] `.env` excluded from Git
- [ ] `.env` not publicly accessible
- [ ] `.env.example` contains placeholders only
- [ ] DB/Razorpay/SMTP/application secrets not hard-coded
- [ ] Secrets never appear in logs or API responses

## Error Handling
- [ ] Production debug disabled
- [ ] Stack traces hidden
- [ ] SQL/filesystem errors hidden
- [ ] Safe generic error responses
- [ ] Technical details logged securely

## HTTP/Production
- [ ] HTTPS enabled
- [ ] HTTP-to-HTTPS behavior reviewed
- [ ] Security headers reviewed
- [ ] Directory listing disabled
- [ ] Internal files/directories protected
- [ ] Sensitive pages not unnecessarily cached

## Database
- [ ] InnoDB
- [ ] Foreign keys reviewed
- [ ] Required fields constrained
- [ ] Unique constraints reviewed
- [ ] Indexes reviewed
- [ ] Production DB credentials protected
- [ ] Backups configured

## Logging
Never log:
- passwords
- OTP values
- API secrets
- SMTP passwords
- DB passwords

Safe identifiers such as booking references may be logged where appropriate.

## Release Gate
Production release must not proceed with:
- authentication bypass
- authorization bypass
- SQL injection
- XSS/CSRF vulnerabilities on sensitive operations
- payment signature/webhook verification missing
- overbooking race condition
- secret exposure
- unsafe file upload
- production debug output
