<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-instance landing page content (hero text, contact details, hours,
 * footer, ...) as key/value rows. Nothing is seeded: a key that has no row
 * falls back to App\Support\LandingDefaults, so the public page works before
 * and after this table is filled in. Purely additive — no existing table is
 * touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('website_settings')) {
            return;
        }

        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
