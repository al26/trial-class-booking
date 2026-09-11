<?php

use App\Enums\BookingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('trial_class_id')->constrained('trial_classes')->cascadeOnDelete();
            $table->string('status')->default(BookingStatus::PendingPayment->value);
            $table->timestamps();
        });

        // Partial unique index: at most one confirmed booking for a student per trial class
        DB::statement("CREATE UNIQUE INDEX unique_confirmed_student_class ON bookings (student_id, trial_class_id) WHERE status = 'CONFIRMED'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
