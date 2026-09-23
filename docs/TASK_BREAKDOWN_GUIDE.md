# SKSL — Task Hierarchy & Implementation Breakdown Guide

This document establishes the official structure for organizing categories, subcategories, tasks, and subtasks for the **Sara Kinetic Sports Lab (SKSL)** project in **Gopafy Business OS**.

---

## 1. The 4-Tier Hierarchy Standard

```
📁 Project: Sara Kinetic Sports Lab (SKSL)
   └── 🏷️ Category (Major system domain)
        └── 📑 Subcategory (Specific subsystem or user flow)
             └── ✅ Task (Actionable work ticket assigned to a developer)
                  └── 🔲 Subtask (Technical checklist item inside the task)
```

---

## 2. Complete Reference Blueprint: Sara Kinetic Sports Lab (SKSL)

### Category 1: Customer Portal
*Focus: Public-facing UI, customer registration, service discovery, and customer account dashboard.*

* **Subcategory 1.1: Authentication & Account**
  * **Task 1:** Build Customer Registration & Password Hashing
    * *Subtasks:*
      - [ ] Frontend registration form with client-side validation
      - [ ] Server-side input sanitization and duplicate email check
      - [ ] Password hashing with `password_hash()` (bcrypt)
      - [ ] Auto-login on successful registration and redirect to services
  * **Task 2:** Customer Login, Logout & Session Guard
    * *Subtasks:*
      - [ ] Email & password authentication endpoint
      - [ ] Secure session cookie configuration (`httponly`, `samesite=strict`)
      - [ ] Logout endpoint destroying session and clearing cookies
  * **Task 3:** Forgot & Reset Password Flow
    * *Subtasks:*
      - [ ] Generate secure, single-use, 15-minute expiring token
      - [ ] Send password reset email via PHPMailer + SMTP
      - [ ] Reset password form and database update handler

* **Subcategory 1.2: Service Catalog**
  * **Task 4:** Service Listing & Catalog Grid
    * *Subtasks:*
      - [ ] Responsive UI grid for all 10 services (Spa, Sauna, Steam, Ice Bath, Endless Pool, Cycle, Treadmill, Walker, Hot Bath, Lap Pool)
      - [ ] Display base price, mandatory 18% GST calculation, and final total
      - [ ] Badge display for duration (10–45 min) and max capacity (1–8 people)
  * **Task 5:** Service Details & Prerequisites Modal
    * *Subtasks:*
      - [ ] Service overview, guidelines, and health advisory notes
      - [ ] Dynamic "Book Now" CTA linking into the slot selection engine

* **Subcategory 1.3: Booking History & Invoices**
  * **Task 6:** Customer Booking Dashboard
    * *Subtasks:*
      - [ ] Tabbed list for "Upcoming Bookings" vs "Past Bookings"
      - [ ] Status indicators: `Confirmed`, `In Hold`, `Cancelled`, `Completed`
      - [ ] Direct "Download PDF Invoice" button for confirmed bookings

---

### Category 2: Booking & Slot Engine
*Focus: Dynamic time slot generation, concurrency protection, and capacity management.*

* **Subcategory 2.1: Slot Generation**
  * **Task 7:** Dynamic Daily Slot Generation Engine (06:00 AM – 10:00 PM)
    * *Subtasks:*
      - [ ] Parse operating window (06:00 AM to 10:00 PM Asia/Kolkata)
      - [ ] Chunk available slots according to service duration (10m, 15m, 30m, 45m)
      - [ ] Exclude past slots if booking for the current day
  * **Task 8:** Blackout & Full-Day Closure Filter
    * *Subtasks:*
      - [ ] Query `facility_closures` table for full-date blocks
      - [ ] Disable all slot options if selected date is marked closed by admin

* **Subcategory 2.2: Concurrency & Capacity Holds**
  * **Task 9:** Server-Side Slot Capacity Validator
    * *Subtasks:*
      - [ ] Enforce concurrency rules: sum of confirmed bookings + active holds < service capacity
      - [ ] Reject checkout requests if remaining capacity equals 0
  * **Task 10:** 10-Minute Temporary Payment Hold Lock
    * *Subtasks:*
      - [ ] Insert temporary hold row in `slot_holds` with 10-minute expiry
      - [ ] Associate hold ID with customer session and Razorpay order
      - [ ] Automatically release held slots if payment is not confirmed within 10 minutes

---

### Category 3: Payments & Invoices
*Focus: Razorpay checkout, server-side signature verification, GST invoices, and emails.*

