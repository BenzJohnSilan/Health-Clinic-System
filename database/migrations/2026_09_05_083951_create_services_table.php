<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clinic services / fee structure used by the Billing & Payments module.
     * Additive only — does not touch any existing table.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        // Seed a couple of sensible defaults so Billing works out of the
        // box (prices are still fully editable from Admin > Services).
        DB::table('services')->insert([
            [
                'name'        => 'Consultation',
                'description' => 'Standard doctor consultation fee.',
                'price'       => 500.00,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'name'        => 'Medical Certificate',
                'description' => 'Issuance of a medical certificate.',
                'price'       => 150.00,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
