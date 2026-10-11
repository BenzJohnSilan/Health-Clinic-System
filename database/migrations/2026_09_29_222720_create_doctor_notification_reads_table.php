<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_notification_reads', function (Blueprint $table) {
            $table->id();

            // The Doctor (users table) who read the notification.
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Stable, unique identifier for the specific notification
            // event (e.g. "doctor_appointment_15_Approved_42" or
            // "doctor_lab_7_1758585600_31"). Every distinct appointment
            // status event / laboratory result arrival gets its own key,
            // so an old read record never marks a NEW event as read.
            //
            // IMPORTANT: this table is completely separate from
            // `admin_notification_reads`, `staff_notification_reads` and
            // `patient_notification_reads` — read states are never shared
            // between roles.
            $table->string('notification_key');

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // A Doctor can only have one read-record per notification key.
            $table->unique(['user_id', 'notification_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_notification_reads');
    }
};
