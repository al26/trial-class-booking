# Ottodot - Trial Class Booking Reliability Engine

> Smallest reliable slice of a trial booking system built with Laravel 11 and SQLite, designed to handle high-concurrency race conditions, payment failures, and zero-overbooking invariants.

---

## 1. How To Run Your Solution

This project includes an automated single-command setup script (`setup.sh`) that provisions the environment, database, seeds edge-case data, compiles assets, and runs tests in under **1 minute**.

### Prerequisites
- PHP 8.2 or higher (with `sqlite3`, `pdo_sqlite`, `bcmath`, `mbstring` extensions)
- Composer 2.x
- Node.js & npm (optional, pre-built Vite assets are included)

### Quick Start (Single Command)

Clone the repository and run:

```bash
git clone https://github.com/amriluthfi/trial-class-booking.git
cd trial-class-booking
./setup.sh
```

The script will automatically:
1. Copy `.env.example` to `.env` (if not exists)
2. Install PHP Composer dependencies
3. Generate the application key (`APP_KEY`)
4. Create and migrate the SQLite database with rich synthetic seed data
5. Build frontend assets (Vite / Tailwind CSS)
6. Clear caches and run the automated test suite

---

### Running the App & Verifications

Once setup is complete, you can run and test the application with these commands:

#### 1. Start the Web Server (Interactive Single-Page Demo)
```bash
cd trial-class-booking
php artisan serve
```
Open your browser at: **[http://localhost:8000](http://localhost:8000)**
- **Left Panel:** Parent Booking Simulator (choose parent, pick child, select class, toggle payment failure simulation).
- **Right Panel:** Live Teacher/Admin Roster View (shows live capacity, seat progress bars, and strictly confirmed students).
- **Quick Test Buttons:** One-click presets for testing duplicate bookings and payment failures.

#### 2. Run the Last-Seat Race Concurrency Test (CLI)
We built a dedicated Artisan command to simulate two parallel users competing for the final slot:
```bash
cd trial-class-booking
php artisan test:last-seat-race
```
This spawns 2 isolated concurrent sub-processes using `Symfony\Component\Process\Process` and prints a real-time verification table.

#### 3. Run Automated Pest Test Suite
```bash
cd trial-class-booking
vendor/bin/pest
# or
php artisan test --compact
```
10 tests, 57 assertions covering all required scenarios (TC-01 to TC-06).

---

## 2. What Was Built

We built the core reliable slice required by the brief:

1. **Parent Booking Simulator:** Allows selecting a parent, filtering their child, selecting a trial class, and picking a payment method.
2. **Pessimistic Concurrency Engine:** Guarantees that at most 4 students can be confirmed per class, even under simultaneous checkout requests.
3. **Mock Payment & Compensating Transaction:** Simulates synchronous payments, isolates failures, and automatically triggers refunds if a class fills up before a payment lock resolves.
4. **Live Teacher / Admin Roster View & REST API:** Real-time visibility into who is confirmed for class, excluding failed or refunded attempts.
5. **Multi-layer Invariant Defense:** Database-level partial unique index + backend validation guards.

---

## 3. Time Spent

- **Total Time:** ~3.5 hours (well within the 3-4 hours timebox).
- Breakdown:
  - *Data model, migrations & partial index:* ~45 mins
  - *Core BookingService & concurrency locking logic:* ~60 mins
  - *REST API layer, resources & form requests:* ~30 mins
  - *Pest test suite & Artisan race simulator script:* ~45 mins
  - *Interactive single-page UI & setup script:* ~30 mins

---

## 4. Assumptions Made

1. **Strict Capacity of 4:** Trial classes are strictly capped at 4 seats. No manual admin overrides or overbooking allowances.
2. **Single Child per Checkout:** To keep the booking slice clean and simple, parents register 1 student per transaction.
3. **Fixed Trial Price:** Classes have a fixed price ($25.00 / 2500 cents) to provide realistic payment attempt tracking.
4. **No Auth System Needed:** As recommended in the brief, full login/registration was skipped so evaluators can switch parents easily via dropdown.
5. **Entity Naming Aligned with Brief:** Tables are named `parents`, `students`, `trial_classes`, `bookings`, and `payment_attempts` for clarity.
6. **SQLite Compatibility:** We used SQLite partial indexes (`WHERE status = 'CONFIRMED'`), which is supported natively without external server dependencies like MySQL or PostgreSQL.

---

## 5. Key Architecture & Backend Decisions

### Data Model & Schema

```text
+---------------+       1:N       +---------------+
|    parents    | --------------< |   students    |
+---------------+                 +---------------+
        |                                 |
        | 1:N                             | 1:N
        v                                 v
+-------------------------------------------------+
|                    bookings                     |
+-------------------------------------------------+
| id, parent_id, student_id, trial_class_id       |
| status (PENDING_PAYMENT, CONFIRMED,             |
|         PAYMENT_FAILED, CLASS_FULL)             |
+-------------------------------------------------+
        |                                 ^
        | 1:N                             | N:1
        v                                 |
+----------------------+          +---------------+
|   payment_attempts   |          | trial_classes |
+----------------------+          +---------------+
| id, booking_id       |          | id, subject   |
| amount, method       |          | teacher_name  |
| status, reference_id |          | max_capacity  |
+----------------------+          +---------------+
```

### Key API Endpoints
- `GET /api/trial-classes` - Lists active classes with real-time seat counts (`confirmed_seats`, `remaining_seats`, `is_full`).
- `GET /api/trial-classes/{id}` - Details for a specific class.
- `GET /api/trial-classes/{id}/roster` - Returns the confirmed student roster and parent contact info.
- `POST /api/bookings/checkout` - Main checkout entry point. Validates request, processes payment, and secures the seat atomically.

### Booking & Payment Statuses Used

- **`BookingStatus`**:
  - `PENDING_PAYMENT`: Initial state when booking record is created.
  - `CONFIRMED`: Seat is officially secured; child appears on the roster.
  - `PAYMENT_FAILED`: Payment was declined; child is NOT added to the roster.
  - `CLASS_FULL`: Payment was charged, but the last seat was taken by a competitor before lock resolution. Automatic refund triggered.

- **`PaymentStatus`**:
  - `PENDING`: Transaction initiated.
  - `SUCCESS`: Payment successfully charged.
  - `FAILED`: Payment declined by mock gateway.
  - `REFUNDED`: Compensating refund executed because the class filled up.

---

### How Duplicate Bookings Are Prevented

We use a **two-layer defense**:
1. **Application / Service Layer:** Before processing payment, `BookingService` checks if the student already has a `CONFIRMED` booking for the class. If yes, it throws a `422 Unprocessable Entity` validation exception.
2. **Database Hard Invariant (Partial Unique Index):**
   ```sql
   CREATE UNIQUE INDEX unique_confirmed_student_class 
   ON bookings (student_id, trial_class_id) 
   WHERE status = 'CONFIRMED';
   ```
   *Why a partial index?* If a student's first payment attempt fails (`PAYMENT_FAILED`), standard unique indexes would block them from retrying. With a partial index, retries are allowed, but two `CONFIRMED` rows for the same student and class can never exist in the database.

---

### How Payment Failure Is Handled

1. If `simulate_failure` is checked or a gateway card decline occurs:
2. A `payment_attempts` record is stored with status `FAILED`.
3. The booking status is updated to `PAYMENT_FAILED`.
4. The transaction stops immediately before touching capacity calculation.
5. The student is **never added** to the confirmed roster.
6. The partial unique index ensures the student can try again later with another card without database constraint conflicts.

---

### Required Technical Scenario: Last-Seat Race Handling

#### The Scenario
1. **User A** selects the 4th (last) slot and goes to payment.
2. **User B** selects the same slot at the same time.
3. **User B** completes payment first and confirms the booking.
4. **User A** completes payment slightly after User B.

#### Approach Chosen: Pessimistic Locking with Post-Payment Evaluation & Auto-Refund
Inside `BookingService::checkout()`, when payment succeeds:
```php
DB::transaction(function () use ($booking, $paymentAttempt, $trialClass) {
    // 1. Lock the class row for updates
    $lockedClass = TrialClass::where('id', $trialClass->id)->lockForUpdate()->firstOrFail();

    // 2. Count strictly confirmed bookings inside the locked transaction
    $confirmedCount = Booking::where('trial_class_id', $lockedClass->id)
        ->where('status', BookingStatus::Confirmed)
        ->count();

    // 3. Evaluate capacity
    if ($confirmedCount >= $lockedClass->max_capacity) {
        $booking->update(['status' => BookingStatus::ClassFull]);
        $this->paymentService->refund($paymentAttempt); // Auto refund!
        return $booking;
    }

    // 4. Confirm seat
    $booking->update(['status' => BookingStatus::Confirmed]);
    return $booking;
});
```

#### Why This Approach?
- It guarantees **Zero Overbooking** at the database layer through transaction serialization.
- It prevents **abandoned carts from holding seats hostage**. In systems with a "10-minute hold timer", users often start payment and leave, preventing other paying parents from booking the last slot.
- The critical section inside the lock is tiny (~2ms: count rows and update status), so database lock contention is minimal.

#### Tradeoffs Accepted
- If User A and User B pay at the same exact second, User A will have their card charged and then immediately refunded with status `CLASS_FULL`. In real-world payment gateways, this is handled via synchronous pre-authorization release or instant refund void.

---

### Responsibility Matrix (Where Checks Belong)

| Check / Action | UI | Backend (Service/API) | Database | Background Job |
|---|:---:|:---:|:---:|:---:|
| Filter student by parent | ✅ | ❌ | ❌ | ❌ |
| Show real-time seats left badge | ✅ | ✅ | ❌ | ❌ |
| Input & enum format validation | ❌ | ✅ | ❌ | ❌ |
| Duplicate check before payment | ❌ | ✅ | ❌ | ❌ |
| Hard Invariant: No duplicate confirmed | ❌ | ❌ | ✅ (Partial Index) | ❌ |
| Capacity evaluation & atomic lock | ❌ | ✅ | ✅ (`lockForUpdate`) | ❌ |
| Automatic mock refund trigger | ❌ | ✅ | ❌ | ❌ |
| Email receipt & roster webhook dispatch | ❌ | ❌ | ❌ | ✅ (Async Queue) |

---

## 6. Seed Data & Edge Cases

The database seeder (`database/seeders/DatabaseSeeder.php`) provides 4 specific classes to test each requirement easily:

| ID | Subject | Seeded State | Purpose / Edge Case Tested |
|:--:|---|:---:|---|
| **1** | **Roblox Science Explorer** | 1/4 Confirmed (Kenzi) | **Duplicate Booking Test:** Kenzi is already confirmed. Submitting another booking for Kenzi will trigger a 422 duplicate error. |
| **2** | **Math Olympiad for Kids** | 3/4 Confirmed | **Last-Seat Race Target:** Exactly 1 seat left! Target for `php artisan test:last-seat-race`. |
| **3** | **Space Astronomy Lab** | 4/4 Confirmed (FULL) | **Full Class Test:** Booking attempt will be rejected immediately with `CLASS_FULL` (Zero Overbooking). |
| **4** | **AI & Coding Adventures** | 0/4 Confirmed | **Payment Failure Test:** Contains a failed payment history from Aisha to show failed payments never enter the roster. |

---

## 7. What Was Deliberately Cut

To stay strictly within the 3-4 hours timebox and prioritize backend correctness:
1. **User Authentication & Authorization:** Cut out login/passwords. Used direct Parent dropdown selection to make testing fast.
2. **Third-Party Payment SDKs (Stripe/Xendit):** Used a clean `MockPaymentService` instead of setting up external gateway credentials and webhooks.
3. **Bulk / Multi-Child Checkout:** Kept checkout to 1 child per transaction.
4. **Attendance Tracking & Calendar Invites:** Cut post-booking admin features not required in the trial booking slice.

---

## 8. What We Would Monitor After Release

1. **Overbooking Invariant Alert (P0 / Critical):**
   - Scheduled alert query: `SELECT trial_class_id, COUNT(*) FROM bookings WHERE status = 'CONFIRMED' GROUP BY trial_class_id HAVING COUNT(*) > 4`.
   - Any result triggers an immediate engineering alert.
2. **`CLASS_FULL` & Refund Rate Spike:**
   - A sudden rise in auto-refunds indicates high contention for popular slots. Signal to add more teacher schedules.
3. **Payment Gateway Decline Ratio:**
   - Track gateway failure spikes to detect third-party payment provider outages.
4. **Database Lock Contention Latency:**
   - Monitor P95 and P99 latency on `POST /api/bookings/checkout` to ensure `lockForUpdate` transactions remain under 10ms.

---

## 9. What We Would Do Next (With More Time)

1. **Automated Waitlist System:** When a confirmed student cancels, the system automatically offers the freed seat to the parent who was refunded during a race condition.
2. **Idempotency Keys:** Require a client-generated UUID header (`Idempotency-Key`) on checkout requests to prevent double-charging on network dropouts.
3. **Async Webhook Handlers:** Support Stripe/PayPal asynchronous webhooks with signature verification and retry backoff.
4. **Automated Notifications:** Send SMS/WhatsApp confirmation receipts and calendar `.ics` invites to parents.
