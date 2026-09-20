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
                ->nullOnDelete();   // ✅ NULL ang patient_id pag na-delete ang user
                                    //    hindi matatanggal yung appointment record

            // 🚶 Walk-in patient (patients table) — null if registered
            $table->foreignId('walkin_patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();

            // 👨‍⚕️ Doctor
            $table->foreignId('doctor_id')
                ->constrained('users')
                ->onDelete('cascade');  // ✅ Kapag na-delete ang doctor,
                                        //    matatanggal din lahat ng kanyang appointments

            // 📅 Schedule
            $table->date('appointment_date');
            $table->time('appointment_time');

            // 📌 Status
            // Note: "Checked In" at "In Progress" idinagdag para sa
            // Staff Check-In -> Doctor Consultation -> Medicine Dispensing
            // workflow.
            $table->enum('status', [
                'Pending',
                'Approved',
                'Checked In',
                'In Progress',
                'Completed',
                'Rejected',
                'Cancelled',
                'Rescheduled',
                'No Show',
            ])->default('Pending');

            // 🔁 Reschedule info
            $table->foreignId('rescheduled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // 📝 Staff/Doctor's reason for rescheduling (separate from the
            // patient's original `reason` for booking the appointment).
            $table->text('reschedule_reason')->nullable();

            // 🕒 When the reschedule action happened (distinct from
            // `updated_at`, which changes on any appointment update).
            $table->dateTime('rescheduled_at')->nullable();

            // 📝 Original reason submitted by the patient
            $table->text('reason')->nullable();

            // ❌ Staff/Doctor's reason for rejecting the appointment
            $table->text('rejection_reason')->nullable();

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