<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===================== USERS TABLE =====================
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // ================= PROFILE =================
            $table->string('avatar')->nullable();

            // ================= PERSONAL INFORMATION =================
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->date('birthdate');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->enum('civil_status', ['Single', 'Married', 'Widowed', 'Separated']);
            $table->string('address');

            // ================= CONTACT =================
            $table->string('contact_number')->unique();

            // ================= VERIFICATION =================
            $table->string('id_type')->nullable();
            $table->string('valid_id')->nullable();

            // ================= REASON =================
            $table->enum('reason', [
                'Check-up / Consultation',
                'Appointment Booking',
                'Medical Record Access',
                'Others',
            ])->nullable();

            // ================= MEDICAL INFORMATION =================
            $table->string('blood_type')->nullable();
            $table->text('allergies')->nullable();

            // ================= EMERGENCY CONTACT =================
            $table->string('emergency_name')->nullable();
            $table->string('emergency_contact_number')->nullable();
            $table->string('relationship')->nullable();
            $table->string('emergency_address')->nullable();

            // ================= LOGIN =================
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // ================= ROLE =================
            $table->enum('role', ['Admin', 'Patient', 'Doctor'])
                  ->default('Patient');

            // ================= ACCOUNT STATUS =================
            $table->enum('status', ['Active', 'Inactive'])
                  ->default('Active');

            // ================= APPROVAL =================
            $table->enum('approval_status', ['Pending', 'Approved', 'Rejected'])
                  ->default('Pending');

            $table->rememberToken();
            $table->timestamps();
        });

        // ===================== PASSWORD RESET =====================
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ===================== SESSIONS =====================
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};