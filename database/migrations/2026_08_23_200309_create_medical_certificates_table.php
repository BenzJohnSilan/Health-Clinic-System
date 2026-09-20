<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_certificates', function (Blueprint $table) {
            $table->id();

            // ================= RELATIONSHIPS =================
            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->onDelete('cascade');

            // Patient with an account (users table). Nullable so a doctor
            // can also create a certificate directly for a walk-in /
            // face-to-face patient who only has a record in the
            // `patients` table (no `users` account) — the certificate
            // still stays linked through `appointment_id`.
            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Doctor who issued/rejected it. Nullable because it is only
            // guaranteed once the request has been reviewed.
            $table->foreignId('doctor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ================= CERTIFICATE NUMBER =================
            // Only assigned at the moment of issuance.
            $table->string('certificate_number', 30)->nullable()->unique();

            // ================= REQUEST DETAILS (from patient) =================
            $table->enum('purpose', ['School', 'Work', 'Sick Leave', 'Other']);
            $table->string('other_purpose', 150)->nullable();
            $table->text('request_details')->nullable();

            // ================= ISSUED CERTIFICATE CONTENT (from doctor) =================
            $table->text('medical_statement')->nullable();
            $table->text('recommendation')->nullable();

            $table->date('rest_start_date')->nullable();
            $table->date('rest_end_date')->nullable();

            $table->text('additional_remarks')->nullable();

            // ================= STATUS =================
            $table->enum('status', ['pending', 'issued', 'rejected', 'correction_requested'])
                ->default('pending');

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_certificates');
    }
};