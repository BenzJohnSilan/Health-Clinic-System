<?php

namespace App\Services\Archive;

use App\Models\Appointment;
use App\Models\ArchiveEntry;
use App\Models\ContactMessage;
use App\Models\LandingItem;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\UserLog;
use App\Services\LandingPageContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Admin Archive: archive, restore and (guarded) permanent delete.
 *
 * Archiving NEVER removes a database row. It writes one row to
 * archive_entries (who / when / label / values needed to restore) and, for
 * the few modules that need it, flips an existing status column:
 *
 *   user            -> users.status = 'Inactive' (account can no longer log in)
 *   service         -> services.is_active = false
 *   medicine        -> existing SoftDeletes (unchanged behaviour)
 *   everything else -> hidden from its normal list by the archive entry alone
 *
 * Permanent delete is refused while anything in ArchiveDependencies still
 * points at the record.
 *
 * Protected (never archivable / deletable here, and not offered): medical
 * records, prescriptions, medical certificates, laboratory results, invoices,
 * payments, user activity logs, permissions, system settings, backups.
 */
class ArchiveService
{
    /** Module key => label shown on the Archive page. */
    public const TYPES = [
        'user'            => 'User Account',
        'walkin_patient'  => 'Walk-in Patient',
        'appointment'     => 'Appointment',
        'service'         => 'Service',
        'contact_message' => 'Contact Message',
        'landing_item'    => 'Website Content',
        'medicine'        => 'Medicine',
    ];

    /** Appointment statuses that are still "live" (someone is expected). */
    public const ACTIVE_APPOINTMENT_STATUSES = [
        'Pending', 'Approved', 'Rescheduled', 'Waiting', 'Checked In', 'In Progress',
    ];

    /** Only finished appointments may be archived. */
    public const ARCHIVABLE_APPOINTMENT_STATUSES = [
        'Completed', 'Cancelled', 'Rejected', 'No Show',
    ];

    // =====================================================================
    //  ARCHIVE
    // =====================================================================

    public function archive(string $type, int $id, User $admin): ArchiveEntry
    {
        $this->assertAdmin($admin);
        $this->assertType($type);

        try {
            $entry = DB::transaction(function () use ($type, $id, $admin) {
                $record = $this->find($type, $id, true);

                if (! $record) {
                    throw new ArchiveException('That record could not be found.');
                }

                if ($this->entryFor($type, $id) || ($type === 'medicine' && $record->trashed())) {
                    throw new ArchiveException('This record is already archived.');
                }

                if ($reason = $this->archiveBlocker($type, $record, $admin)) {
                    throw new ArchiveException($reason);
                }

                [$label, $sub] = $this->describe($type, $record);

                // Medicine: the existing soft delete. The Medicine model event
                // registers the archive entry; here we only attribute it.
                if ($type === 'medicine') {
                    $record->delete();

                    $entry = $this->entryFor($type, $id) ?? new ArchiveEntry([
                        'archivable_type' => $type,
                        'archivable_id'   => $id,
                    ]);
                    $entry->fill([
                        'label'            => $label,
                        'sub_label'        => $sub,
                        'archived_by'      => $admin->id,
                        'archived_by_name' => $this->nameOf($admin),
                        'archived_at'      => now(),
                    ])->save();

                    return $entry;
                }

                $state = $this->applyArchive($type, $record);

                return ArchiveEntry::create([
                    'archivable_type'  => $type,
                    'archivable_id'    => $id,
                    'label'            => $label,
                    'sub_label'        => $sub,
                    'state'            => $state ?: null,
                    'archived_by'      => $admin->id,
                    'archived_by_name' => $this->nameOf($admin),
                    'archived_at'      => now(),
                ]);
            });
        } catch (QueryException $e) {
            // Two requests raced past the exists() check; the UNIQUE index won.
            if ($this->entryFor($type, $id)) {
                throw new ArchiveException('This record is already archived.');
            }

            throw $e;
        }

        if ($type === 'landing_item') {
            LandingPageContent::forget();
        }

        $this->log($admin, 'Archived Record', $type, $id, $entry->label);

        return $entry;
    }

    // =====================================================================
    //  RESTORE
    // =====================================================================

