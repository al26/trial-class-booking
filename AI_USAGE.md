# AI Usage Disclosure & Workflow Reflection

This document details how AI assistance was utilized, directed, challenged, and verified throughout the implementation of the Ottodot Trial Class Booking engine, in accordance with the take-home challenge brief.

---

## 1. Which AI Tools Were Used

- **Opencode AI / Antigravity CLI:** Powered by the `gemini-3.8-flash` model as the primary interactive coding assistant.
- **Laravel Boost MCP Server:** Utilized for inspecting framework-specific conventions and testing standards within modern Laravel 11/12 ecosystems.

---

## 2. What AI Was Used For

AI was used as an active pair programmer across the following tasks:
1. **Initial Scaffolding:** Generating draft migration schemas, models, and form requests based on the entity definitions in the brief.
2. **Edge-Case Synthetic Data Modeling:** Crafting the initial structure for `DatabaseSeeder.php` with 4 distinct edge-case trial classes.
3. **Pest Test Suite Drafting:** Writing comprehensive test assertions for happy path, duplicate bookings, payment decline isolation, and retry flows.
4. **Artisan Concurrency Command:** Writing the multi-process runner (`app/Console/Commands/TestLastSeatRace.php`) using `Symfony\Component\Process\Process` to simulate real-world simultaneous checkout requests.

---

## 3. One Place Where AI Helped Move Faster

AI significantly accelerated the creation of the **parallel race condition simulator (`php artisan test:last-seat-race`)** and the **Pest feature test suite (TC-01 to TC-06)**.

Instead of writing repetitive boilerplate code for spawning background processes, capturing JSON outputs from stdout, and configuring 45+ test assertions by hand, the AI generated a working prototype in minutes. This allowed me to focus my energy on validating core invariants, database locking behaviors, and race edge cases.

---

## 4. Places Where I Disagreed With, Corrected, or Rejected AI Output

Human oversight was critical throughout this build. Here are key examples where AI suggestions were corrected or rejected:

### Case 1: Partial Unique Index vs. Standard Composite Unique Index
- **AI Proposal:** The AI initially suggested a standard Laravel migration unique index:
  ```php
  $table->unique(['student_id', 'trial_class_id']);
  ```
- **Why I Corrected It:** A simple composite unique index across `(student_id, trial_class_id)` introduces a critical bug: if a student's payment fails (`PAYMENT_FAILED`), the student is permanently blocked from retrying the checkout because the database rejects any new row for that pair.
- **My Solution:** I rejected the AI proposal and replaced it with a **partial unique index**:
  ```sql
  CREATE UNIQUE INDEX unique_confirmed_student_class 
  ON bookings (student_id, trial_class_id) 
  WHERE status = 'CONFIRMED';
  ```
  This guarantees at the database level that no two confirmed bookings exist for the same student, while still allowing retries if an earlier payment failed.

### Case 2: PHP Reserved Keyword Collision (`Parent`)
- **AI Proposal:** The AI created a model class named `Parent` to represent the parent entity.
- **Why I Corrected It:** In PHP, `Parent` is a reserved compile-time token (`parent::method()`), causing a fatal parser error (`Cannot use "Parent" as a class name as it is reserved`).
- **My Solution:** I renamed the Eloquent model to `ParentModel` while explicitly specifying `protected $table = 'parents';`, preserving clean table names without PHP keyword conflicts.

### Case 3: Over-Engineered Separate Roster Resource
- **AI Proposal:** The AI initially drafted a standalone `RosterResource` that duplicated most of `TrialClassResource`.
- **Why I Corrected It:** It was unnecessary boilerplate. In Laravel, the idiomatic way to include conditional sub-resources is using `$this->whenLoaded()`.
- **My Solution:** I instructed the AI to delete `RosterResource` and embed `roster` directly inside `TrialClassResource`, resolved only when the `bookings` relation is explicitly loaded.

### Case 4: Preserving Frontend Compilation in Composer Setup
- **AI Proposal:** During early script cleanup, the AI removed `npm install` and `npm run build` from `composer.json`'s setup script.
- **Why I Corrected It:** The interactive demo UI requires Tailwind CSS assets bundled via Vite. Removing frontend commands would break the demo out-of-the-box for evaluators.
- **My Solution:** I directed the AI to restore the full asset compilation pipeline in both `composer.json` and `./setup.sh`.

---

## 5. What I Would Change About My AI Workflow Next Time

If I were to approach this task again with an AI assistant, I would:

1. **Prompt Architectural Constraints Earlier:** State database dialect limitations (like SQLite partial index syntax) and language keywords upfront in the initial prompt to avoid trial-and-error iterations.
2. **Define Schema Contracts & DTOs First:** Agree on the JSON response shape and database invariants before prompting the AI to write controllers or services.
3. **Validate SQL Execution Plans First:** Ask the AI to output raw DDL and transaction SQL before converting them into Laravel migrations and Eloquent closures.

---

## 6. How The Final Implementation Was Verified

The entire solution was verified using three distinct validation layers:

1. **Automated Feature Test Suite (Pest):**
   - Ran `vendor/bin/pest` covering 10 tests and 57 assertions across all edge cases (happy path, double bookings, failed payments, retry after failure, capacity limit).
   - All tests run against an in-memory SQLite database (`:memory:`) and pass with 100% green status.
2. **Simultaneous Multi-Process Concurrency Verification:**
   - Ran `php artisan test:last-seat-race` repeatedly across multiple runs.
   - Verified that two parallel processes competing for the 4th seat resulted in exactly 1 `CONFIRMED` booking, 1 `CLASS_FULL` (refunded) booking, and strictly 4 confirmed students in the database.
3. **End-to-End Interactive UI Testing:**
   - Tested the live Web UI at `http://localhost:8000`.
   - Verified that flash alert feedback accurately reflected booking outcomes and that the live roster view updated immediately without showing failed or unconfirmed payments.
