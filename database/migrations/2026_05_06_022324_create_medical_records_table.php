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

            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained()
                ->onDelete('set null');

            // Medical info
            $table->text('chief_complaint')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment')->nullable();
            $table->text('notes')->nullable();

            // Vital signs
            $table->string('blood_pressure')->nullable();
            $table->string('temperature')->nullable();
            $table->string('weight')->nullable();
            $table->string('height')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};