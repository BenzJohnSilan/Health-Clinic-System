<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action');

            // Feature area this log belongs to (Appointments, Patients,
            // Billing & Payments, Account, etc.) — used to drive the Module
            // filter on the Staff/Patient Activity Logs pages. Nullable so
            // any log that doesn't set it (older/legacy call sites) still
            // works fine.
            $table->string('module', 50)->nullable();

            $table->text('details')->nullable();
            $table->timestamps();

            // optional foreign key
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Indexes for the query patterns the Activity Logs pages use:
            // "my logs, newest first" and "my logs filtered by module".
            $table->index(['user_id', 'created_at']);
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};