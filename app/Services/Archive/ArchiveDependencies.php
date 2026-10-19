<?php

namespace App\Services\Archive;

use Illuminate\Support\Facades\DB;

/**
 * Which other records still point at an archived record.
 *
 * Permanent deletion is allowed ONLY when nothing in `refs` points at the
 * record. Most foreign keys in this project are "ON DELETE SET NULL" or
 * "CASCADE", which means the database would NOT stop a delete — it would
 * quietly erase or detach medical, appointment and billing history. So the
 * rule is enforced here, explicitly.
 *
 * `owned` tables are plain child data that belongs to the record itself
 * (read-receipts, saved schedules, replies of a message) and go away with it.
 *
 * Each ref is [table, column, label, isAudit]. `isAudit` refs are the audit
 * trail (user logs, backup logs): they block a PERMANENT delete, but are not
 * part of the model-level guard that protects rejected registrations.
 *
 * tests/Feature/AdminArchiveTest has a drift test: if a new foreign key is
 * ever added that points at one of these tables, the test fails until the
 * new table is listed here.
 */
final class ArchiveDependencies
{
    public const MAP = [
        'user' => [
            'table' => 'users',
            'owned' => [
                'admin_notification_reads', 'doctor_notification_reads', 'patient_notification_reads',
                'staff_notification_reads', 'doctor_schedule_exceptions', 'doctor_weekly_schedules',
                'sessions', 'user_permissions',
                // Only records WHO archived something; the name is copied, so nothing is lost.
                'archive_entries',
            ],
            'refs' => [
                ['appointments', 'patient_id', 'appointments', false],
                ['appointments', 'doctor_id', 'appointments', false],
                ['appointments', 'rescheduled_by', 'appointments', false],
                ['medical_records', 'patient_id', 'medical records', false],
                ['medical_records', 'doctor_id', 'medical records', false],
                ['medical_records', 'vitals_recorded_by', 'medical records', false],
                ['prescriptions', 'dispensed_by', 'prescriptions', false],
                ['medical_certificates', 'patient_id', 'medical certificates', false],
                ['medical_certificates', 'doctor_id', 'medical certificates', false],
                ['medical_certificates', 'released_by', 'medical certificates', false],
                ['medical_certificate_corrections', 'patient_id', 'medical certificate corrections', false],
                ['laboratory_requests', 'patient_id', 'laboratory requests', false],
                ['laboratory_requests', 'doctor_id', 'laboratory requests', false],
                ['laboratory_requests', 'reviewed_by', 'laboratory requests', false],
                ['laboratory_results', 'uploaded_by', 'laboratory results', false],
                ['laboratory_result_corrections', 'requested_by', 'laboratory result corrections', false],
                ['invoices', 'patient_id', 'invoices', false],
                ['invoices', 'created_by', 'invoices', false],
                ['invoices', 'voided_by', 'invoices', false],
                ['invoices', 'discount_id_verified_by', 'invoices', false],
                ['invoice_items', 'waived_by', 'invoice items', false],
                ['payments', 'received_by', 'payments', false],
                ['medicine_stock_movements', 'user_id', 'stock movements', false],
                ['contact_message_replies', 'user_id', 'contact message replies', false],
                ['patients', 'user_id', 'patient profile records', false],
                ['user_logs', 'user_id', 'user activity logs', true],
                ['backup_logs', 'created_by', 'backup logs', true],
            ],
        ],

        'walkin_patient' => [
            'table' => 'patients',
            'owned' => [],
            'refs' => [
                ['appointments', 'walkin_patient_id', 'appointments', false],
                ['medical_records', 'walkin_patient_id', 'medical records', false],
                ['laboratory_requests', 'walkin_patient_id', 'laboratory requests', false],
                ['invoices', 'walkin_patient_id', 'invoices', false],
            ],
        ],

        'appointment' => [
            'table' => 'appointments',
            'owned' => ['appointment_status_events'],
            'refs' => [
                ['medical_records', 'appointment_id', 'medical records', false],
                ['prescriptions', 'appointment_id', 'prescriptions', false],
                ['medical_certificates', 'appointment_id', 'medical certificates', false],
                ['laboratory_requests', 'appointment_id', 'laboratory requests', false],
                ['invoices', 'appointment_id', 'invoices / billing', false],
                ['reviews', 'appointment_id', 'reviews', false],
                ['reviews', 'follow_up_appointment_id', 'reviews', false],
            ],
        ],

        'service' => [
            'table' => 'services',
            'owned' => [],
            'refs' => [
                ['invoice_items', 'service_id', 'invoice items (billing history)', false],
            ],
        ],

        'medicine' => [
            'table' => 'medicines',
            'owned' => [],
            'refs' => [
                ['medicine_batches', 'medicine_id', 'stock batches', false],
                ['medicine_stock_movements', 'medicine_id', 'stock movement history', false],
                ['prescriptions', 'medicine_id', 'prescriptions', false],
                ['invoice_items', 'medicine_id', 'invoice items (billing history)', false],
            ],
        ],

        'contact_message' => [
            'table' => 'contact_messages',
            'owned' => ['contact_message_replies'],
            'refs'  => [],
        ],

        'landing_item' => [
            'table' => 'landing_items',
            'owned' => [],
            'refs'  => [],
        ],
    ];

    /**
     * Readable names of everything that still references the record
     * (empty array = nothing blocks a permanent delete).
     *
     * @return array<int, string>
     */
    public static function blockers(string $type, int $id, bool $includeAudit = true): array
    {
        $found = [];

        foreach (self::MAP[$type]['refs'] ?? [] as [$table, $column, $label, $isAudit]) {
            if ($isAudit && ! $includeAudit) {
                continue;
            }

            if (in_array($label, $found, true)) {
                continue;
            }

            if (DB::table($table)->where($column, $id)->exists()) {
                $found[] = $label;
            }
        }

        return $found;
    }
}
