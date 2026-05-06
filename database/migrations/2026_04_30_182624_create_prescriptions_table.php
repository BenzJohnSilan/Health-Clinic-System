<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();

            // Appointment relation
            $table->foreignId('appointment_id')
                ->constrained()
                ->onDelete('cascade');

            // Medicine relation (nullable for manual prescription)
            $table->foreignId('medicine_id')
                ->nullable()
                ->constrained()
                ->onDelete('set null');

            // Manual medicine name (for "Other" option)
            $table->string('manual_medicine_name')->nullable();

            $table->string('dosage');
            $table->string('frequency');
            $table->string('duration');
            $table->integer('quantity_prescribed');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};