# SKSL Production Release Checklist

**Project:** Sara Kinetic Sports Lab (SKSL)  
**Version:** 1.0  
**Status:** MVP Release Gate

---

## 1. Purpose

This checklist is the final release gate before deploying SKSL to Hostinger production.

A release should not be considered complete merely because the application loads.

---

# 2. Requirements

- [ ] MVP Development Plan reviewed.
- [ ] Approved requirements implemented.
- [ ] Pending client decisions resolved where required for launch.
- [ ] No unapproved features added.
- [ ] Excluded V1 features remain excluded.
- [ ] Final business/content assets received.
- [ ] Terms/privacy/refund/safety content approved.
- [ ] Invoice/business details confirmed.

---

# 3. Database

- [ ] Production database created.
- [ ] Correct credentials configured.
- [ ] Schema matches approved `DATABASE_SCHEMA.md`.
- [ ] All migrations applied.
- [ ] Indexes verified.
- [ ] Foreign keys verified.
- [ ] Historical booking snapshot fields verified.
- [ ] Database backup completed before release.
- [ ] Restore procedure tested.

---

# 4. Environment

- [ ] Production `.env` configured.
- [ ] `.env` not committed to Git.
- [ ] `APP_ENV=production`.
- [ ] Debug mode disabled.
- [ ] Asia/Kolkata timezone configured.
- [ ] Production application URL configured.
- [ ] Production database credentials verified.
- [ ] Razorpay production credentials configured only when launch is approved.
- [ ] Webhook secret configured.
- [ ] SMTP configured.
- [ ] Session security configured.
- [ ] Upload limits configured.

---

# 5. Security

- [ ] HTTPS enabled.
- [ ] Secure session cookies enabled.
- [ ] HttpOnly cookies enabled.
- [ ] Appropriate SameSite policy enabled.
- [ ] CSRF protection tested.
- [ ] SQL injection protections verified.
- [ ] Prepared statements used.
- [ ] XSS output escaping verified.
- [ ] Authentication authorization verified.
- [ ] Admin endpoints protected.
- [ ] Admin OTP is 6 digits.
- [ ] Admin OTP expires after 5 minutes.
- [ ] OTP is one-time use.
- [ ] OTP rate limiting works.
- [ ] Login/password reset protections tested.
- [ ] File upload security tested.
- [ ] Sensitive directories protected.
- [ ] No secrets appear in frontend source.
- [ ] Production PHP errors are not displayed.

---

# 6. Services

Verify all initial services:

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

- [ ] Names verified.
- [ ] Prices verified.
- [ ] GST verified.
- [ ] Durations verified.
- [ ] Capacities verified.
- [ ] Service images verified where provided.
- [ ] Inactive services cannot receive new bookings.

---

# 7. Booking Engine

- [ ] Facility hours are 06:00–22:00.
- [ ] Dynamic slot generation works.
- [ ] Past dates blocked.
- [ ] Closed dates blocked.
- [ ] Service duration respected.
- [ ] Capacity respected.
- [ ] Existing bookings considered.
- [ ] Active holds considered.
- [ ] Overlap formula tested.
- [ ] Concurrent booking attempts tested.
- [ ] Server-side availability enforcement works.
- [ ] Client-side availability cannot bypass server validation.
- [ ] Hold duration is 10 minutes.
- [ ] Expired holds are ignored/released correctly.
- [ ] Duplicate hold requests are handled safely.
- [ ] Booking status transitions are correct.
- [ ] Historical service/price/GST snapshot is preserved.

---

# 8. Customer Authentication

- [ ] Signup works.
- [ ] Email uniqueness works.
- [ ] Password hashing verified.
- [ ] Login works.
- [ ] Session regeneration works.
- [ ] Logout works.
- [ ] Forgot password works.
- [ ] Reset token expires.
- [ ] Reset token cannot be reused.
- [ ] Customer cannot access admin endpoints.
- [ ] Customer dashboard works.

---

# 9. Admin

- [ ] `/admin/` accessible.
- [ ] Admin OTP request works.
- [ ] OTP delivery works.
- [ ] Invalid OTP rejected.
- [ ] Expired OTP rejected.
- [ ] Used OTP rejected.
- [ ] Rate limiting tested.
- [ ] Admin session secured.
- [ ] Dashboard metrics work.
- [ ] Booking search/filter works.
- [ ] Booking details work.
- [ ] Cancel action works according to approved policy.
- [ ] Mark completed works.
- [ ] Service CRUD works.
- [ ] Service activation/deactivation works.
- [ ] Closed-date management works.

---

# 10. Razorpay

