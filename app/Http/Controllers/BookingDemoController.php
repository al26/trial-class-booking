<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutBookingRequest;
use App\Models\ParentModel;
use App\Models\TrialClass;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingDemoController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Display the interactive single-page booking demo & live roster.
     */
    public function index(): View
    {
        $parents = ParentModel::with('students')->orderBy('name')->get();
        $trialClasses = TrialClass::where('is_active', true)
            ->with(['bookings.student', 'bookings.parent', 'bookings.paymentAttempts'])
            ->orderBy('id')
            ->get();

        return view('booking', compact('parents', 'trialClasses'));
    }

    /**
     * Handle trial booking submission from the interactive form.
     */
    public function store(CheckoutBookingRequest $request): RedirectResponse
    {
        try {
            $booking = $this->bookingService->checkout($request->validated());

            return redirect()->route('booking.index')->with('booking_result', [
                'status' => $booking->status->value,
                'student_name' => $booking->student->name,
                'class_name' => $booking->trialClass->subject,
                'booking_id' => $booking->id,
                'payment_status' => $booking->paymentAttempts->first()?->status->value,
                'reference_id' => $booking->paymentAttempts->first()?->reference_id,
            ]);
        } catch (ValidationException $e) {
            return redirect()->route('booking.index')
                ->withErrors($e->validator)
                ->withInput();
        }
    }
}