    public function restore(ArchiveEntry $entry, User $admin): void
    {
        $this->assertAdmin($admin);

        $type  = (string) $entry->archivable_type;
        $id    = (int) $entry->archivable_id;
        $label = (string) $entry->label;

        $this->assertType($type);

        $missing = false;

        DB::transaction(function () use ($entry, $type, $id, &$missing) {
            $locked = ArchiveEntry::whereKey($entry->id)->lockForUpdate()->first();

            if (! $locked) {
                throw new ArchiveException('This archive entry no longer exists. It may already have been restored.');
            }

            $record = $this->find($type, $id, true);

            // Original row gone (or a medicine that is no longer soft-deleted):
            // the entry points at nothing, so clear it instead of keeping a dead link.
            if (! $record || ($type === 'medicine' && ! $record->trashed())) {
                $locked->delete();
                $missing = true;

                return;
            }

            if ($reason = $this->restoreBlocker($type, $record)) {
                throw new ArchiveException($reason);
            }

            $this->applyRestore($type, $record, (array) ($locked->state ?? []));

            $locked->delete();
        });

        if ($missing) {
            throw new ArchiveException('The original record no longer exists, so it cannot be restored. The stale archive entry was removed.');
        }

        if ($type === 'landing_item') {
            LandingPageContent::forget();
        }

        $this->log($admin, 'Restored Record', $type, $id, $label);
    }

    // =====================================================================
    //  PERMANENT DELETE
    // =====================================================================

    /** @return string Success message for the Admin. */
    public function permanentlyDelete(ArchiveEntry $entry, User $admin): string
    {
        $this->assertAdmin($admin);

        $type  = (string) $entry->archivable_type;
        $id    = (int) $entry->archivable_id;
        $label = (string) $entry->label;

        $this->assertType($type);

        $files = [];
        $stale = false;

        try {
            DB::transaction(function () use ($entry, $type, $id, &$files, &$stale) {
                $locked = ArchiveEntry::whereKey($entry->id)->lockForUpdate()->first();

                if (! $locked) {
                    throw new ArchiveException('This archive entry no longer exists.');
                }

                $record = $this->find($type, $id, true);

                if (! $record || ($type === 'medicine' && ! $record->trashed())) {
                    $locked->delete();
                    $stale = true;

                    return;
                }

                if ($type === 'user' && $record->role === 'Admin') {
                    throw new ArchiveException('Admin accounts cannot be permanently deleted.');
                }

                $blockers = ArchiveDependencies::blockers($type, $id);

                if ($blockers) {
                    throw new ArchiveException(
                        'Cannot permanently delete "' . $locked->label . '" because it is still linked to: '
                        . implode(', ', $blockers) . '. This history must be kept, so leave the record archived '
                        . '(or restore it) instead.'
                    );
                }

                if ($type === 'user') {
                    $files = array_filter([
                        'avatar'   => $record->avatar,
                        'valid_id' => $record->valid_id,
                    ]);
                }

                match ($type) {
                    'medicine' => $record->forceDelete(),
                    default    => $record->delete(),
                };

                $locked->delete();
            });
        } catch (ArchiveException $e) {
            $this->log($admin, 'Permanent Delete Blocked', $type, $id, $label, $e->getMessage());

            throw $e;
        }

        if ($stale) {
            $this->log($admin, 'Cleared Stale Archive Entry', $type, $id, $label);

            return 'That record was already gone, so only the leftover archive entry was cleared.';
        }

        $this->deleteFiles($files);

        if ($type === 'landing_item') {
            LandingPageContent::forget();
        }

        $this->log($admin, 'Permanently Deleted Record', $type, $id, $label);

        return '"' . $label . '" was permanently deleted.';
    }

    /**
     * Readable list of what still blocks a permanent delete (empty = allowed).
     * Used by the Archive page to disable the button and explain why.
     *
     * @return array<int, string>
     */
    public function deleteBlockers(ArchiveEntry $entry): array
    {
        if (! isset(self::TYPES[$entry->archivable_type])) {
            return ['an unknown record type'];
        }

        return ArchiveDependencies::blockers($entry->archivable_type, (int) $entry->archivable_id);
    }

