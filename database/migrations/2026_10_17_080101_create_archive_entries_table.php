<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central Archive register (Admin > Archive).
 *
 * One row = "this record is archived". It stores ONLY metadata — who
 * archived it, when, a readable label, and (for a few modules) the values
 * needed to put the record back exactly as it was. The archived record itself
 * is never moved or copied: its row, its ID and every relationship stay where
 * they are, so medical / appointment / billing history is untouched.
 *
 * Additive only: creates one new table. It does not alter, drop or truncate
 * any existing table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('archive_entries')) {
            return;
        }

        Schema::create('archive_entries', function (Blueprint $table) {
            $table->id();

            // Module key (user, walkin_patient, appointment, service,
            // contact_message, landing_item, medicine) + the record's ID.
            $table->string('archivable_type', 40);
            $table->unsignedBigInteger('archivable_id');

            // Snapshot shown on the Archive page (name / reference number).
            $table->string('label', 255);
            $table->string('sub_label', 255)->nullable();

            // Values needed to restore (e.g. the account's previous status).
            $table->json('state')->nullable();

            // Who archived it. The name is copied so it is still readable if
            // that account is removed later.
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archived_by_name', 150)->nullable();
            $table->timestamp('archived_at')->useCurrent();

            $table->timestamps();

            // A record can be archived only once.
            $table->unique(['archivable_type', 'archivable_id'], 'archive_entries_record_unique');
            $table->index('archived_at');
        });

        // Medicines already use SoftDeletes (their "Delete" is a soft delete).
        // Register the ones that are already soft-deleted so they show up on the
        // Archive page. Insert-only: nothing existing is changed or removed.
        if (Schema::hasTable('medicines') && Schema::hasColumn('medicines', 'deleted_at')) {
            $now = now();

            DB::table('medicines')
                ->whereNotNull('deleted_at')
                ->orderBy('id')
                ->each(function ($m) use ($now) {
                    DB::table('archive_entries')->insertOrIgnore([
                        'archivable_type' => 'medicine',
                        'archivable_id'   => $m->id,
                        'label'           => (string) $m->medicine_name,
                        'sub_label'       => trim(($m->brand ?? '') . ' ' . ($m->dosage ?? '')) ?: null,
                        'archived_at'     => $m->deleted_at,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ]);
                });
        }
    }

    public function down(): void
    {
        // Safe: this table holds only metadata. Dropping it un-hides archived
        // contact messages / website items / appointments / services (their
        // rows were never touched) and does not delete any record.
        // NOTE: archived USER accounts stay 'Inactive' — reactivate them from
        // Manage Users after a rollback.
        Schema::dropIfExists('archive_entries');
    }
};
