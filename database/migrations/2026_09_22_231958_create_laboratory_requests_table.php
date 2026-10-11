<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_requests', function (Blueprint $table) {
            $table->id();

            // ================= RELATIONSHIPS =================
            // Always tied to the consultation it was created from.
            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->onDelete('cascade');

            // Registered patient (users table). Nullable so a walk-in
            // patient's request still resolves through walkin_patient_id
            // only — same nullable pattern already used by
            // medical_certificates / appointments.
            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Walk-in patient (patients table).
            $table->foreignId('walkin_patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();

            // Doctor who created the request. Nullable-on-delete only —
            // the request itself must never disappear if the doctor
            // account is later removed.
            $table->foreignId('doctor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ================= REQUEST NUMBER =================
            // Format: LAB-YYYYMMDD-00001 (see LaboratoryRequest::generateReferenceNo()).
            $table->string('reference_no', 30)->unique();

            // ================= REQUEST DETAILS (from Doctor) =================
            $table->text('diagnosis')->nullable();

            // Free-text description shown only when "Others" is checked
            // among the requested tests.
            $table->string('other_test_description', 255)->nullable();

            // ================= LABORATORY STATUS =================
            // Deliberately separate from appointments.status — see
            // Appointment/Laboratory workflow spec: the appointment can
            // be Completed while this stays Awaiting Result.
            //
            // "Correction Required" supports the correction workflow:
            // Result Received -> Correction Required (Doctor requests a
            // new upload, reason recorded in laboratory_result_corrections)
            // -> Result Received (patient/staff uploads again) -> Reviewed.
            $table->enum('status', ['Awaiting Result', 'Result Received', 'Correction Required', 'Reviewed'])
                ->default('Awaiting Result');

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('result_received_at')->nullable();

            // ================= DOCTOR REVIEW =================
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            // ================= FOLLOW-UP CONSULTATION =================
            // Set by the Doctor when marking the result as Reviewed
            // (Laboratory Result Follow-up Consultation feature).
            // Patient-facing — shown on the patient's Laboratory Results page.
            $table->boolean('follow_up_recommended')->default(false);
            $table->text('follow_up_instructions')->nullable();

            $table->timestamps();
        });

        // ================= APPOINTMENTS -> LABORATORY REQUEST LINK =================
        // Added here (via Schema::table), not inside the appointments
        // migration, because laboratory_requests does not exist yet at
        // the point the appointments table is created — this FK can only
        // be added once this table exists.
        Schema::table('appointments', function (Blueprint $table) {
            // The ORIGINAL laboratory request a Follow-up appointment was
            // booked for (null for every Regular appointment).
            // nullOnDelete: an appointment must never disappear because a
            // laboratory request was later removed.
            $table->foreignId('follow_up_laboratory_request_id')
                ->nullable()
                ->after('appointment_type')
                ->constrained('laboratory_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Drop the FK/column from appointments FIRST — it references
        // laboratory_requests, so it must go before that table is dropped.
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('follow_up_laboratory_request_id');
        });

        Schema::dropIfExists('laboratory_requests');
    }
};