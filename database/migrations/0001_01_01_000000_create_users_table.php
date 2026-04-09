<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
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
            $table->string('contact_number')->unique(); // +639XXXXXXXXX

            // ================= LOGIN CREDENTIALS =================
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // ================= ROLE =================
            $table->enum('role', ['Admin', 'Patient', 'Doctor'])
                  ->default('Patient');

            /*
            =========================================================
            ACCOUNT CONTROL SYSTEM
            Active   = pwede mag login
            Inactive = disabled ng admin
            =========================================================
            */
            $table->enum('status', ['Active', 'Inactive'])
                  ->default('Active');

            /*
            =========================================================
            APPROVAL SYSTEM
            Pending  = waiting for admin approval
            Approved = approved by admin
            Rejected = denied by admin
            =========================================================
            */
            $table->enum('approval_status', ['Pending', 'Approved', 'Rejected'])
                  ->default('Pending');

            $table->rememberToken();
            $table->timestamps();
        });

        // ===================== PASSWORD RESET TOKENS =====================
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ===================== SESSIONS TABLE =====================
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};