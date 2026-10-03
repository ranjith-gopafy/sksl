# SKSL — Test Cases

Manual cases for the whole Sara Kinetic Sports Lab site: public pages, customer accounts, booking, Razorpay, invoices, email, My Sessions, and the admin portal.

Run these on the test site (`https://test.sarakineticsportslab.com`) with Razorpay **test** keys. Do not use live keys or real cards. A pass is the expected result below. Anything else is a fail, including a blank page or the branded “Something went wrong” screen.

There is already a shorter checklist in `docs/testing/TESTING_PLAN.md`. This document is the one to follow case by case.

---

## 1. Three ways to test

| Way | What it covers | How to run |
| --- | --- | --- |
| Manual, in a browser | The full customer and admin journeys, layout, email in a real inbox, the PDF download | Follow the cases in this document on desktop Chrome and on a phone |
| PHP suites | Rules, payments, auth, invoices, security, without clicking | From the project folder: `php tests/test_booking_rules.php` and the other `php tests/test_*.php` files. Local MySQL must be running |
| Playwright | Public pages, login, booking, admin, and phone layouts, in a browser driven by script | `npx playwright test` with `PLAYWRIGHT_BASE_URL` pointing at a running copy of the site |

Manual testing is the release check. The PHP suites and Playwright are the repeat check after a code change. A green automated run does not replace opening the test site and paying once with a Razorpay test card.

---

## 2. Before you start

Have these ready:

- Two customer accounts (Athlete A and Athlete B), or create them in section 4.
- One admin email that exists in the `admins` table, and access to that inbox for the login code.
- `APP_ENV=production` on the test site, `APP_DEBUG=false`, `APP_URL` equal to the site address with no `/public` on the end.
- `BUSINESS_GSTIN` (15 characters), `BUSINESS_ADDRESS_LINE1` (in double quotes if it contains spaces), and `BUSINESS_PINCODE` set. If any of these is missing, invoices and both confirmation emails fail together.
- SMTP working, and `ADMIN_EMAIL` set to a real inbox.
- Razorpay test card: `4111 1111 1111 1111`, any future expiry, any CVV.
- Facility hours 06:00–22:00 IST. Slots step by the service duration plus the check-in and check-out buffers (5 + 5 minutes with the current settings). A slot can be booked up to 30 days ahead. A customer may hold 3 unpaid slots at once. A hold lasts 10 minutes.

Record the booking reference (it looks like `SKSL-20260926-21254B`) for every paid test, so you can find the invoice, the emails, and the admin row.

---

## 3. Public pages

| ID | Steps | Expected |
| --- | --- | --- |
| PUB-01 | Open the home page on a desktop window and on a phone. | One card per service. The name, price, and Book link each appear once. On a phone the card is a row; on a desktop it is a column. |
| PUB-02 | Open Services & Pricing. | Same single-card rule. Each service shows price and a Book link. |
| PUB-03 | Open Contact, Privacy Policy, Terms, and Cancellation & Refund from the footer. | Each page loads, has one main heading, and the cancellation page tells the customer to contact SKSL. There is no “cancel my booking” button. |
| PUB-04 | Use the skip link (first Tab press on a page). | Focus moves to the main content. |
| PUB-05 | Open `/robots.txt`, `/sitemap.xml`, and `/health`. | robots and sitemap return text/XML. `/health` returns `{"success":true,"status":"ok"}` and does not show the database name or environment. |
| PUB-06 | Open `/health` again with the header `X-Health-Token` set to the value in `.env`. | The response adds environment, database, and time. Without the header those fields stay hidden. |
| PUB-07 | Open a made-up path such as `/this-page-does-not-exist`. | Branded 404, not a PHP error. |

---

## 4. Create an account

| ID | Steps | Expected |
| --- | --- | --- |
| REG-01 | Register with a new name, email, 10-digit mobile, password `Sprint-2026!`, matching confirmation, and the terms box ticked. | Account is created and you are signed in. |
| REG-02 | Register again with the same email. | Rejected. The message does not say “this email already exists.” |
| REG-03 | Leave the terms box unticked. | Rejected. |
| REG-04 | Password `short1`, then `abcdefgh`, then `password1`, then a password that contains your first name. | Each one is rejected. A valid password is 8–128 characters, has a letter and a number, is not a common password, and does not contain your name or the part of your email before `@`. |
| REG-05 | Mobile `12345` and mobile with letters. | Rejected. A 10-digit Indian mobile is required. |
| REG-06 | Submit the form with no fields filled. | Each required field is flagged. No account is created. |

---

## 5. Sign in, sign out, profile

