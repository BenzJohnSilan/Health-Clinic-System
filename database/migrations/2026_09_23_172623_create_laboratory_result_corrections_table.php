<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correction Required workflow (doctor-initiated, mirrors the
     * existing medical_certificate_corrections pattern — see
     * MedicalCertificateCorrection):
     *
     *   Result Received --[doctor requests correction]--> Correction Required
     *   Correction Required --[patient/staff uploads]--> Result Received
     *
     * One row per correction REQUEST (not per file), so requesting
     * correction more than once on the same laboratory_request keeps
     * every past reason instead of overwriting it — this is the audit
     * trail. The uploaded files themselves already keep their own
     * history for free via laboratory_results (one-to-many, nothing is
     * ever deleted here), so this table only needs to record the
     * request/resolution side of each correction cycle.
     */
    public function up(): void
    {
        Schema::create('laboratory_result_corrections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('laboratory_request_id')
                ->constrained('laboratory_requests')
                ->onDelete('cascade');

            // Doctor who requested the correction. Nullable-on-delete
            // only — the correction record must survive account deletion.
            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Optional tag of which requested test this correction concerns
            // (free text matching one of the request's test names), same
            // convention as laboratory_results.test_name.
            $table->string('test_name', 100)->nullable();

            // Why the doctor is asking for a new upload (required at the
            // application level).
            $table->text('reason');

            // pending -> resolved (a new file was uploaded against this request)
            $table->enum('status', ['pending', 'resolved'])->default('pending');

            // The upload that resolved this correction request, once made.
            $table->foreignId('resolving_result_id')
                ->nullable()
                ->constrained('laboratory_results')
                ->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_result_corrections');
    }
};
