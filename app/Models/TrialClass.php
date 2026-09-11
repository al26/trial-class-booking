<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject', 'teacher_name', 'schedule_date', 'schedule_time', 'max_capacity', 'price', 'is_active'])]
class TrialClass extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'max_capacity' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the count of confirmed bookings for this class.
     */
    public function confirmedBookingsCount(): int
    {
        return $this->bookings()->where('status', BookingStatus::Confirmed->value)->count();
    }

    /**
     * Calculate remaining available seats.
     */
    public function remainingSeats(): int
    {
        return max(0, $this->max_capacity - $this->confirmedBookingsCount());
    }

    /**
     * Check if class is fully booked.
     */
    public function isFull(): bool
    {
        return $this->confirmedBookingsCount() >= $this->max_capacity;
    }
}
