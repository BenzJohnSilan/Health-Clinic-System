<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notification_reads', function (Blueprint $table) {
            $table->id();

            // The staff member (users table) who read the notification.
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Stable, unique identifier for the specific notification
            // event (e.g. "staff_appointment_15_Approved_42"). Every
            // distinct appointment status event gets its own key, so an
            // old read record never accidentally marks a NEW event as
            // read.
            //
            // IMPORTANT: this table is completely separate from
            // `patient_notification_reads` — Staff and Patient read
            // states must never share rows or keys, even though both
            // ultimately derive from `appointment_status_events`.
            $table->string('notification_key');

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // A staff member can only have one read-record per notification key.
            $table->unique(['user_id', 'notification_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notification_reads');
    }
};
