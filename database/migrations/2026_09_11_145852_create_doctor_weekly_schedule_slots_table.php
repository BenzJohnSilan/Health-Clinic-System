<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('doctor_weekly_schedule_slots', function (Blueprint $table) {
            $table->id();

            // 🔗 Parent weekly schedule (one day, one doctor)
            $table->foreignId('doctor_weekly_schedule_id')
                ->constrained('doctor_weekly_schedules')
                ->onDelete('cascade');

            // 🕒 Time range for this slot (e.g. 8:00 AM - 12:00 PM)
            $table->time('start_time');
            $table->time('end_time');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('doctor_weekly_schedule_slots');
    }
};
