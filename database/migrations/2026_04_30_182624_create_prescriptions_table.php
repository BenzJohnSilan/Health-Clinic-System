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

            // Free-text patient instructions (e.g. "Take after meals"),
            // separate from dosage/frequency/duration.
            $table->string('instructions')->nullable();

            $table->integer('quantity_prescribed');

            // Splits "Doctor writes a prescription" from "Staff releases
            // the medicine" so inventory is only deducted at the
            // dispensing step, never at prescription creation.
            // quantity_prescribed keeps its original meaning (amount
            // written on the prescription); dispensed_quantity is the
            // amount Staff actually released, which may be equal to or
            // less than quantity_prescribed.
            $table->enum('dispense_status', ['Pending', 'Dispensed'])->default('Pending');
            $table->unsignedInteger('dispensed_quantity')->nullable();

            $table->foreignId('dispensed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('dispensed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};