| ID | Steps | Expected |
| --- | --- | --- |
| AUTH-01 | Sign in as Athlete A with the correct password. | You land in the site as that athlete. My Sessions and Profile are available. |
| AUTH-02 | Sign in with the right email and a wrong password, five times. | After the fifth failure, further tries are locked for about 15 minutes. The message does not reveal whether the email is registered. |
| AUTH-03 | Sign out, then press the browser Back button. | You are not still inside My Sessions. Opening My Sessions asks you to sign in. |
| AUTH-04 | On a phone, open Profile and sign out. | Sign out works on the phone as well as on the desktop. It is a real sign-out, not a link that only shows on a wide screen. |
| AUTH-05 | While signed in, change your name and mobile on Profile to valid values. | Saved. The new name shows in the header or profile. |
| AUTH-06 | Change the password to a new valid one. Sign out. Sign in with the old password, then with the new one. | Old password fails. New password works. |
| AUTH-07 | Open `/my-bookings` and `/profile` in a private window with no login. | Redirected to sign in. |
| AUTH-08 | Sign in as a customer, then open `/admin/bookings`. | Refused. A customer session is not an admin session. |

---

## 6. Forgot password

| ID | Steps | Expected |
| --- | --- | --- |
| PW-01 | Request a reset for Athlete A’s real email. | The page says the same thing it says for an unknown email. The inbox receives one reset mail. The subject and body do not contain a one-time login code for an admin. |
| PW-02 | Request a reset for `nobody-sksl@example.com`. | Same on-screen message as PW-01. No account is created. |
| PW-03 | Open the link, set a new valid password, sign in with it. | Sign-in works. Using the same link a second time fails. |
| PW-04 | Request three resets for the same email within 15 minutes. | The fourth is rate-limited. |

---

## 7. Choose a slot

| ID | Steps | Expected |
| --- | --- | --- |
| AVAIL-01 | Signed in, open Book and pick an active service and tomorrow’s date. | Slots run from 06:00 and stop so the session ends by 22:00. The gap between starts matches duration plus buffer. |
| AVAIL-02 | Pick today. | Slots that have already started are not offered. |
| AVAIL-03 | Pick yesterday, and pick a date more than 30 days ahead. | Both are refused, on the calendar and if the date is sent directly. |
| AVAIL-04 | In admin, deactivate a service. As a customer, try to book it. | It is not offered. Turn it back on afterwards. |
| AVAIL-05 | In admin, add tomorrow as a closed date. As a customer, open that date. | No slots. Remove the closed date afterwards. Existing bookings on that date are not auto-cancelled. |
| AVAIL-06 | Athlete A confirms a 10:00 slot. Athlete A tries another modality at 10:00 the same day. | Refused. One person cannot hold two overlapping sessions. |
| AVAIL-07 | With capacity 1, Athlete A confirms a slot. Athlete B opens the same slot. | Shown as unavailable. A neighbouring slot that does not overlap stays available. |

---

## 8. Hold and pay

| ID | Steps | Expected |
| --- | --- | --- |
| PAY-01 | Pick a free future slot, tick the Terms & Health Declaration, continue to pay. | The slot is held. Razorpay opens for the same amount shown on the site (base + 18% GST). |
| PAY-02 | Untick the health declaration and try to continue. | Refused. The message asks you to accept the declaration. |
| PAY-03 | Pay with the test card and complete the bank step. | Booking becomes Confirmed and Paid. You see the confirmation page with the reference, service, date, and time. The slot is no longer free for anyone else. |
| PAY-04 | Close Razorpay without paying. Wait 10 minutes (or run `php cron/expire-holds.php`). | The hold ends. The slot is free again. The booking does not stay confirmed. |
| PAY-05 | Start three unpaid holds, then start a fourth before any of them expire. | The fourth is refused. Releasing one allows a new hold. |
| PAY-06 | On a confirmed booking, start payment again for the same reference. | No second Razorpay order. You are not charged twice. |
| PAY-07 | Pay, then immediately refresh the confirmation URL. | Still one booking, one payment, one pair of emails. |

---

## 9. Invoice and email

Do these on the booking from PAY-03.

| ID | Steps | Expected |
| --- | --- | --- |
| INV-01 | Open My Sessions and download Invoice PDF. | A PDF downloads. It shows the booking reference, athlete name, service, date, time, base, CGST, SGST, total, and the Razorpay payment id. |
| INV-02 | Check Athlete A’s inbox and the `ADMIN_EMAIL` inbox. | The athlete receives one confirmation with the PDF attached. The admin receives one new-booking alert. Neither inbox receives a second copy on refresh. |
| INV-03 | While signed out, open the invoice URL. While signed in as Athlete B, open Athlete A’s invoice URL. | Signed out: asked to sign in. Athlete B: refused. |
| INV-04 | In admin, edit that service’s price. Download the old invoice again. | The PDF still shows the price from the day of booking, not the new price. |
| INV-05 | Temporarily clear `BUSINESS_GSTIN` on a **copy** of the site, not production, and download an invoice. | The page says the invoice is temporarily unavailable, and no confirmation email is sent. Put the GSTIN back. |

If INV-01 or INV-02 fails on the test site, read the last lines of `storage/logs/php-error.log`. The usual line is `Invoice identity is not configured`, which means the GSTIN, address line, or pincode is missing or the address has spaces and is not in quotes.

---

## 10. My Sessions

Use a booking whose end time is already past (the morning Sauna is the example), and a second booking for a future slot.

