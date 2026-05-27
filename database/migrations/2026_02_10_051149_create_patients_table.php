<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            // Link to users table (null if walk-in)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');

            // Personal Information
            $table->string('first_name', 50)->nullable();
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('suffix', 10)->nullable();
            $table->date('birthdate')->nullable();
            // NO age column — computed via Patient model accessor

            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('civil_status', 20)->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->text('address')->nullable();

            // Medical Information
            $table->enum('blood_type', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
            $table->text('allergies')->nullable();

            // Emergency Contact
            $table->string('emergency_name', 100)->nullable();
            $table->string('emergency_contact', 20)->nullable();
            $table->string('relationship', 50)->nullable();
            $table->text('emergency_address')->nullable();

            // Walk-in = true, Registered = false
            $table->boolean('is_walk_in')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};