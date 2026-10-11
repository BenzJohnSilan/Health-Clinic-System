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

            // ⏰ Reserved time slot. NULLABLE because a WALK-IN has no
            // reserved time (it uses queue_number + arrived_at instead).
            // Scheduled appointments always have a time. The unique index
            // below is unchanged: a unique index treats NULLs as distinct,
            // so walk-ins (NULL time) never collide with — or steal — a
            // reserved scheduled slot, while two scheduled appointments for
            // the same doctor/date/time are still rejected.
            $table->time('appointment_time')->nullable();

            // 📌 Status
            // Note: "Checked In" at "In Progress" idinagdag para sa
            // Staff Check-In -> Doctor Consultation -> Medicine Dispensing
            // workflow.
            // 'Waiting' = a walk-in added to today's queue, not yet checked
            // in. It is NOT 'Approved' (a reserved scheduled appointment).
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
                'Waiting',
            ])->default('Pending');

            // 🔵 Consultation Type (Laboratory Result Follow-up Consultation
            // feature) — "Regular" or "Follow-up". Plain string (not enum)
            // so it stays portable; no need for after() here — this is a
            // fresh CREATE TABLE, so the column already sits right after
            // `status` in definition order. The FK below
            // (follow_up_laboratory_request_id) is added later, once the
            // laboratory_requests table exists — see
            // create_laboratory_requests_table.php.
            // Walk-in Queue reuses this column: 'Walk-in' = queue-based visit
            // with no reserved time; 'Regular' / 'Follow-up' = scheduled.
            $table->string('appointment_type', 20)->default('Regular');

            // 🚶 Walk-in Queue (NULL for scheduled appointments)
            // Real arrival timestamp, and the daily queue number (Q001...).
            // Check-in / consultation start / completion times already live
            // in appointment_status_events, and vitals time in medical_records.
            $table->timestamp('arrived_at')->nullable();
            $table->string('queue_number', 10)->nullable();

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

            // 🧾 Charges the Doctor prepared while the consultation is still
            // In Progress (selected services + clinic medicines). NOT an
            // invoice: nothing here is collectible. Complete Consultation
            // turns it into the real invoice and clears it. Null = no draft.
            $table->json('billing_draft')->nullable();

            // 🔐 No duplicate slot per doctor
            $table->unique(['doctor_id', 'appointment_date', 'appointment_time']);

            // 🔢 No duplicate queue number on the same clinic day
            // (scheduled rows have NULL and are unaffected).
            $table->unique(['appointment_date', 'queue_number'], 'appointments_date_queue_number_unique');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('appointments');
    }
};