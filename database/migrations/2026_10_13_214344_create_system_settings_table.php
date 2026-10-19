<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic-wide operational settings managed from Admin > System Settings
 * (general info, appointment rules, notification toggles, session timeout)
 * as key/value rows.
 *
 * This is deliberately separate from `website_settings`, which only holds
 * public landing page content. Nothing is seeded: a key with no row falls
 * back to App\Services\SystemSettings::DEFAULTS, which reproduce the
 * behavior the system had before this module existed. Purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_settings')) {
            return;
        }

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
