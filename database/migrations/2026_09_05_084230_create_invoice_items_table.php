<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('description');
            $table->enum('item_type', ['service', 'medicine', 'medical_certificate'])->default('service');

            $table->foreignId('service_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();

            $table->foreignId('medicine_id')
                ->nullable()
                ->constrained('medicines')
                ->nullOnDelete();

            // Links a MEDICINE line to the clinic prescription that Staff
            // dispensed (the quantity actually released is what is billed).
            // UNIQUE, so one prescription can be billed at most once no matter
            // how many times dispensing is attempted. NULL for every other
            // kind of line (services, certificates).
            $table->foreignId('prescription_id')
                ->nullable()
                ->unique()
                ->constrained('prescriptions')
                ->nullOnDelete();

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);

            // GROSS line amount (quantity x unit_price), before any discount.
            $table->decimal('amount', 10, 2)->default(0);

            // Per-item discount, applied by Staff BEFORE payment.
            // invoices.discount = SUM(invoice_items.discount_amount).
            $table->string('discount_type')->nullable();
            $table->decimal('discount_rate', 5, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);

            // Consultation fee waiver audit trail. The waiver itself is a 100%
            // line discount on the Consultation line (discount_type =
            // 'consultation_waiver'), so the original fee is never overwritten:
            //   amount (original fee) - discount_amount (waived) = net.
            // These columns record WHY, WHO and WHEN.
            $table->string('waiver_reason', 40)->nullable();
            $table->text('waiver_notes')->nullable();
            $table->foreignId('waived_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('waived_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};