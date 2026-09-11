<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trial_classes', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('teacher_name');
            $table->date('schedule_date');
            $table->string('schedule_time');
            $table->unsignedInteger('max_capacity')->default(4);
            $table->unsignedInteger('price')->default(2500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trial_classes');
    }
};
