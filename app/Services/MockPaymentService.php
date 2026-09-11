<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\PaymentAttempt;

class MockPaymentService
{
    /**
     * Simulate synchronous payment processing.
     *
     * @return array{status: PaymentStatus, reference_id: string}
     */
    public function process(int $amount, PaymentMethod|string $paymentMethod, bool $simulateFailure = false): array
    {
        if ($simulateFailure) {
            return [
                'status' => PaymentStatus::Failed,
                'reference_id' => 'PAY-FAIL-'.uniqid(),
            ];
        }

        return [
            'status' => PaymentStatus::Success,
            'reference_id' => 'PAY-SUCC-'.uniqid(),
        ];
    }

    /**
     * Refund a payment attempt.
     */
    public function refund(PaymentAttempt $payment): PaymentAttempt
    {
        $payment->update([
            'status' => PaymentStatus::Refunded,
        ]);

        return $payment;
    }
}
