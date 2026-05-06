<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();

            $table->string('medicine_name');
            $table->string('brand')->nullable(); // ✅ ADD
            $table->string('category');
            $table->string('dosage')->nullable(); // ✅ ADD

            $table->integer('quantity');
            $table->string('unit');

            $table->decimal('price', 10, 2)->default(0); // ✅ ADD

            $table->date('expiration_date');

            $table->enum('status', ['Available', 'Low Stock', 'Out of Stock'])
                  ->default('Available');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};