    // =====================================================================
    //  MEDICINE (existing SoftDeletes) — called from the Medicine model events
    // =====================================================================

    /** A medicine was soft-deleted from Inventory: show it in the Archive. */
    public function recordSoftDeletedMedicine(Medicine $medicine): void
    {
        $actor = auth()->user();

        ArchiveEntry::updateOrCreate(
            ['archivable_type' => 'medicine', 'archivable_id' => $medicine->id],
            [
                'label'            => (string) $medicine->medicine_name,
                'sub_label'        => trim(($medicine->brand ?? '') . ' ' . ($medicine->dosage ?? '')) ?: null,
                'archived_by'      => $actor?->id,
                'archived_by_name' => $actor ? $this->nameOf($actor) : null,
                'archived_at'      => now(),
            ]
        );
    }

    /** A medicine was restored: it is active again, so it leaves the Archive. */
    public function forgetMedicine(int $id): void
    {
        ArchiveEntry::where('archivable_type', 'medicine')->where('archivable_id', $id)->delete();
    }

    // =====================================================================
    //  RULES
    // =====================================================================

    private function archiveBlocker(string $type, Model $record, User $admin): ?string
    {
        switch ($type) {
            case 'user':
                if ($record->role === 'Admin') {
                    return 'Admin accounts cannot be archived.';
                }
                if ($record->id === $admin->id) {
                    return 'You cannot archive your own account.';
                }
                if ($record->approval_status !== 'Approved') {
                    return 'Only approved accounts can be archived. Pending registrations are handled in Pending Accounts.';
                }

                $active = Appointment::whereIn('status', self::ACTIVE_APPOINTMENT_STATUSES)
                    ->where(fn ($q) => $q->where('patient_id', $record->id)->orWhere('doctor_id', $record->id))
                    ->count();

                return $active > 0
                    ? "This account still has {$active} active appointment(s). Complete, cancel or reschedule them first."
                    : null;

            case 'walkin_patient':
                $active = Appointment::whereIn('status', self::ACTIVE_APPOINTMENT_STATUSES)
                    ->where('walkin_patient_id', $record->id)
                    ->count();

                return $active > 0
                    ? "This patient still has {$active} active appointment(s). Complete or cancel them first."
                    : null;

            case 'appointment':
                return in_array($record->status, self::ARCHIVABLE_APPOINTMENT_STATUSES, true)
                    ? null
                    : 'Only Completed, Cancelled, Rejected or No Show appointments can be archived. This one is "' . $record->status . '".';

            case 'service':
                return in_array($record->name, [Service::CONSULTATION, Service::MEDICAL_CERTIFICATE], true)
                    ? 'The "' . $record->name . '" service is used automatically by Billing and cannot be archived.'
                    : null;
        }

        return null;
    }

    private function restoreBlocker(string $type, Model $record): ?string
    {
        switch ($type) {
            case 'user':
                return $record->approval_status === 'Approved'
                    ? null
                    : 'This account is no longer an approved account, so it cannot be restored.';

            case 'service':
                $clash = Service::where('name', $record->name)
                    ->where('id', '!=', $record->id)
                    ->notArchived()
                    ->exists();

                return $clash
                    ? 'An active service named "' . $record->name . '" already exists. Rename or archive it first.'
                    : null;

            case 'medicine':
                $clash = Medicine::where('medicine_name', $record->medicine_name)
                    ->where('brand', $record->brand)
                    ->where('dosage', $record->dosage)
                    ->where('id', '!=', $record->id)
                    ->exists();

                return $clash
                    ? 'An active medicine with the same name, brand and dosage already exists, so restoring this one would create a duplicate.'
                    : null;
        }

        return null;
    }

    /** @return array<string, mixed> Values needed to undo the change. */
    private function applyArchive(string $type, Model $record): array
    {
        switch ($type) {
            case 'user':
                $state = ['status' => $record->status];

                $record->forceFill(['status' => 'Inactive'])->save();

                // Sign the account out of any open session right away.
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $record->id)->delete();
                }

                return $state;

            case 'service':
                $state = ['is_active' => (bool) $record->is_active];

                $record->forceFill(['is_active' => false])->save();

                return $state;