- [ ] Test-mode flow completed.
- [ ] Server-side order creation works.
- [ ] Amount is calculated server-side.
- [ ] Browser callback is not trusted directly.
- [ ] Payment signature verification works.
- [ ] Webhook signature verification works.
- [ ] Duplicate webhook handling tested.
- [ ] Duplicate callback handling tested.
- [ ] Failed payment tested.
- [ ] Tampered amount tested.
- [ ] Invalid signature tested.
- [ ] Payment status transitions verified.
- [ ] Successful payment creates authoritative confirmation.
- [ ] Payment/order identifiers are stored safely.

---

# 11. Invoice and Email

- [ ] mPDF invoice generation works.
- [ ] Invoice contains correct booking details.
- [ ] Invoice contains correct amounts.
- [ ] Business/invoice details approved.
- [ ] PHPMailer SMTP works.
- [ ] Confirmation email works.
- [ ] Invoice is attached correctly.
- [ ] Email failure does not corrupt payment state.
- [ ] Admin booking notification works.
- [ ] No automated reminder is enabled unless separately approved.

---

# 12. UI/UX

- [ ] Home page works.
- [ ] Services page works.
- [ ] Booking flow works on mobile.
- [ ] Booking flow works on desktop.
- [ ] Forms have validation.
- [ ] Loading states exist.
- [ ] Empty states exist.
- [ ] Error states exist.
- [ ] Payment processing state exists.
- [ ] Payment success state is authoritative.
- [ ] Payment failure state is clear.
- [ ] Customer dashboard is usable.
- [ ] Admin dashboard is usable.
- [ ] Keyboard accessibility checked.
- [ ] Critical controls are not hover-only.
- [ ] No empty service-image placeholders appear when no image exists.

---

# 13. Testing

- [ ] Full testing plan executed.
- [ ] Authentication tests passed.
- [ ] Authorization tests passed.
- [ ] Booking overlap tests passed.
- [ ] Capacity tests passed.
- [ ] Concurrency tests passed.
- [ ] Hold expiry tests passed.
- [ ] Payment success/failure tests passed.
- [ ] Webhook idempotency tested.
- [ ] Invoice/email tests passed.
- [ ] Security checklist passed.
- [ ] Regression testing completed.
- [ ] No critical/high unresolved bugs.

---

# 14. Git and Deployment

- [ ] Correct branch selected.
- [ ] Working tree reviewed.
- [ ] No accidental files committed.
- [ ] `.env` excluded.
- [ ] Debug/test credentials excluded.
- [ ] Migration files committed.
- [ ] Composer lock file included when appropriate.
- [ ] Production build completed.
- [ ] Tailwind production CSS built.
- [ ] Dependencies installed correctly.
- [ ] Hostinger PHP version verified.
- [ ] Required PHP extensions verified.
- [ ] File permissions verified.
- [ ] Sensitive files protected.

---

# 15. Cron

- [ ] Required cron jobs configured.
- [ ] Expired hold cleanup works.
- [ ] Cron command does not expose secrets.
- [ ] Cron is safe to run repeatedly.
- [ ] Cron failures are logged.

---

# 16. Smoke Test After Deployment

Immediately after deployment:

1. Open public website.
2. Test service listing.
3. Create/login customer test account.
4. Check availability.
5. Verify closed-date behavior.
6. Create a test booking/payment in the appropriate environment.
7. Verify booking status.
8. Verify payment status.
9. Verify invoice.
10. Verify confirmation email.
11. Login to admin.
12. Verify booking appears in admin.
13. Verify dashboard metrics.
14. Check application logs.
15. Check webhook delivery.
16. Check mobile layout.

---

# 17. Rollback Gate

Before release:

- [ ] Previous stable version identified.
- [ ] Database backup created.
- [ ] Migration rollback implications understood.
- [ ] Application rollback procedure available.
- [ ] Payment reconciliation plan available if rollback affects payment processing.

Never roll back application code blindly when database/payment state has already changed.

---

# 18. Final Sign-Off

### Development

- [ ] Code review complete.
- [ ] Testing complete.
- [ ] Security review complete.
- [ ] Documentation updated.

### Client/Business

- [ ] Services/pricing approved.
- [ ] Policies approved.
- [ ] Invoice details approved.
- [ ] Payment configuration approved.
- [ ] Launch approval received.

### Production

- [ ] Backup verified.
- [ ] Deployment completed.
- [ ] Smoke tests passed.
- [ ] No critical errors in logs.
- [ ] Production release accepted.

---

## 19. Release Decision

Choose exactly one:

**GO**

All required release gates passed.

**GO WITH KNOWN NON-CRITICAL ISSUES**

Only documented low-risk issues remain and client/development approval exists.

**NO-GO**

Any critical security, payment, booking, data-integrity, or deployment issue remains unresolved.

---

## 20. Post-Release

After launch:

- Monitor payment verification.
- Monitor booking conflicts.
- Monitor email delivery.
- Monitor application errors.
- Review admin authentication failures.
- Confirm backups continue.
- Record production issues in `docs/bugs/`.
- Record approved future improvements in `docs/development-plans/` or `FUTURE_FEATURES.md`.

