<?php

use App\Support\LandingDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repeatable public landing page content (service cards, portal features,
 * "How It Works" steps, workflow steps, FAQs) in ONE table, told apart by
 * `section`. This is deliberately separate from the billing `services`
 * table: the public service cards are marketing copy and have no price or
 * link to invoices, so editing/hiding/deleting one can never affect billing.
 *
 * On first creation the current hardcoded content is copied in so the Admin
 * edits what visitors already see. It only runs when the table is created
 * and empty — re-running never overwrites Admin changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('landing_items')) {
            Schema::create('landing_items', function (Blueprint $table) {
                $table->id();
                $table->string('section', 30);
                $table->string('category', 30)->nullable();   // FAQ tab key
                $table->string('title', 255);                  // name / step / question
                $table->text('body')->nullable();              // description / answer
                $table->string('icon', 60)->nullable();        // boxicons class, e.g. bxs-heart
                $table->string('image_path')->nullable();      // public disk, landing/...
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['section', 'is_active', 'sort_order']);
            });
        }

        if (DB::table('landing_items')->count() === 0) {
            $now = now();
            $rows = array_map(fn ($r) => $r + [
                'image_path' => null,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], LandingDefaults::items());

            DB::table('landing_items')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_items');
    }
};
