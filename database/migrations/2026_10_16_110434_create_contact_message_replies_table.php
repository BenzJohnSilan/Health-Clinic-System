<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replies an Admin sent to a Contact Us inquiry from the admin panel.
 * One row per email that was actually delivered to the mail server.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_message_replies')) {
            return;
        }

        Schema::create('contact_message_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_message_id')->constrained('contact_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 150);
            $table->text('body');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['contact_message_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_message_replies');
    }
};