* **Subcategory 3.1: Razorpay Integration**
  * **Task 11:** Create Razorpay Order API Endpoint
    * *Subtasks:*
      - [ ] Server calculates base price + 18% GST (never trust client amounts)
      - [ ] Initialize Razorpay SDK and create order in INR
      - [ ] Store `razorpay_order_id` in database linked to customer hold
  * **Task 12:** Payment Verification & Webhook Reconciler
    * *Subtasks:*
      - [ ] Verify Razorpay signature using HMAC SHA256 secret server-side
      - [ ] Idempotent payment webhook listener to handle network dropouts
      - [ ] Transition hold to `confirmed` status on verified payment

* **Subcategory 3.2: Invoicing & Communications**
  * **Task 13:** GST Tax Invoice Generation via mPDF
    * *Subtasks:*
      - [ ] Design official SKSL invoice template with brand header and GSTIN
      - [ ] Itemize service cost, 18% GST breakdown, and transaction ID
      - [ ] Stream/save generated PDF to customer storage
  * **Task 14:** Consolidated Notification Dispatcher (PHPMailer)
    * *Subtasks:*
      - [ ] Send booking confirmation email with attached PDF invoice to customer
      - [ ] Send instant booking alert email to admin notification inbox

---

### Category 4: Admin Management
*Focus: Internal operations, booking controls, service catalog CRUD, and facility closures.*

* **Subcategory 4.1: Admin Security**
  * **Task 15:** 6-Digit Email OTP Login for Admin Portal
    * *Subtasks:*
      - [ ] Dedicated `/admin/` login form requesting authorized admin email
      - [ ] Generate 6-digit cryptographically random OTP with 5-minute expiry
      - [ ] Invalidate OTP immediately upon first use
      - [ ] Admin authentication middleware guarding all `/admin/*` routes

* **Subcategory 4.2: Service Management**
  * **Task 16:** Service CRUD & Capacity Controller
    * *Subtasks:*
      - [ ] Edit service title, description, base price, and capacity
      - [ ] Banner image upload and optimization
      - [ ] Toggle service active/inactive status

* **Subcategory 4.3: Operations & Booking Master**
  * **Task 17:** Master Booking List with Filters & Search
    * *Subtasks:*
      - [ ] Filter bookings by date range, service, and payment status
      - [ ] Search by customer name, email, or booking reference number
      - [ ] Manual override actions (cancel booking, mark customer attended)
  * **Task 18:** Facility Calendar Closure Manager
    * *Subtasks:*
      - [ ] Admin calendar UI to mark holidays, maintenance, or emergency closures
      - [ ] Block new customer bookings across all services for marked dates

---

### Category 5: DevOps & Infrastructure
*Focus: Hosting configuration, cron jobs, database migrations, and security headers.*

* **Subcategory 5.1: Hosting & Database**
  * **Task 19:** Hostinger Environment Setup & MySQL Migrations
    * *Subtasks:*
      - [ ] Create production MySQL database in Hostinger hPanel
      - [ ] Execute idempotent database schema creation scripts
      - [ ] Configure production `.env` with strict file permissions (`600`)
  * **Task 20:** Automated Hold Expiry Cron Job
    * *Subtasks:*
      - [ ] Create CLI PHP script to prune expired holds older than 10 minutes
      - [ ] Set up Hostinger scheduled cron job running every 5 minutes

---

## 3. How to Manage This in Gopafy Business OS

### 1. Creating a Category
1. Navigate to **Tasks** (`/tasks/kanban` or `/tasks/table`).
2. In the left **Task Navigator**, find `Sara Kinetic Sports Lab (SKSL)`.
3. Hover over the project name and click the **`+`** icon (or click the project row and select **`+ Add category`**).
4. Enter the category name (e.g., `Booking & Slot Engine`) and press **Enter**.

### 2. Creating a Subcategory
1. In the sidebar under the category you just created, hover over the category name.
2. Click the **`+`** icon next to it.
3. Enter the subcategory name (e.g., `Concurrency & Capacity Holds`) and press **Enter**.

### 3. Adding a Task
1. Click the **`+`** icon next to the subcategory in the sidebar, or click the **`+ New Task`** button in the header.
2. In the modal, fill in:
   - **Title:** Action-oriented name (e.g., `10-Minute Temporary Payment Hold Lock`)
   - **Project:** `Sara Kinetic Sports Lab (SKSL)`
   - **Category:** `Booking & Slot Engine` *(auto-filled if clicked from sidebar)*
   - **Sub-category:** `Concurrency & Capacity Holds` *(auto-filled if clicked from sidebar)*
   - **Priority:** `Low`, `Medium`, or `High`
   - **Status:** `To Do`
   - **Assigned To:** Assign to the developer responsible
   - **Due Date:** Target delivery milestone
   - **Description:** Acceptance criteria and technical notes
3. Click **Create Task**.

### 4. Adding Checklist Subtasks
1. Click the task card on the Kanban board or Table to open the **Task Detail Drawer**.
2. Scroll to the **Subtasks** section.
3. Type each technical checklist item and hit **Enter**.
4. Check off items as implementation completes to track progress accurately.
