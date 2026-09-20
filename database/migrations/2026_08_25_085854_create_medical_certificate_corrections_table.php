<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_certificate_corrections', function (Blueprint $table) {
            $table->id();

            // The issued Medical Certificate this correction request belongs to.
            $table->foreignId('medical_certificate_id')
                ->constrained('medical_certificates')
                ->onDelete('cascade');

            // Patient who filed the correction request.
            $table->foreignId('patient_id')
                ->constrained('users')
                ->onDelete('cascade');

            // What the patient says is incorrect.
            $table->text('reason');

            // pending -> resolved (doctor edited & corrected) or rejected (doctor declined)
            $table->enum('status', ['pending', 'resolved', 'rejected'])
                ->default('pending');

            $table->text('rejection_reason')->nullable();

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_certificate_corrections');
    }
};
