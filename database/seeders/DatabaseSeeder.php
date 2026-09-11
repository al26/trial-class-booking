<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\ParentModel;
use App\Models\PaymentAttempt;
use App\Models\TrialClass;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Primary Demo Parents & Students
        $parentBudi = ParentModel::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ]);
        $kenzi = $parentBudi->students()->create(['name' => 'Kenzi', 'age' => 8]);
        $kira = $parentBudi->students()->create(['name' => 'Kira', 'age' => 10]);

        $parentSiti = ParentModel::create([
            'name' => 'Siti Rahma',
            'email' => 'siti@example.com',
        ]);
        $aisha = $parentSiti->students()->create(['name' => 'Aisha', 'age' => 7]);
        $rayyan = $parentSiti->students()->create(['name' => 'Rayyan', 'age' => 9]);

        // 2. Synthetic Background Parents & Students for Capacity Seeding
        $bgParent = ParentModel::create([
            'name' => 'Ottodot Demo Parent',
            'email' => 'demo.parent@example.com',
        ]);
        $bgStudent1 = $bgParent->students()->create(['name' => 'Leo Tan', 'age' => 8]);
        $bgStudent2 = $bgParent->students()->create(['name' => 'Maya Lim', 'age' => 9]);
        $bgStudent3 = $bgParent->students()->create(['name' => 'Noah Lee', 'age' => 8]);
        $bgStudent4 = $bgParent->students()->create(['name' => 'Emma Wong', 'age' => 10]);

        // 3. Classes
        // Class A: 1 confirmed (Kenzi), 3 remaining seats -> Available + ready for Duplicate Booking test
        $classA = TrialClass::create([
            'subject' => 'Roblox Science Explorer',
            'teacher_name' => 'Teacher Sarah',
            'schedule_date' => Carbon::now()->addDays(1)->format('Y-m-d'),
            'schedule_time' => '10:00 - 11:00 AM',
            'max_capacity' => 4,
            'price' => 2500,
            'is_active' => true,
        ]);

        $bookingKenzi = Booking::create([
            'parent_id' => $parentBudi->id,
            'student_id' => $kenzi->id,
            'trial_class_id' => $classA->id,
            'status' => BookingStatus::Confirmed,
        ]);
        PaymentAttempt::create([
            'booking_id' => $bookingKenzi->id,
            'amount' => $classA->price,
            'payment_method' => PaymentMethod::CreditCard,
            'status' => PaymentStatus::Success,
            'reference_id' => 'SEED-PAY-KENZI-01',
        ]);

        // Class B: Exactly 3 confirmed students, 1 remaining seat -> Target for Last-Seat Race test
        $classB = TrialClass::create([
            'subject' => 'Math Olympiad for Kids',
            'teacher_name' => 'Teacher Alex',
            'schedule_date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'schedule_time' => '02:00 - 03:00 PM',
            'max_capacity' => 4,
            'price' => 2500,
            'is_active' => true,
        ]);

        foreach ([$bgStudent1, $bgStudent2, $bgStudent3] as $idx => $student) {
            $booking = Booking::create([
                'parent_id' => $bgParent->id,
                'student_id' => $student->id,
                'trial_class_id' => $classB->id,
                'status' => BookingStatus::Confirmed,
            ]);
            PaymentAttempt::create([
                'booking_id' => $booking->id,
                'amount' => $classB->price,
                'payment_method' => PaymentMethod::CreditCard,
                'status' => PaymentStatus::Success,
                'reference_id' => 'SEED-PAY-RACE-0'.($idx + 1),
            ]);
        }

        // Class C: Fully booked (4 confirmed students) -> Full class edge case
        $classC = TrialClass::create([
            'subject' => 'Space Astronomy Lab',
            'teacher_name' => 'Teacher David',
            'schedule_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'schedule_time' => '04:00 - 05:00 PM',
            'max_capacity' => 4,
            'price' => 2500,
            'is_active' => true,
        ]);

        $extraParent = ParentModel::create(['name' => 'Parent Anita', 'email' => 'anita@example.com']);
        $extraStudent1 = $extraParent->students()->create(['name' => 'Lucas', 'age' => 9]);
        $extraStudent2 = $extraParent->students()->create(['name' => 'Chloe', 'age' => 8]);
        $extraStudent3 = $extraParent->students()->create(['name' => 'Ethan', 'age' => 10]);

        foreach ([$bgStudent4, $extraStudent1, $extraStudent2, $extraStudent3] as $idx => $student) {
            $booking = Booking::create([
                'parent_id' => $student->parent_id,
                'student_id' => $student->id,
                'trial_class_id' => $classC->id,
                'status' => BookingStatus::Confirmed,
            ]);
            PaymentAttempt::create([
                'booking_id' => $booking->id,
                'amount' => $classC->price,
                'payment_method' => PaymentMethod::BankTransfer,
                'status' => PaymentStatus::Success,
                'reference_id' => 'SEED-PAY-FULL-0'.($idx + 1),
            ]);
        }

        // Class D: Empty class -> Ready for fresh bookings & payment failure demo
        $classD = TrialClass::create([
            'subject' => 'AI & Coding Adventures',
            'teacher_name' => 'Teacher Maya',
            'schedule_date' => Carbon::now()->addDays(4)->format('Y-m-d'),
            'schedule_time' => '01:00 - 02:00 PM',
            'max_capacity' => 4,
            'price' => 2500,
            'is_active' => true,
        ]);

        // Demo payment failure record (to demonstrate failed payment attempt doesn't add child to roster)
        $failedBooking = Booking::create([
            'parent_id' => $parentSiti->id,
            'student_id' => $aisha->id,
            'trial_class_id' => $classD->id,
            'status' => BookingStatus::PaymentFailed,
        ]);
        PaymentAttempt::create([
            'booking_id' => $failedBooking->id,
            'amount' => $classD->price,
            'payment_method' => PaymentMethod::CreditCard,
            'status' => PaymentStatus::Failed,
            'reference_id' => 'SEED-PAY-FAIL-AISHA',
        ]);
    }
}
