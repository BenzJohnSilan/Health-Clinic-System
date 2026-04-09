<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->onDelete('cascade'); // patients
            $table->foreignId('doctor_id')->constrained('users')->onDelete('cascade');  // doctors
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->string('status')->default('Pending'); // Pending, Approved, Rejected
            $table->text('reason')->nullable(); // changed from notes to reason
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('appointments');
    }
};
