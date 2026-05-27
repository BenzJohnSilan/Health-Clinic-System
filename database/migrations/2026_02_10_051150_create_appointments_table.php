<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // 🔖 Reference Number
            $table->string('reference_no')->unique()->nullable();

            // 👤 Registered patient (users table) — null if walk-in
            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // 🚶 Walk-in patient (patients table) — null if registered
            $table->foreignId('walkin_patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();

            // 👨‍⚕️ Doctor
            $table->foreignId('doctor_id')
                ->constrained('users')
                ->onDelete('cascade');

            // 📅 Schedule
            $table->date('appointment_date');
            $table->time('appointment_time');

            // 📌 Status
            $table->enum('status', [
                'Pending',
                'Approved',
                'Rejected',
                'Completed',
                'Cancelled',
                'Rescheduled',
                'No Show',
            ])->default('Pending');

            // 🔁 Reschedule info
            $table->foreignId('rescheduled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // 📝 Reason for appointment / rejection reason
            $table->text('reason')->nullable();

            // 🔐 No duplicate slot per doctor
            $table->unique(['doctor_id', 'appointment_date', 'appointment_time']);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('appointments');
    }
};