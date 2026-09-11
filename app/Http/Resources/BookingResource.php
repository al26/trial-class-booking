<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
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
            'status' => $this->status?->value ?? (string) $this->status,
            'parent' => new ParentResource($this->whenLoaded('parent')),
            'student' => new StudentResource($this->whenLoaded('student')),
            'trial_class' => new TrialClassResource($this->whenLoaded('trialClass')),
            'payment_attempts' => PaymentAttemptResource::collection($this->whenLoaded('paymentAttempts')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
