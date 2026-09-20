<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batch-based stock tracking for the Medicine Inventory module.
     *
     * A Medicine is the product record; a MedicineBatch is one
     * physical lot of that product with its own batch/lot number,
     * quantity, and expiration date.
     *
     * medicines.quantity and medicines.expiration_date stay on the
     * medicines table as denormalized cache columns — kept in sync
     * with this table by Medicine::recalculateFromBatches() — so
     * every existing part of the app that already reads
     * $medicine->quantity or $medicine->expiration_date (the Admin
     * Dashboard's expiring-medicines widget, doctor prescriptions,
     * inventory reports) keeps working unchanged.
     */
    public function up(): void
    {
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('medicine_id')
                ->constrained('medicines')
                ->cascadeOnDelete();

            $table->string('batch_no');
            $table->unsignedInteger('quantity')->default(0);
            $table->date('expiration_date');

            $table->timestamps();

            $table->index(['medicine_id', 'expiration_date']);

            // A given lot number should only exist once per product —
            // Stock In tops up the existing batch instead of creating
            // a second row for the same batch_no.
            $table->unique(['medicine_id', 'batch_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_batches');
    }
};
