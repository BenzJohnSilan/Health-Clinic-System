<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notification_reads', function (Blueprint $table) {
            $table->id();

            // The admin (users table) who read the notification.
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Stable, unique identifier for the specific notification
            // event (e.g. "admin_account_12" or
            // "admin_appointment_15_Pending_42"). Every distinct pending
            // account / pending appointment event gets its own key, so an
            // old read record never accidentally marks a NEW event as
            // read.
            //
            // IMPORTANT: this table is completely separate from
            // `staff_notification_reads` and `patient_notification_reads`
            // — Admin, Staff, and Patient read states must never share
            // rows or keys, even though Admin's appointment source
            // ultimately derives from the same `appointment_status_events`
            // table Staff already uses.
            $table->string('notification_key');

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // An admin can only have one read-record per notification key.
            $table->unique(['user_id', 'notification_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_reads');
    }
};
