<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutBookingRequest;
use App\Http\Resources\BookingResource;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BookingApiController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Submit and process a trial booking checkout.
     */
    public function checkout(CheckoutBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->checkout($request->validated());

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
