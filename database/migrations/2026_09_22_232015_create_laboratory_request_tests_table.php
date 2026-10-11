<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per test the Doctor checked on a Laboratory Request
     * (CBC, Urinalysis, ...). Normalized instead of a comma-separated
     * column so the Result Review page can cleanly group uploaded
     * result files per test.
     */
    public function up(): void
    {
        Schema::create('laboratory_request_tests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('laboratory_request_id')
                ->constrained('laboratory_requests')
                ->onDelete('cascade');

            // One of LaboratoryRequest::TEST_CATALOG (includes "Others").
            $table->string('test_name', 100);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_request_tests');
    }
};
