<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing & Payments — Invoices.
     *
     * An invoice is created when a Doctor finishes a consultation and
     * tags the applicable service/charge for an appointment. It always
     * starts UNPAID; only a verified Staff-recorded Payment moves the
     * status toward Partially Paid / Paid.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_no')->unique();

            // The consultation this invoice belongs to. Nullable ONLY for a
            // Medical Certificate that has to be billed on its own invoice
            // (its consultation invoice is already paid/finalized). Such an
            // invoice is linked through medical_certificates.invoice_id, so
            // Appointment::invoice() (hasOne) never returns it.
            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');

            // Registered patient (users table) — nullable for walk-ins.
            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Walk-in patient (patients table) — nullable for registered patients.
            $table->foreignId('walkin_patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);

            $table->enum('status', ['Unpaid', 'Partially Paid', 'Paid', 'Cancelled'])
                ->default('Unpaid');

            // Who prepared/tagged the invoice (the attending Doctor).
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

        // medical_certificates is created before invoices, so its
        // invoice_id foreign key can only be attached now.
        Schema::table('medical_certificates', function (Blueprint $table) {
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('medical_certificates', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });

        Schema::dropIfExists('invoices');
    }
};
