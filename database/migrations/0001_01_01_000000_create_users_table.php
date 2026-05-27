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
            $table->string('avatar', 100)->nullable();

            // ================= PERSONAL INFORMATION =================
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50);
            $table->string('suffix', 10)->nullable();
            $table->date('birthdate');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->enum('civil_status', ['Single', 'Married', 'Widowed', 'Separated']);
            $table->string('address', 100);

            // ================= CONTACT =================
            $table->string('contact_number', 20)->unique();

            // ================= VERIFICATION =================
            $table->string('id_type', 50)->nullable();
            $table->string('valid_id')->nullable();

            // ================= REASON =================
            $table->enum('reason', [
                'To Book Appointments Online',
                'To Access Clinic Services',
                'To Manage Personal Health Records',
                'For Easier Communication with the Clinic',
                'Others'
            ])->nullable();

            // ================= MEDICAL INFORMATION =================
            $table->string('blood_type', 10)->nullable();
            $table->text('allergies')->nullable();

            // ================= EMERGENCY CONTACT =================
            $table->string('emergency_name', 100)->nullable();
            $table->string('emergency_contact_number', 20)->nullable();
            $table->string('relationship', 50)->nullable();
            $table->string('emergency_address', 100)->nullable();

            // ================= DOCTOR INFORMATION =================
            $table->string('specialization', 50)->nullable();
            $table->string('license_number', 20)->nullable();

            // ================= STAFF INFORMATION =================
            $table->string('employee_id', 20)->nullable();
            $table->string('position', 50)->nullable();

            // ================= LOGIN =================
            $table->string('username', 50)->unique();
            $table->string('email')->unique();       // ✅ FIXED: inalis ang (50) limit
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');              // ✅ FIXED: inalis ang (50) limit, bcrypt needs 60+ chars

            // ================= ROLE =================
            $table->enum('role', ['Admin', 'Patient', 'Doctor', 'Staff'])
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