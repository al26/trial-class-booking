<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TrialClass;

class BookingService
{
    public function __construct(
        protected MockPaymentService $paymentService
    ) {}

    /**
     * Placeholder checkout method for Phase 4.
     * Full concurrency & business logic implemented in Phase 3.
     *
     * @param  array{parent_id: int, student_id: int, trial_class_id: int, payment_method: string, simulate_failure?: bool}  $data
     */
    public function checkout(array $data): Booking
    {
        $trialClass = TrialClass::findOrFail($data['trial_class_id']);

        $booking = Booking::create([
            'parent_id' => $data['parent_id'],
            'student_id' => $data['student_id'],
            'trial_class_id' => $trialClass->id,
            'status' => BookingStatus::PendingPayment,
        ]);

        $booking->load(['parent', 'student', 'trialClass', 'paymentAttempts']);

        return $booking;
    }
}