| ID | Steps | Expected |
| --- | --- | --- |
| MY-01 | Open My Sessions with no tab selected. | All Sessions counts every real booking. Pending and expired checkouts are not listed. |
| MY-02 | Open Upcoming, Completed, and Cancelled. | The number on each tab equals the number of cards inside it. A confirmed session that already ended today is Completed, not Upcoming. A future confirmed session is Upcoming. |
| MY-03 | Look for a cancel button on a paid upcoming session. | There is none. The page tells the athlete to contact SKSL and shows the phone and support email. |
| MY-04 | Open the site on a phone width. | The same tabs and the same cards are usable. The invoice button can be tapped. |

---

## 11. Admin sign-in

| ID | Steps | Expected |
| --- | --- | --- |
| ADM-01 | Open `/admin/login` and submit the admin email. | One 6-digit code arrives. It is not in the email subject. It expires in 5 minutes. |
| ADM-02 | Enter the code. | You reach the admin bookings list. |
| ADM-03 | Enter a wrong code. | Rejected. After repeated failures the attempt is locked. |
| ADM-04 | Submit an email that is not in `admins`. | Same style of response as a real admin. No code is sent. |
| ADM-05 | Sign out of admin, then open `/admin/bookings` directly. | Sent back to admin login. |

---

## 12. Admin operations

| ID | Steps | Expected |
| --- | --- | --- |
| ADM-06 | Find the PAY-03 booking. Mark it completed. | Status becomes completed. The athlete sees it under Completed. |
| ADM-07 | On a different paid future booking, set status to cancelled. | Status becomes cancelled. The slot is free for another athlete. The athlete sees it under Cancelled. Refund is still done by hand in the Razorpay dashboard; the site does not call Razorpay to refund. |
| ADM-08 | Try to move that cancelled booking back to confirmed, and try to move a completed booking anywhere. | Both refused. |
| ADM-09 | Try to mark an unpaid booking completed. | Refused. Only a paid booking can be completed. |
| ADM-10 | Edit a service name, duration, price, and capacity to valid values. Upload a real JPEG under 5 MB. Then upload a `.php` file renamed to `.jpg`, and a file that is not an image. | Valid save works. The fake image and the non-image are rejected. Bookings already made keep the old name and price. |
| ADM-11 | Set a price of 0 or a negative price. | Rejected. |
| ADM-12 | Add a closed date that already exists. Delete a closed date. | Duplicate is refused or ignored. Delete removes it and that date can be booked again. |
| ADM-13 | Change the home banner image and link. | The home page shows the new banner. A link that is not an `http` or `https` address is rejected. |

---

## 13. Security checks

Do these once per release. Stop at the first real failure.

| ID | Steps | Expected |
| --- | --- | --- |
| SEC-01 | On Register, type `<script>alert(1)</script>` as the name, then view My Sessions and any admin list that shows the name. | The text shows as text. No alert box. |
| SEC-02 | Submit a booking or profile form with no CSRF token (or a token copied from another session). | Rejected. |
| SEC-03 | `POST /api/payment/webhook` with no Razorpay signature, to the public site URL that Razorpay will call. | 400 if the secret is set and the signature is wrong. 503 if `RAZORPAY_WEBHOOK_SECRET` is empty. Not a CSRF 403. A 403 means the webhook URL is not the public `/api/payment/webhook` path (the site is being served from the project folder instead of `public/`). |
| SEC-04 | Request `/storage/`, `/cron/`, `/.env`, and `/database/`. | Each is denied. The browser does not show source, invoices, or the env file. |
| SEC-05 | Confirm `APP_DEBUG` is false by forcing a bad request. | The branded error page. No file path or SQL. |
| SEC-06 | From Athlete A’s session, request Athlete B’s invoice and booking reference. | 403 or not found. A’s own invoice still downloads. |

---

## 14. Phone and desktop

Repeat PUB-01, AUTH-04, AVAIL-01, PAY-03, and MY-04 at a narrow width (about 390px) and at a desktop width. The booking you pay on the phone must show the same reference in My Sessions on the desktop.

---

## 15. Automated runs

From the project folder, with MySQL running and `.env` pointing at that database:

```bash
php tests/test_booking_rules.php
php tests/test_auth_hardening.php
php tests/test_phase7_payment.php
php tests/test_phase8_invoice.php
php tests/test_phase9_email.php
php tests/test_security_audit.php
```

Then the browser suite:

```bash
npx playwright test
```

Each PHP file prints a pass count and must end with 0 failed. Playwright must show 0 failed. These cover the same rules as the manual cases, plus overlap, capacity, and payment tampering that are unsafe to fake by hand.

---

## 16. Release pass

The test site is ready to show when all of these are true:

- REG-01, AUTH-01, PAY-03, INV-01, INV-02, MY-02, ADM-02, and ADM-07 passed on `https://test.sarakineticsportslab.com`.
- SEC-03 returned 400 or 200 for a signed test webhook, not 403.
- `/health` is ok, and the public body has no secrets.
- Confirmation mail arrived in both the athlete inbox and `ADMIN_EMAIL`.
- The invoice PDF opens and the GSTIN on it is the real one, not the dummy `29ABCDE1234F1Z5`.
