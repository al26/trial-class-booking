<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\TrialClass;

beforeEach(function () {
    $this->parent = ParentModel::create([
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
    ]);

    $this->student = $this->parent->students()->create([
        'name' => 'Kenzi',
        'age' => 8,
    ]);

    $this->trialClass = TrialClass::create([
        'subject' => 'Roblox Science Explorer',
        'teacher_name' => 'Teacher Sarah',
        'schedule_date' => '2026-09-15',
        'schedule_time' => '10:00 - 11:00 AM',
        'max_capacity' => 4,
        'price' => 2500,
        'is_active' => true,
    ]);
});

test('TC-01: happy path trial booking creates confirmed booking and payment attempt', function () {
    $payload = [
        'parent_id' => $this->parent->id,
        'student_id' => $this->student->id,
        'trial_class_id' => $this->trialClass->id,
        'payment_method' => PaymentMethod::CreditCard->value,
    ];

    $response = $this->postJson(route('api.bookings.checkout'), $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', BookingStatus::Confirmed->value)
        ->assertJsonPath('data.parent.id', $this->parent->id)
        ->assertJsonPath('data.student.id', $this->student->id)
        ->assertJsonPath('data.trial_class.id', $this->trialClass->id);

    // Verify database state
    $this->assertDatabaseHas('bookings', [
        'parent_id' => $this->parent->id,
        'student_id' => $this->student->id,
        'trial_class_id' => $this->trialClass->id,
        'status' => BookingStatus::Confirmed->value,
    ]);

    $this->assertDatabaseHas('payment_attempts', [
        'amount' => 2500,
        'payment_method' => PaymentMethod::CreditCard->value,
        'status' => PaymentStatus::Success->value,
    ]);

    expect($this->trialClass->fresh()->confirmedBookingsCount())->toBe(1)
        ->and($this->trialClass->fresh()->remainingSeats())->toBe(3);

    // Verify student is in roster
    $rosterResponse = $this->getJson(route('api.trial-classes.roster', $this->trialClass));
    $rosterResponse->assertStatus(200)
        ->assertJsonCount(1, 'data.roster')
        ->assertJsonPath('data.roster.0.student.id', $this->student->id);
});

test('TC-02: duplicate confirmed booking for the same student and class is prevented', function () {
    // First confirmed booking
    Booking::create([
        'parent_id' => $this->parent->id,
        'student_id' => $this->student->id,
        'trial_class_id' => $this->trialClass->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $payload = [
        'parent_id' => $this->parent->id,
        'student_id' => $this->student->id,
        'trial_class_id' => $this->trialClass->id,
        'payment_method' => PaymentMethod::CreditCard->value,
    ];

    $response = $this->postJson(route('api.bookings.checkout'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['student_id']);

    // Ensure only 1 booking exists
    expect(Booking::where('student_id', $this->student->id)
        ->where('trial_class_id', $this->trialClass->id)
        ->count())->toBe(1);
});

test('TC-03: payment failure does not add child to confirmed roster', function () {
    $payload = [
        'parent_id' => $this->parent->id,
        'student_id' => $this->student->id,
        'trial_class_id' => $this->trialClass->id,
        'payment_method' => PaymentMethod::CreditCard->value,
        'simulate_failure' => true,
    ];

    $response = $this->postJson(route('api.bookings.checkout'), $payload);

    $response->assertStatus(422)
        ->assertJsonPath('data.status', BookingStatus::PaymentFailed->value);

    // Assert booking and payment recorded as failed
    $this->assertDatabaseHas('bookings', [
        'student_id' => $this->student->id,
        'trial_class_id' => $this->trialClass->id,
        'status' => BookingStatus::PaymentFailed->value,
    ]);

    $this->assertDatabaseHas('payment_attempts', [
        'status' => PaymentStatus::Failed->value,
    ]);

    // Ensure student is NOT on roster and remaining seats unchanged
    expect($this->trialClass->fresh()->confirmedBookingsCount())->toBe(0)
        ->and($this->trialClass->fresh()->remainingSeats())->toBe(4);

    $rosterResponse = $this->getJson(route('api.trial-classes.roster', $this->trialClass));
    $rosterResponse->assertStatus(200)
        ->assertJsonCount(0, 'data.roster');
});

test('TC-04: booking in a fully booked class is rejected with class full status and no overbooking', function () {
    // Seed 4 confirmed students to fill the class
    for ($i = 1; $i <= 4; $i++) {
        $otherParent = ParentModel::create([
            'name' => "Parent $i",
            'email' => "parent{$i}@example.com",
        ]);
        $otherStudent = $otherParent->students()->create([
            'name' => "Student $i",
            'age' => 8,
        ]);

        Booking::create([
            'parent_id' => $otherParent->id,
            'student_id' => $otherStudent->id,
            'trial_class_id' => $this->trialClass->id,
            'status' => BookingStatus::Confirmed,
        ]);
    }

    expect($this->trialClass->fresh()->isFull())->toBeTrue();

    // 5th student attempts checkout
    $payload = [
        'parent_id' => $this->parent->id,
        'student_id' => $this->student->id,
        'trial_class_id' => $this->trialClass->id,
        'payment_method' => PaymentMethod::CreditCard->value,
    ];

    $response = $this->postJson(route('api.bookings.checkout'), $payload);

    $response->assertStatus(409)
        ->assertJsonPath('data.status', BookingStatus::ClassFull->value);

    // Total confirmed bookings must strictly remain 4 (Zero Overbooking)
    expect($this->trialClass->fresh()->confirmedBookingsCount())->toBe(4);
});

test('TC-05: student can retry booking after a previous payment failure', function () {
    // 1st attempt: payment failure
    Booking::create([
        'parent_id' => $this->parent->id,
        'student_id' => $this->student->id,
        'trial_class_id' => $this->trialClass->id,
        'status' => BookingStatus::PaymentFailed,
    ]);

    // 2nd attempt: successful retry
    $payload = [
        'parent_id' => $this->parent->id,
        'student_id' => $this->student->id,
        'trial_class_id' => $this->trialClass->id,
        'payment_method' => PaymentMethod::CreditCard->value,
        'simulate_failure' => false,
    ];

    $response = $this->postJson(route('api.bookings.checkout'), $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', BookingStatus::Confirmed->value);

    expect(Booking::where('student_id', $this->student->id)
        ->where('trial_class_id', $this->trialClass->id)
        ->where('status', BookingStatus::Confirmed)
        ->count())->toBe(1);
});

test('TC-06: trial class index returns active classes with calculated seat metrics', function () {
    $response = $this->getJson(route('api.trial-classes.index'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'subject',
                    'teacher_name',
                    'schedule_date',
                    'schedule_time',
                    'max_capacity',
                    'price',
                    'confirmed_seats',
                    'remaining_seats',
                    'is_full',
                    'is_active',
                ],
            ],
        ]);
});
