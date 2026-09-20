<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments are recorded physically at the clinic counter by Staff.
     * Cash needs no reference number; GCash requires one (the clinic's
     * own reference after Staff verifies the transfer in their GCash
     * account — there is no online/API integration).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained()
                ->onDelete('cascade');

            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['Cash', 'GCash']);
            $table->string('reference_number')->nullable();

            $table->foreignId('received_by')
                ->constrained('users')
                ->onDelete('cascade');

            $table->dateTime('paid_at');
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
