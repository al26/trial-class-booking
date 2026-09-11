<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Models\TrialClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrialClass
 */
class TrialClassResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'teacher_name' => $this->teacher_name,
            'schedule_date' => $this->schedule_date?->format('Y-m-d'),
            'schedule_time' => $this->schedule_time,
            'max_capacity' => (int) $this->max_capacity,
            'price' => (int) $this->price,
            'confirmed_seats' => $this->confirmedBookingsCount(),
            'remaining_seats' => $this->remainingSeats(),
            'is_full' => $this->isFull(),
            'is_active' => (bool) $this->is_active,
            'roster' => $this->whenLoaded('bookings', function () {
                return $this->bookings
                    ->where('status', BookingStatus::Confirmed)
                    ->map(function ($booking) {
                        return [
                            'booking_id' => $booking->id,
                            'student' => new StudentResource($booking->student),
                            'parent' => new ParentResource($booking->parent),
                            'confirmed_at' => $booking->updated_at?->toIso8601String(),
                        ];
                    })->values();
            }),
        ];
    }
}