            case 'appointment':
                return ['status' => $record->status];
        }

        // walkin_patient, contact_message, landing_item: the entry alone hides them.
        return [];
    }

    private function applyRestore(string $type, Model $record, array $state): void
    {
        switch ($type) {
            case 'user':
                $previous = $state['status'] ?? 'Active';
                $record->forceFill([
                    'status' => in_array($previous, ['Active', 'Inactive'], true) ? $previous : 'Active',
                ])->save();
                break;

            case 'service':
                $record->forceFill(['is_active' => (bool) ($state['is_active'] ?? true)])->save();
                break;

            case 'medicine':
                $record->restore(); // Medicine::restored event also clears the entry
                break;
        }
    }

    // =====================================================================
    //  HELPERS
    // =====================================================================

    private function find(string $type, int $id, bool $lock = false): ?Model
    {
        $query = match ($type) {
            'user'            => User::query(),
            'walkin_patient'  => Patient::query()->where('is_walk_in', true),
            'appointment'     => Appointment::query(),
            'service'         => Service::query(),
            'contact_message' => ContactMessage::withoutGlobalScopes(),
            'landing_item'    => LandingItem::withoutGlobalScopes(),
            'medicine'        => Medicine::withTrashed(),
        };

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->whereKey($id)->first();
    }

    /** @return array{0: string, 1: ?string} label + sub label shown on the Archive page */
    private function describe(string $type, Model $r): array
    {
        return match ($type) {
            'user' => [
                trim($r->first_name . ' ' . $r->last_name),
                $r->role . ' · @' . $r->username,
            ],
            'walkin_patient' => [
                trim($r->first_name . ' ' . $r->last_name),
                'Walk-in patient',
            ],
            'appointment' => [
                (string) $r->reference_no,
                trim($r->patientName() . ' · ' . $r->status . ' · ' . optional($r->appointment_date)->format('M d, Y'), ' ·'),
            ],
            'service' => [
                (string) $r->name,
                '₱' . number_format((float) $r->price, 2),
            ],
            'contact_message' => [
                trim($r->first_name . ' ' . $r->last_name),
                (string) $r->email,
            ],
            'landing_item' => [
                (string) $r->title,
                ucfirst((string) $r->section),
            ],
            'medicine' => [
                (string) $r->medicine_name,
                trim(($r->brand ?? '') . ' ' . ($r->dosage ?? '')) ?: null,
            ],
        };
    }

    private function entryFor(string $type, int $id): ?ArchiveEntry
    {
        return ArchiveEntry::where('archivable_type', $type)->where('archivable_id', $id)->first();
    }

    private function assertType(string $type): void
    {
        if (! isset(self::TYPES[$type])) {
            throw new ArchiveException('That type of record cannot be archived.');
        }
    }

    private function assertAdmin(User $admin): void
    {
        if ($admin->role !== 'Admin') {
            throw new ArchiveException('Only an Admin can do this.');
        }
    }

    private function nameOf(User $user): string
    {
        return trim($user->first_name . ' ' . $user->last_name);
    }

    /** Remove an account's uploaded files after its row is gone. */
    private function deleteFiles(array $files): void
    {
        if (! empty($files['avatar']) && preg_match('#^avatars/[A-Za-z0-9._-]+$#', $files['avatar']) === 1) {
            Storage::disk('public')->delete($files['avatar']);
        }

        if (! empty($files['valid_id']) && preg_match('#^valid_ids/[A-Za-z0-9._-]+$#', $files['valid_id']) === 1) {
            Storage::disk('private')->delete($files['valid_id']);
            Storage::disk('public')->delete($files['valid_id']);
        }
    }

    /**
     * Audit trail (Admin > User Logs). Only the module, ID and the same label
     * already shown in the lists — never passwords or medical details.
     */
    private function log(User $admin, string $action, string $type, int $id, string $label, ?string $note = null): void
    {
        UserLog::create([
            'user_id' => $admin->id,
            'action'  => $action,
            'module'  => 'Archive',
            'details' => (self::TYPES[$type] ?? $type) . ' #' . $id . ' (' . $label . ')'
                       . ($note ? ' | ' . $note : ''),
        ]);
    }
}
