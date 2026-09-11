<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\TrialClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        protected MockPaymentService $paymentService
    ) {}

    /**
     * Checkout and confirm a trial class booking.
     *
     * @param  array{parent_id: int, student_id: int, trial_class_id: int, payment_method: PaymentMethod|string, simulate_failure?: bool}  $data
     *
     * @throws ValidationException
     */
    public function checkout(array $data): Booking
    {
        $trialClassId = (int) $data['trial_class_id'];
        $studentId = (int) $data['student_id'];
        $parentId = (int) $data['parent_id'];
        $paymentMethod = $data['payment_method'] instanceof PaymentMethod
            ? $data['payment_method']
            : PaymentMethod::from($data['payment_method']);
        $simulateFailure = (bool) ($data['simulate_failure'] ?? false);

        // 1. Pre-check: Invariant guard against duplicate confirmed booking for the same student and class
        $alreadyConfirmed = Booking::where('student_id', $studentId)
            ->where('trial_class_id', $trialClassId)
            ->where('status', BookingStatus::Confirmed)
            ->exists();

        if ($alreadyConfirmed) {
            throw ValidationException::withMessages([
                'student_id' => ['This student already has a confirmed booking for this trial class.'],
            ]);
        }

        $trialClass = TrialClass::findOrFail($trialClassId);

        // 2. Pre-check: Don't take payment if class is already full before checkout
        if ($trialClass->isFull()) {
            $booking = Booking::create([
                'parent_id' => $parentId,
                'student_id' => $studentId,
                'trial_class_id' => $trialClass->id,
                'status' => BookingStatus::ClassFull,
            ]);

            $booking->load(['parent', 'student', 'trialClass', 'paymentAttempts']);

            return $booking;
        }

        // 3. Create initial pending booking
        $booking = Booking::create([
            'parent_id' => $parentId,
            'student_id' => $studentId,
            'trial_class_id' => $trialClass->id,
            'status' => BookingStatus::PendingPayment,
        ]);

        // 4. Process payment attempt
        $paymentResult = $this->paymentService->process($trialClass->price, $paymentMethod, $simulateFailure);

        $paymentAttempt = PaymentAttempt::create([
            'booking_id' => $booking->id,
            'amount' => $trialClass->price,
            'payment_method' => $paymentMethod,
            'status' => $paymentResult['status'],
            'reference_id' => $paymentResult['reference_id'],
        ]);

        // 5. If payment failed, student is never confirmed nor added to roster
        if ($paymentResult['status'] === PaymentStatus::Failed) {
            $booking->update([
                'status' => BookingStatus::PaymentFailed,
            ]);

            $booking->load(['parent', 'student', 'trialClass', 'paymentAttempts']);

            return $booking;
        }

        // 6. Concurrency-safe final seat allocation with pessimistic locking
        return DB::transaction(function () use ($booking, $paymentAttempt, $trialClass) {
            /** @var TrialClass $lockedClass */
            $lockedClass = TrialClass::where('id', $trialClass->id)
                ->lockForUpdate()
                ->firstOrFail();

            $confirmedCount = Booking::where('trial_class_id', $lockedClass->id)
                ->where('status', BookingStatus::Confirmed)
                ->count();

            // Scenario: Lost the last-seat race (Competitor confirmed first while this payment was processing)
            if ($confirmedCount >= $lockedClass->max_capacity) {
                $booking->update([
                    'status' => BookingStatus::ClassFull,
                ]);

                // Refund payment attempt
                $this->paymentService->refund($paymentAttempt);

                $booking->load(['parent', 'student', 'trialClass', 'paymentAttempts']);

                return $booking;
            }

            // Confirmed booking for the available seat
            $booking->update([
                'status' => BookingStatus::Confirmed,
            ]);

            $booking->load(['parent', 'student', 'trialClass', 'paymentAttempts']);

            return $booking;
        });
    }
}
