<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Billing audit — protect financial history.
 *
 * What this migration does (it never deletes an invoice, item or payment):
 *
 *  1. Foreign keys that used to CASCADE-DELETE money records are changed:
 *       payments.invoice_id    cascade  -> restrict   (an invoice with payments can't be deleted)
 *       payments.received_by   cascade  -> set null   (deleting a staff account keeps the payment)
 *       invoices.appointment_id cascade -> set null   (deleting an appointment keeps the invoice)
 *  2. Snapshot columns so a report/receipt still says WHO and WHICH after the
 *     related user/appointment is gone:
 *       invoices.patient_name, invoices.appointment_ref, payments.received_by_name
 *     (existing rows are back-filled from the data that exists today).
 *  3. Void audit trail on invoices: void_reason, voided_by, voided_at.
 *  4. Senior Citizen / PWD proof on invoices: discount_id_number,
 *     discount_id_verified_by, discount_id_verified_at.
 *  5. payments.reference_key  — normalized GCash reference (lower-case, trimmed),
 *     UNIQUE, so two simultaneous requests can never both save the same reference.
 *     payments.submission_token — one-time form token, UNIQUE, so a double click /
 *     refresh can never record the same payment twice.
 *  6. UNIQUE invoices.appointment_id — one invoice per appointment.
 *
 * Existing duplicates are NOT deleted: for a duplicated GCash reference only the
 * first payment gets the unique key (later ones are left empty and logged), and
 * the appointment unique index is skipped (and logged) if duplicates already exist.
 * Back up the database before running this (see the README steps in the audit report).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- 2/3/4/5: new columns (all nullable => backward compatible) ----------
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('patient_name', 150)->nullable()->after('walkin_patient_id');
            $table->string('appointment_ref', 50)->nullable()->after('appointment_id');

            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->dateTime('voided_at')->nullable();

            $table->string('discount_id_number', 60)->nullable();
            $table->unsignedBigInteger('discount_id_verified_by')->nullable();
            $table->dateTime('discount_id_verified_at')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('discount_id_verified_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('received_by_name', 150)->nullable();
            $table->string('reference_key', 191)->nullable();
            $table->string('submission_token', 64)->nullable();
        });

        // ---------- back-fill snapshots from today's data ----------
        DB::table('invoices')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $inv) {
                $name = null;
                if ($inv->patient_id) {
                    $u = DB::table('users')->where('id', $inv->patient_id)->first(['first_name', 'last_name']);
                    $name = $u ? trim($u->first_name . ' ' . $u->last_name) : null;
                } elseif ($inv->walkin_patient_id) {
                    $p = DB::table('patients')->where('id', $inv->walkin_patient_id)->first(['first_name', 'last_name']);
                    $name = $p ? trim($p->first_name . ' ' . $p->last_name) : null;
                }

                $ref = $inv->appointment_id
                    ? DB::table('appointments')->where('id', $inv->appointment_id)->value('reference_no')
                    : null;

                DB::table('invoices')->where('id', $inv->id)->update([
                    'patient_name'    => $name,
                    'appointment_ref' => $ref,
                ]);
            }
        });

        DB::table('payments')->orderBy('id')->chunkById(200, function ($rows) {
            $seenKeys = [];
            foreach ($rows as $pay) {
                $u = $pay->received_by
                    ? DB::table('users')->where('id', $pay->received_by)->first(['first_name', 'last_name'])
                    : null;

                $update = ['received_by_name' => $u ? trim($u->first_name . ' ' . $u->last_name) : null];

                if ($pay->payment_method === 'GCash' && trim((string) $pay->reference_number) !== '') {
                    $key = mb_strtolower(trim($pay->reference_number));

                    $taken = isset($seenKeys[$key]) || DB::table('payments')
                        ->where('reference_key', $key)->exists();

                    if ($taken) {
                        Log::warning("Billing migration: payment #{$pay->id} repeats GCash reference '{$key}'. "
                            . 'Record kept; no unique key assigned to it. Please review manually.');
                    } else {
                        $update['reference_key'] = $key;
                        $seenKeys[$key] = true;
                    }
                }

                DB::table('payments')->where('id', $pay->id)->update($update);
            }
        });

        // ---------- 5: unique keys on payments ----------
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('reference_key', 'payments_reference_key_unique');
            $table->unique('submission_token', 'payments_submission_token_unique');
        });

        // ---------- 6: one invoice per appointment (only if data allows it) ----------
        $dupes = DB::table('invoices')
            ->whereNotNull('appointment_id')
            ->groupBy('appointment_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('appointment_id');

        if ($dupes->isEmpty()) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unique('appointment_id', 'invoices_appointment_id_unique');
            });
        } else {
            Log::warning('Billing migration: unique index on invoices.appointment_id was SKIPPED because these '
                . 'appointments already have more than one invoice: ' . $dupes->implode(', ')
                . '. Review them, then add the index manually.');
        }

        // ---------- 1: stop cascade deletes of money records ----------
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['received_by']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('received_by')->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Restores the old foreign-key rules and drops the added columns.
        // (Rolling back re-introduces cascade deletes — only do it on purpose.)
        // Order matters on MySQL: the foreign key may be using the unique index
        // as its supporting index, so drop the key, then the index, then re-add the key.
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
        });

        if (Schema::hasIndex('invoices', 'invoices_appointment_id_unique')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropUnique('invoices_appointment_id_unique');
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('appointment_id')->references('id')->on('appointments')->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['received_by']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_reference_key_unique');
            $table->dropUnique('payments_submission_token_unique');
            $table->dropColumn(['received_by_name', 'reference_key', 'submission_token']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['discount_id_verified_by']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'patient_name', 'appointment_ref',
                'void_reason', 'voided_by', 'voided_at',
                'discount_id_number', 'discount_id_verified_by', 'discount_id_verified_at',
            ]);
        });
    }
};
