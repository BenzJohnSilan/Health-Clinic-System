<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact Us inquiries submitted from the public landing page.
 *
 * Safe to run on any database:
 *  - Fresh install: creates the table.
 *  - If an earlier implementation already created `contact_messages`, the
 *    table is KEPT (no inquiry is dropped); only the missing columns are
 *    added, and a legacy `subject` column is made nullable so the app no
 *    longer needs it while the old data stays readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table) {
                $table->id();
                $table->string('first_name', 60);
                $table->string('last_name', 60);
                $table->string('email', 150);
                $table->string('contact_number', 30);
                $table->text('message');
                $table->string('status', 10)->default('unread'); // unread | read
                $table->timestamp('read_at')->nullable();
                $table->timestamp('notified_at')->nullable();     // clinic email notification delivered
                $table->string('notification_error', 255)->nullable(); // generic reason, never server details
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });

            return;
        }

        Schema::table('contact_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('contact_messages', 'first_name')) {
                $table->string('first_name', 60)->nullable();
            }
            if (! Schema::hasColumn('contact_messages', 'last_name')) {
                $table->string('last_name', 60)->nullable();
            }
            if (! Schema::hasColumn('contact_messages', 'email')) {
                $table->string('email', 150)->nullable();
            }
            if (! Schema::hasColumn('contact_messages', 'contact_number')) {
                $table->string('contact_number', 30)->nullable();
            }
            if (! Schema::hasColumn('contact_messages', 'message')) {
                $table->text('message')->nullable();
            }
            if (! Schema::hasColumn('contact_messages', 'status')) {
                $table->string('status', 10)->default('unread');
            }
            if (! Schema::hasColumn('contact_messages', 'read_at')) {
                $table->timestamp('read_at')->nullable();
            }
            if (! Schema::hasColumn('contact_messages', 'notified_at')) {
                $table->timestamp('notified_at')->nullable();
            }
            if (! Schema::hasColumn('contact_messages', 'notification_error')) {
                $table->string('notification_error', 255)->nullable();
            }
        });

        // Legacy Subject column: keep the data, just stop requiring it.
        if (Schema::hasColumn('contact_messages', 'subject')) {
            Schema::table('contact_messages', function (Blueprint $table) {
                $table->string('subject')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
