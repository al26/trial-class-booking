<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\ParentModel;
use App\Models\TrialClass;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

test('web UI renders booking demo page with classes and parents', function () {
    $parent = ParentModel::create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    $student = $parent->students()->create(['name' => 'Kenzi', 'age' => 8]);
    $class = TrialClass::create([
        'subject' => 'Roblox Science Explorer',
        'teacher_name' => 'Teacher Sarah',
        'schedule_date' => '2026-09-15',
        'schedule_time' => '10:00 - 11:00 AM',
        'max_capacity' => 4,
        'price' => 2500,
        'is_active' => true,
    ]);

    $response = $this->get(route('booking.index'));

    $response->assertStatus(200)
        ->assertSee('Ottodot Trial Booking')
        ->assertSee('Parent Booking Simulator')
        ->assertSee('Teacher / Admin Live Roster View')
        ->assertSee('Roblox Science Explorer')
        ->assertSee('Budi Santoso');
});

test('web UI allows parent to submit booking and redirects with flash message', function () {
    $parent = ParentModel::create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    $student = $parent->students()->create(['name' => 'Kira', 'age' => 10]);
    $class = TrialClass::create([
        'subject' => 'Roblox Science Explorer',
        'teacher_name' => 'Teacher Sarah',
        'schedule_date' => '2026-09-15',
        'schedule_time' => '10:00 - 11:00 AM',
        'max_capacity' => 4,
        'price' => 2500,
        'is_active' => true,
    ]);

    $payload = [
        'parent_id' => $parent->id,
        'student_id' => $student->id,
        'trial_class_id' => $class->id,
        'payment_method' => PaymentMethod::CreditCard->value,
    ];

    $response = $this->withoutMiddleware(PreventRequestForgery::class)
        ->post(route('booking.store'), $payload);

    $response->assertRedirect(route('booking.index'))
        ->assertSessionHas('booking_result');

    $this->assertDatabaseHas('bookings', [
        'student_id' => $student->id,
        'trial_class_id' => $class->id,
        'status' => BookingStatus::Confirmed->value,
    ]);
});
