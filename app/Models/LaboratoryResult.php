<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaboratoryResult extends Model
{
    use SoftDeletes;

    protected $table = 'laboratory_results';

    protected $fillable = [
        'laboratory_request_id',
        'test_name',
        'file_path',
        'original_filename',
        'file_type',
        'file_size',
        'uploaded_by',
        'uploaded_at',
        'notes',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function laboratoryRequest()
    {
        return $this->belongsTo(LaboratoryRequest::class, 'laboratory_request_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Public URL for previewing/downloading this file. Stored on the
     * 'public' disk, same convention as avatars/signatures/valid IDs
     * elsewhere in the project.
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function getReadableSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Soft-delete every OTHER active result on the same laboratory
     * request that occupies the same "test slot" as $testName (null
     * meaning the General / All Requested Tests slot). Called right
     * after a new result is created for that slot, so at most one
     * active (current) file ever exists per test at a time — this is
     * what makes a plain re-upload and an explicit "Replace" behave the
     * same way: the newest file for a test silently becomes the current
     * one, and the patient-facing UI never has to show two "current"
     * files for the same test (Section 4 of the upload workflow spec).
     *
     * The superseded rows are only soft-deleted (kept for the Doctor's
     * audit trail via ::withTrashed()), but their files are removed
     * from storage — same convention already used by the Patient's
     * "Remove" action.
     */
    public static function supersede(int $laboratoryRequestId, ?string $testName, int $exceptResultId): void
    {
        $query = static::where('laboratory_request_id', $laboratoryRequestId)
            ->where('id', '!=', $exceptResultId);

        $testName === null ? $query->whereNull('test_name') : $query->where('test_name', $testName);

        foreach ($query->get() as $old) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($old->file_path);
            $old->delete();
        }
    }
}
