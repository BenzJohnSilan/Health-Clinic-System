<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('doctor_weekly_schedules', function (Blueprint $table) {
            $table->id();

            // 👨‍⚕️ Doctor this weekly schedule belongs to
            $table->foreignId('doctor_id')
                ->constrained('users')
                ->onDelete('cascade');

            // 📅 Day of week this row represents
            $table->enum('day_of_week', [
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
                'Sunday',
            ]);

            // 📌 Available or Unavailable for this day
            $table->enum('status', ['Available', 'Unavailable'])->default('Unavailable');

            $table->timestamps();

            // 🔐 One row per doctor per day (no duplicate day schedules)
            $table->unique(['doctor_id', 'day_of_week']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('doctor_weekly_schedules');
    }
};
