<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Medicine Inventory audit trail. Every Stock In, Stock Out,
     * Adjustment, and Dispense writes exactly one row here — never just
     * the final quantity — so any change can be traced back to WHO,
     * WHEN, WHAT ACTION, HOW MUCH, BEFORE, AFTER, and WHY. These rows
     * are treated as permanent audit records: nothing in the
     * application is allowed to update or delete them.
     */
    public function up(): void
    {
        Schema::create('medicine_stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('medicine_id')
                ->constrained('medicines')
                ->cascadeOnDelete();

            // Which specific batch/lot this movement affected. Nullable
            // so movements that predate batch tracking (or ones with no
            // clean batch match) don't break the audit trail.
            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('medicine_batches')
                ->nullOnDelete();

            // Who performed the movement. Kept even if the user
            // account is later removed, so the audit trail never
            // silently loses its "WHO" — the log falls back to
            // "user_id: null" rather than disappearing.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // 'dispense' is its own movement_type — distinct from
            // 'stock_out' — so the Stock History / Inventory Reports
            // audit trail can tell "Staff manually removed stock" apart
            // from "Staff released a Doctor's prescription to a
            // patient" at a glance, without reading into the `reason`
            // text.
            $table->enum('movement_type', ['stock_in', 'stock_out', 'adjustment', 'dispense']);

            // Signed change applied to quantity (e.g. +50, -20, -5).
            $table->integer('quantity_change');
            $table->integer('quantity_before');
            $table->integer('quantity_after');

            $table->string('reason');
            $table->string('reference_no')->nullable();
            $table->string('batch_no')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['medicine_id', 'created_at']);
            $table->index('movement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_stock_movements');
    }
};