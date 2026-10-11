<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();

            // Registered patient (users table)
            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');

            // Walk-in patient (patients table)
            $table->foreignId('walkin_patient_id')
                ->nullable()
                ->constrained('patients')
                ->onDelete('cascade');

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Appointment this record was created from. CASCADE (not
            // "set null"): if the appointment is deleted (e.g. Admin ->
            // Appointments -> Delete), its medical record should go with
            // it, not be left behind with a null appointment_id (every
            // MedicalRecord in the app is always created tied to a real
            // appointment — see DoctorAppointmentController's
            // MedicalRecord::updateOrCreate(['appointment_id' => ...])
            // calls — so appointment_id is never legitimately null
            // outside of that deletion).
            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');

            // Medical info
            $table->text('chief_complaint')->nullable();

            // History of Present Illness at Physical Examination — bahagi
            // ng bagong Staff Check-In -> Consultation workflow. Nullable
            // dahil pwede mag-Save Draft na hindi pa kumpleto ang fields.
            $table->text('history_of_present_illness')->nullable();
            $table->text('physical_examination')->nullable();

            $table->text('diagnosis')->nullable();
            $table->text('treatment')->nullable();
            $table->text('notes')->nullable();

            // Vital signs
            $table->string('blood_pressure')->nullable();
            $table->string('temperature')->nullable();
            $table->string('weight')->nullable();
            $table->string('height')->nullable();
            $table->string('pulse_rate')->nullable();
            $table->string('respiratory_rate')->nullable();
            $table->string('oxygen_saturation')->nullable();

            // Recorded by STAFF (before Check-In). The Doctor only views these.
            // {"pulse_rate": {"reason": "Equipment unavailable", "note": null}, ...}
            // A vital listed here was deliberately NOT taken/available; its
            // value column stays NULL (never a fake 0 / "N/A").
            $table->json('vitals_not_taken')->nullable();

            $table->foreignId('vitals_recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('vitals_recorded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};