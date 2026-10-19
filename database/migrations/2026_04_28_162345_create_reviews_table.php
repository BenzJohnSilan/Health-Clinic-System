<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('appointment_id');

            // The Follow-up appointment created from this consultation's
            // Follow-up Schedule — saving again updates the SAME appointment.
            $table->unsignedBigInteger('follow_up_appointment_id')->nullable();

            $table->date('next_review_date')->nullable();
            $table->text('message')->nullable();

            $table->timestamps();

            $table->foreign('appointment_id')
                ->references('id')
                ->on('appointments')
                ->onDelete('cascade');

            $table->foreign('follow_up_appointment_id')
                ->references('id')
                ->on('appointments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};