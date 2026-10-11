<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per uploaded result file. A single Laboratory Request can
     * have multiple requested tests (CBC, Urinalysis, ...) and the
     * external laboratory may return a separate file per test, so this
     * is intentionally one-to-many against laboratory_requests rather
     * than a single file column on the request itself.
     */
    public function up(): void
    {
        Schema::create('laboratory_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('laboratory_request_id')
                ->constrained('laboratory_requests')
                ->onDelete('cascade');

            // Optional tag of which requested test this file belongs to
            // (free text matching one of the request's test names, or
            // null for a single combined result file). Nullable on
            // purpose — some external labs return one file for everything.
            $table->string('test_name', 100)->nullable();

            // ================= FILE =================
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('file_type', 10);   // pdf | jpg | jpeg | png
            $table->unsignedInteger('file_size'); // bytes

            // ================= UPLOAD META =================
            // Whoever uploaded it — the Patient themselves, or a Staff
            // member recording a physical result. Nullable-on-delete
            // only, so the file record survives account deletion.
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();

            $table->text('notes')->nullable();

            // Lets a Patient remove a wrong upload (Laboratory Result
            // Upload workflow, Section 3/4) without destroying audit
            // history: the row is kept (soft-deleted) so the Doctor's
            // Correction History / audit trail stays intact — only the
            // actual file is removed from storage by the controller.
            // Same soft-delete convention already used by Medicine (see
            // medicines.deleted_at) — no new mechanism invented.
            $table->softDeletes();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_results');
    }
};