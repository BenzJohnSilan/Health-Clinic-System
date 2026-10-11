<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();

            // Archive name, e.g. clinic_backup_2026-09-30_14-05-09.zip.
            // Kept even when a run fails so the attempt can be identified.
            // On a "restore" row this is the backup that was restored.
            $table->string('filename');

            // manual | automatic | pre-restore | restore  (see BackupLog::TYPE_* constants)
            $table->string('type', 20)->default('manual');

            // Archive size in bytes. Null until the backup succeeds.
            $table->unsignedBigInteger('size')->nullable();

            // running | success | failed  (see BackupLog::STATUS_* constants)
            $table->string('status', 20)->default('running');

            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();

            // Sanitized failure reason (never contains credentials).
            $table->text('error_message')->nullable();

            // Short note that links related rows, e.g. on a restore record
            // "Safety backup: clinic_backup_....zip". Plain text, never secrets.
            $table->string('details', 500)->nullable();

            // Admin who triggered a manual backup or restore; null for CLI/automatic
            // runs or if that account is later deleted.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};