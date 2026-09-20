<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_status_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->onDelete('cascade');

            // Snapshot of the status at the moment this event was recorded.
            $table->string('status', 30);

            $table->timestamp('occurred_at');

            $table->timestamps();

            // Fast lookup per appointment, most-recent first.
            $table->index(['appointment_id', 'occurred_at']);
        });

        // Backfill: give every EXISTING appointment one initial status
        // snapshot (its current status, timestamped at its own updated_at)
        // so appointments created before this feature existed still show up
        // in the Patient notification feed instead of silently having no
        // history at all. Going forward, Appointment::boot() records every
        // status change automatically — this backfill only ever runs once,
        // here, for pre-existing rows.
        $now = now();
        \DB::table('appointments')
            ->select('id', 'status', 'updated_at')
            ->orderBy('id')
            ->chunk(500, function ($appointments) use ($now) {
                $rows = $appointments->map(fn ($a) => [
                    'appointment_id' => $a->id,
                    'status'         => $a->status,
                    'occurred_at'    => $a->updated_at ?? $now,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ])->all();

                if (!empty($rows)) {
                    \DB::table('appointment_status_events')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_events');
    }
};
