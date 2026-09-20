<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_notification_reads', function (Blueprint $table) {
            $table->id();

            // The patient (users table) who read the notification.
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Stable, unique identifier for the specific notification
            // event (e.g. "appointment_15_approved_1735689600"). Every
            // distinct status change / event gets its own key, so an old
            // read record never accidentally marks a NEW event as read.
            $table->string('notification_key');

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // A patient can only have one read-record per notification key.
            $table->unique(['user_id', 'notification_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_notification_reads');
    }
};
