<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\ParentModel;
use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\TrialClass;
use App\Services\BookingService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TestLastSeatRace extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:last-seat-race 
                            {--worker : Internal flag for parallel execution worker}
                            {--student= : Student ID for worker mode}
                            {--parent= : Parent ID for worker mode}
                            {--class= : Trial Class ID for worker mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate and verify concurrent last-seat race condition handling';

    /**
     * Execute the console command.
     */
    public function handle(BookingService $bookingService): int
    {
        if ($this->option('worker')) {
            return $this->runWorker($bookingService);
        }

        return $this->runOrchestrator();
    }

    /**
     * Worker execution: Runs checkout in an isolated process and outputs JSON.
     */
    protected function runWorker(BookingService $bookingService): int
    {
        $studentId = (int) $this->option('student');
        $parentId = (int) $this->option('parent');
        $classId = (int) $this->option('class');

        try {
            $booking = $bookingService->checkout([
                'parent_id' => $parentId,
                'student_id' => $studentId,
                'trial_class_id' => $classId,
                'payment_method' => PaymentMethod::CreditCard,
            ]);

            $latestPayment = $booking->paymentAttempts->first();

            $output = [
                'success' => true,
                'student_id' => $studentId,
                'booking_id' => $booking->id,
                'booking_status' => $booking->status->value,
                'payment_status' => $latestPayment?->status->value ?? 'N/A',
                'reference_id' => $latestPayment?->reference_id ?? 'N/A',
            ];

            $this->line('RESULT_JSON:'.json_encode($output));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output = [
                'success' => false,
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ];

            $this->line('RESULT_JSON:'.json_encode($output));

            return Command::FAILURE;
        }
    }

    /**
     * Orchestrator execution: Resets state, spawns 2 concurrent workers, and validates invariants.
     */
    protected function runOrchestrator(): int
    {
        $this->info('===============================================================');
        $this->info(' OTTODOT TRIAL BOOKING - LAST-SEAT RACE CONDITION VERIFICATION ');
        $this->info('===============================================================');

        // 1. Locate or create Class 2 (Math Olympiad)
        $trialClass = TrialClass::where('subject', 'like', '%Math Olympiad%')->first();

        if (! $trialClass) {
            $trialClass = TrialClass::firstOrCreate(
                ['subject' => 'Math Olympiad for Kids'],
                [
                    'teacher_name' => 'Teacher Alex',
                    'schedule_date' => now()->addDays(2)->format('Y-m-d'),
                    'schedule_time' => '02:00 - 03:00 PM',
                    'max_capacity' => 4,
                    'price' => 2500,
                    'is_active' => true,
                ]
            );
        }

        // 2. Locate or create Candidate competitors: Kenzi and Rayyan
        $parentBudi = ParentModel::firstOrCreate(['email' => 'budi@example.com'], ['name' => 'Budi Santoso']);
        $kenzi = Student::firstOrCreate(['name' => 'Kenzi', 'parent_id' => $parentBudi->id], ['age' => 8]);

        $parentSiti = ParentModel::firstOrCreate(['email' => 'siti@example.com'], ['name' => 'Siti Rahma']);
        $rayyan = Student::firstOrCreate(['name' => 'Rayyan', 'parent_id' => $parentSiti->id], ['age' => 9]);

        // Background parent and 3 students to fill 3 seats
        $bgParent = ParentModel::firstOrCreate(['email' => 'demo.parent@example.com'], ['name' => 'Ottodot Demo Parent']);
        $bgStudent1 = Student::firstOrCreate(['name' => 'Leo Tan', 'parent_id' => $bgParent->id], ['age' => 8]);
        $bgStudent2 = Student::firstOrCreate(['name' => 'Maya Lim', 'parent_id' => $bgParent->id], ['age' => 9]);
        $bgStudent3 = Student::firstOrCreate(['name' => 'Noah Lee', 'parent_id' => $bgParent->id], ['age' => 8]);

        $this->line("Preparing database state for Class ID {$trialClass->id} ('{$trialClass->subject}')...");

        // Reset all existing bookings for this class
        Booking::where('trial_class_id', $trialClass->id)->delete();

        // Seed exactly 3 confirmed bookings (3/4 seats filled)
        foreach ([$bgStudent1, $bgStudent2, $bgStudent3] as $idx => $student) {
            $b = Booking::create([
                'parent_id' => $bgParent->id,
                'student_id' => $student->id,
                'trial_class_id' => $trialClass->id,
                'status' => BookingStatus::Confirmed,
            ]);
            PaymentAttempt::create([
                'booking_id' => $b->id,
                'amount' => $trialClass->price,
                'payment_method' => PaymentMethod::CreditCard,
                'status' => PaymentStatus::Success,
                'reference_id' => 'SEED-RACE-0'.($idx + 1),
            ]);
        }

        $initialConfirmed = $trialClass->fresh()->confirmedBookingsCount();
        $this->info("Current Capacity: {$initialConfirmed} / {$trialClass->max_capacity} Confirmed seats.");
        $this->warn('1 Seat Remaining! Two users will now compete simultaneously for this final slot.');
        $this->newLine();

        $this->comment("User A: '{$kenzi->name}' (Parent: {$parentBudi->name})");
        $this->comment("User B: '{$rayyan->name}' (Parent: {$parentSiti->name})");
        $this->newLine();

        $artisanPath = base_path('artisan');
        $phpBinary = PHP_BINARY;

        // 3. Launch 2 parallel processes simultaneously using Symfony Process
        $cmdA = [$phpBinary, $artisanPath, 'test:last-seat-race', '--worker', "--student={$kenzi->id}", "--parent={$parentBudi->id}", "--class={$trialClass->id}"];
        $cmdB = [$phpBinary, $artisanPath, 'test:last-seat-race', '--worker', "--student={$rayyan->id}", "--parent={$parentSiti->id}", "--class={$trialClass->id}"];

        $processA = new Process($cmdA);
        $processB = new Process($cmdB);

        $this->info('Firing concurrent checkout requests in parallel...');
        $processA->start();
        $processB->start();

        // Wait for both to complete
        $processA->wait();
        $processB->wait();

        // Parse outputs
        $resA = $this->parseWorkerOutput($processA->getOutput());
        $resB = $this->parseWorkerOutput($processB->getOutput());

        $this->newLine();
        $this->info('Execution Complete! Parsing results:');

        $tableData = [
            [
                'User A',
                $kenzi->name,
                $resA['booking_status'] ?? 'ERROR',
                $resA['payment_status'] ?? 'ERROR',
                $resA['reference_id'] ?? ($resA['error'] ?? 'N/A'),
            ],
            [
                'User B',
                $rayyan->name,
                $resB['booking_status'] ?? 'ERROR',
                $resB['payment_status'] ?? 'ERROR',
                $resB['reference_id'] ?? ($resB['error'] ?? 'N/A'),
            ],
        ];

        $this->table(['Process', 'Student', 'Booking Status', 'Payment Status', 'Reference / Info'], $tableData);

        // 4. Invariant Verification
        $finalConfirmed = $trialClass->fresh()->confirmedBookingsCount();
        $statuses = [
            $resA['booking_status'] ?? null,
            $resB['booking_status'] ?? null,
        ];

        $confirmedCount = count(array_filter($statuses, fn ($s) => $s === BookingStatus::Confirmed->value));
        $classFullCount = count(array_filter($statuses, fn ($s) => $s === BookingStatus::ClassFull->value));

        $this->newLine();
        $this->info("Class Final Confirmed Bookings in DB: {$finalConfirmed} / {$trialClass->max_capacity}");

        if ($confirmedCount === 1 && $classFullCount === 1 && $finalConfirmed === 4) {
            $this->info('---------------------------------------------------------------');
            $this->info(' [PASS] ZERO OVERBOOKING INVARIANT SUCCESSFULLY PRESERVED!');
            $this->info(' - Exactly 1 user claimed the last available seat (CONFIRMED).');
            $this->info(' - Exactly 1 user was rejected (CLASS_FULL) & refunded.');
            $this->info(' - Total confirmed students in database is strictly 4.');
            $this->info('---------------------------------------------------------------');

            return Command::SUCCESS;
        }

        $this->error('---------------------------------------------------------------');
        $this->error(' [FAIL] Concurrency race condition violated!');
        $this->error(" Expected 1 Confirmed and 1 ClassFull. Got: Confirmed={$confirmedCount}, ClassFull={$classFullCount}, DB Confirmed={$finalConfirmed}");
        $this->error('---------------------------------------------------------------');

        return Command::FAILURE;
    }

    /**
     * Parse worker output line containing RESULT_JSON.
     *
     * @return array<string, mixed>
     */
    protected function parseWorkerOutput(string $output): array
    {
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, 'RESULT_JSON:')) {
                $json = substr($trimmed, strlen('RESULT_JSON:'));

                return json_decode($json, true) ?: [];
            }
        }

        return ['success' => false, 'error' => 'No JSON output returned: '.$output];
    }
}
