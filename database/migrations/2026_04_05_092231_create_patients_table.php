<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();

            // Personal Info
            $table->date('birthdate');
            $table->integer('age');
            $table->string('gender');
            $table->string('civil_status');

            // Contact
            $table->string('contact_number')->nullable();
            $table->text('address')->nullable();

            // Walk-in or Registered
            $table->boolean('is_walk_in')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};