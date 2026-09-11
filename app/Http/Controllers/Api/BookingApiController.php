<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
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

        $statusCode = match ($booking->status) {
            BookingStatus::Confirmed => Response::HTTP_CREATED,
            BookingStatus::ClassFull => Response::HTTP_CONFLICT,
            BookingStatus::PaymentFailed => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => Response::HTTP_OK,
        };

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode($statusCode);
    }
}
