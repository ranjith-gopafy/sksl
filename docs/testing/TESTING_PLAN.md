# SKSL — Testing Plan

## Purpose
Verify functional correctness, security, booking integrity, payment integrity, concurrency, email/invoice behavior, admin operations, and deployment readiness.

## Customer Authentication
- [ ] Valid registration
- [ ] Duplicate email rejection
- [ ] Invalid input rejection
- [ ] Password confirmation validation
- [ ] Password hashing
- [ ] Valid login
- [ ] Invalid login
- [ ] Session regeneration
- [ ] Logout
- [ ] Login rate limiting

## Password Reset
- [ ] Valid reset
- [ ] Unknown email safe response
- [ ] Token expiry
- [ ] One-time token
- [ ] Invalid token rejection
- [ ] New password hashed
- [ ] Rate limiting

## Admin OTP
- [ ] Valid admin receives OTP
- [ ] Exactly 6 digits
- [ ] 5-minute expiry
- [ ] Correct OTP succeeds
- [ ] Incorrect OTP fails
- [ ] Used OTP rejected
- [ ] Rate limiting
- [ ] Unauthorized email rejected

## Services
- [ ] Create
- [ ] Edit
- [ ] Activate/deactivate
- [ ] Price validation
- [ ] Duration validation
- [ ] Capacity validation
- [ ] Valid image upload
- [ ] Invalid image rejection
- [ ] Historical bookings unaffected by later service edits

## Closed Dates
- [ ] Add closed date
- [ ] Customer cannot book it
- [ ] Applies to all services
- [ ] Duplicate handling
- [ ] Removal if supported

## Availability
- [ ] Active services available
- [ ] Inactive services blocked
- [ ] Past dates blocked
- [ ] Closed dates blocked
- [ ] Operating hours enforced
- [ ] Duration respected
- [ ] Approved buffers respected
- [ ] Last-slot rule respected
- [ ] Existing bookings reduce availability
- [ ] Expired holds release availability

## Capacity
Test capacities 1, 2, 4 and 8.

- [ ] Booking succeeds until capacity
- [ ] Additional overlapping booking blocked
- [ ] Holds count correctly
- [ ] Expired holds stop counting

## Overlap
Test:
- exact same time
- partial overlap
- new start inside existing booking
- new end inside existing booking
- adjacent bookings
- multiple services on same day

## Concurrency
Mandatory test.

Simultaneously attempt bookings for capacity 1, 2, 4 and 8.

Expected result: active confirmed/held bookings never exceed capacity.

## Temporary Holds
- [ ] 10-minute expiry
- [ ] Active hold reduces availability
- [ ] Expired hold releases availability
- [ ] Failed payment releases hold
- [ ] Successful payment confirms booking
- [ ] Hold ownership enforced
- [ ] Cleanup process works

## Razorpay
### Success
- [ ] Correct order
- [ ] Correct amount
- [ ] Correct booking association
- [ ] Signature verification
- [ ] Payment record
- [ ] Booking confirmation
- [ ] Invoice
- [ ] Email

### Failure/Tampering
- [ ] Failed payment does not confirm booking
- [ ] Invalid signature rejected
- [ ] Modified amount rejected
- [ ] Incorrect order rejected
- [ ] Incorrect booking/payment relationship rejected

## Webhooks
- [ ] Valid webhook accepted
- [ ] Invalid signature rejected
- [ ] Duplicate webhook safe
- [ ] Webhook-before-browser callback handled
- [ ] Browser callback-before-webhook handled
- [ ] No duplicate booking/payment/invoice/email

## Invoice
- [ ] PDF generated
- [ ] Correct booking reference
- [ ] Correct customer/service/date/time
- [ ] Correct amount/GST
- [ ] Correct payment reference
- [ ] Historical price used
- [ ] PDF opens correctly
- [ ] Invoice access protected

## Email
- [ ] Customer receives one consolidated confirmation
- [ ] Invoice attached
- [ ] Admin receives new-booking notification
- [ ] SMTP failure does not corrupt booking/payment state
- [ ] Email failure logged safely

## Authorization
- [ ] Customer A cannot access Customer B booking
- [ ] Customer A cannot access Customer B invoice
- [ ] Unauthenticated dashboard blocked
- [ ] Customer cannot access admin
- [ ] Admin access requires valid admin session

## Security
- [ ] SQL injection
- [ ] XSS
- [ ] CSRF
- [ ] Session fixation
- [ ] Authorization bypass/IDOR
- [ ] Brute force
- [ ] OTP abuse
- [ ] Password reset abuse
- [ ] File upload abuse
- [ ] Path traversal
- [ ] Sensitive-file exposure
- [ ] Debug information exposure

## Browser/UI
Test desktop Chrome/Edge and mobile browsers where available.

- [ ] Responsive layout
- [ ] Forms
- [ ] Validation
- [ ] Loading states
- [ ] Payment flow
- [ ] Confirmation
- [ ] Customer dashboard
- [ ] Admin dashboard

## Database
- [ ] Foreign keys
- [ ] Required fields
- [ ] Unique booking references
- [ ] Appropriate payment identifier constraints
- [ ] Historical snapshots preserved
- [ ] Transactions rollback correctly

## Regression
After every major change:
1. Run feature-specific tests.
2. Run booking tests.
3. Run payment tests.
4. Run authentication/authorization tests.
5. Run relevant security tests.

## Production Release Gate
- [ ] Critical tests passed
- [ ] No critical security issue
- [ ] Razorpay production config reviewed
- [ ] Webhook configured
- [ ] SMTP configured
- [ ] Invoice tested
- [ ] Cron tested
- [ ] HTTPS verified
- [ ] Debug disabled
- [ ] `.env` protected
- [ ] Backups configured
- [ ] UAT approved
