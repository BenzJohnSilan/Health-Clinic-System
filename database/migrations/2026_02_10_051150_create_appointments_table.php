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

            // patients
            $table->foreignId('patient_id')
                ->constrained('users')
                ->onDelete('cascade');

            // doctors
            $table->foreignId('doctor_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->date('appointment_date');
            $table->time('appointment_time');

            // Pending, Approved, Rejected, Completed, Cancelled, Rescheduled
            $table->string('status')->default('Pending');

            // tracking reschedule source
            $table->string('rescheduled_by')->nullable();

            // reason for appointment
            $table->text('reason')->nullable();

            // 🩺 Doctor's diagnosis for this appointment
            $table->text('diagnosis')->nullable();

            /**
             * 🔐 IMPORTANT:
             * Prevent duplicate booking of same doctor, date, and time
             */
            $table->unique(['doctor_id', 'appointment_date', 'appointment_time']);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('appointments');
    }
};