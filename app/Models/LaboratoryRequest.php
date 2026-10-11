<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LaboratoryRequest extends Model
{
    use HasFactory;

    protected $table = 'laboratory_requests';

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'walkin_patient_id',
        'doctor_id',
        'reference_no',
        'diagnosis',
        'other_test_description',
        'status',
        'requested_at',
        'result_received_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'follow_up_recommended',
        'follow_up_instructions',
    ];

    protected $casts = [
        'follow_up_recommended' => 'boolean',
        'requested_at'        => 'datetime',
        'result_received_at'  => 'datetime',
        'reviewed_at'         => 'datetime',
    ];

    /**
     * The fixed laboratory test catalog the Doctor selects from during
     * consultation (Section 1 of the Laboratory workflow spec). "Others"
     * is always last and pairs with the free-text other_test_description
     * field on this model.
     */
    public const TEST_CATALOG = [
        'CBC',
        'Peripheral Blood Smear',
        'FBS',
        'Alkaline Phosphatase',
        'HbA1c',
        'TSH',
        'Lipid Profile',
        'T3',
        'ALT / SGPT',
        'T4',
        'AST / SGOT',
        'HBsAg',
        'Blood Uric Acid',
        'Urinalysis',
        'BUN',
        '12-L ECG',
        'Creatinine',
        'X-Ray',
        'Cholesterol',
        'Ultrasound',
        'Sodium',
        'Potassium',
        'Others',
    ];

    /**
     * Dropdown value meaning "this file covers all requested tests"
     * (Section 1/11 of the upload workflow spec) — stored as a null
     * test_name on laboratory_results, preserving the pre-existing
     * single-file-for-multiple-tests behavior while still letting the
     * "Which test is this result for?" dropdown be a required field.
     */
    public const GENERAL_TEST_OPTION = 'General / All Requested Tests';

    protected static function boot()
    {
        parent::boot();

        static::creating(function (LaboratoryRequest $request) {
            if (empty($request->reference_no)) {
                $request->reference_no = self::generateReferenceNo();
            }
            if (empty($request->requested_at)) {
                $request->requested_at = now();
            }
            if (empty($request->status)) {
                $request->status = 'Awaiting Result';
            }
        });
    }

    /**
     * Generate unique reference number.
     * Format: LAB-YYYYMMDD-XXXXX (e.g. LAB-20260922-00001) — same
     * pattern as Appointment::generateReferenceNo().
     */
    private static function generateReferenceNo(): string
    {
        $date   = now()->format('Ymd');
        $prefix = 'LAB-' . $date . '-';

        $latest = self::where('reference_no', 'like', $prefix . '%')
            ->orderByDesc('reference_no')
            ->value('reference_no');

        if ($latest) {
            $lastNumber = (int) substr($latest, strlen($prefix));
            $next       = $lastNumber + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    // ====================
    // RELATIONSHIPS
    // ====================

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function walkinPatient()
    {
        return $this->belongsTo(Patient::class, 'walkin_patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function tests()
    {
        return $this->hasMany(LaboratoryRequestTest::class, 'laboratory_request_id');
    }

    public function results()
    {
        return $this->hasMany(LaboratoryResult::class, 'laboratory_request_id');
    }

    public function corrections()
    {
        return $this->hasMany(LaboratoryResultCorrection::class, 'laboratory_request_id');
    }

    /**
     * Follow-up appointments booked for THIS laboratory request (via
     * appointments.follow_up_laboratory_request_id). Normally zero or
     * one; more only if an earlier one was Cancelled/Rejected/No Show.
     */
    public function followUpAppointments()
    {
        return $this->hasMany(Appointment::class, 'follow_up_laboratory_request_id');
    }

    /**
     * The correction request currently awaiting a new upload, if any —
     * kept ONLY for any legacy/simple "is something pending" checks.
     * Prefer pendingCorrections()/pendingCorrectionsByTest() wherever
     * more than one test's correction state matters (Section 8 of the
     * multi-test correction workflow spec: CBC and FBS can each be
     * independently "Correction Needed" at the same time).
     */
    public function pendingCorrection()
    {
        return $this->hasOne(LaboratoryResultCorrection::class, 'laboratory_request_id')
            ->where('status', 'pending')
            ->latestOfMany();
    }

    /**
     * EVERY currently-open correction on this request — there can be
     * more than one at once (one per flagged test).
     */
    public function pendingCorrections()
    {
        return $this->hasMany(LaboratoryResultCorrection::class, 'laboratory_request_id')
            ->where('status', 'pending');
    }

    /**
     * Most recent correction request regardless of status (for history display).
     */
    public function latestCorrection()
    {
        return $this->hasOne(LaboratoryResultCorrection::class, 'laboratory_request_id')
            ->latestOfMany();
    }

    // ====================
    // HELPERS
    // ====================

    /**
     * Returns whichever patient record exists (registered or walk-in).
     * Usage: $laboratoryRequest->resolvedPatient()
     */
    public function resolvedPatient()
    {
        return $this->patient ?? $this->walkinPatient;
    }

    public function patientName(): string
    {
        $p = $this->resolvedPatient();

        if (!$p) {
            return 'Unknown Patient';
        }

        return trim($p->first_name . ' ' . $p->last_name);
    }

    /**
     * Comma-separated display of the requested tests, e.g.
     * "CBC, Urinalysis, Others (Vitamin D Panel)".
     */
    public function testsDisplay(): string
    {
        $names = $this->tests->map(function (LaboratoryRequestTest $test) {
            if ($test->test_name === 'Others' && $this->other_test_description) {
                return 'Others (' . $this->other_test_description . ')';
            }
            return $test->test_name;
        });

        return $names->isEmpty() ? '—' : $names->implode(', ');
    }

    // ====================
    // FOLLOW-UP CONSULTATION
    // ====================

    /**
     * The follow-up appointment currently "holding" this recommendation
     * (anything not Cancelled/Rejected/No Show — Completed included),
     * newest first. Null if none was booked yet or all were cancelled.
     */
    public function currentFollowUpAppointment(): ?Appointment
    {
        return $this->followUpAppointments
            ->sortByDesc('created_at')
            ->first(fn (Appointment $a) => !in_array($a->status, Appointment::INACTIVE_STATUSES, true));
    }

    /**
     * True when the Doctor recommended a follow-up on a Reviewed result
     * and no active/completed follow-up appointment exists for it yet —
     * i.e. the "Book Follow-up Consultation" action should be offered.
     */
    public function needsFollowUpBooking(): bool
    {
        return $this->isReviewed()
            && $this->follow_up_recommended
            && $this->currentFollowUpAppointment() === null;
    }

    /**
     * Query counterpart of needsFollowUpBooking(), used both for listing
     * and for the authoritative server-side check before a follow-up
     * appointment is created.
     */
    public function scopeAwaitingFollowUpBooking($query)
    {
        return $query->where('status', 'Reviewed')
            ->where('follow_up_recommended', true)
            ->whereDoesntHave('followUpAppointments', function ($q) {
                $q->whereNotIn('status', Appointment::INACTIVE_STATUSES);
            });
    }

    public function isAwaitingResult(): bool
    {
        return $this->status === 'Awaiting Result';
    }

    public function isResultReceived(): bool
    {
        return $this->status === 'Result Received';
    }

    public function isReviewed(): bool
    {
        return $this->status === 'Reviewed';
    }

    public function isCorrectionRequired(): bool
    {
        return $this->status === 'Correction Required';
    }

    /**
     * The Doctor may only correct (edit) or delete a request that has not
     * entered the result workflow yet: still Awaiting Result AND no
     * result file uploaded. Printing never affects this — only a result
     * upload (which moves the request to Result Received) locks it.
     */
    public function isEditable(): bool
    {
        if (!$this->isAwaitingResult()) {
            return false;
        }

        return $this->relationLoaded('results')
            ? $this->results->isEmpty()
            : !$this->results()->exists();
    }

    /**
     * A Staff/Patient upload is only accepted while the request has not
     * yet been reviewed — once the Doctor has reviewed it, the record
     * is considered final for that request. "Correction Required" also
     * accepts uploads: that is exactly the state that exists to collect
     * the corrected file.
     */
    public function canAcceptUploads(): bool
    {
        return in_array($this->status, ['Awaiting Result', 'Result Received', 'Correction Required'], true);
    }

    /**
     * Whether the Patient may remove/replace their own uploaded file(s)
     * right now (Section 4 of the upload workflow spec):
     *   - Result Received: yes, the Doctor hasn't reviewed it yet.
     *   - Correction Required: yes, that's exactly what this state is for.
     *   - Awaiting Result: nothing has been uploaded yet, nothing to remove.
     *   - Reviewed: locked — never removable, no silent replacement.
     */
    public function canPatientModifyResults(): bool
    {
        return in_array($this->status, ['Result Received', 'Correction Required'], true);
    }

    /**
     * Maps every requested test name to its CURRENT uploaded result (or
     * null if that test still has nothing uploaded), for the "Uploaded
     * Laboratory Results" modal (Section 3 of the upload workflow spec).
     *
     * A file uploaded against the General / All Requested Tests slot
     * (null test_name) counts as the current result for every requested
     * test that doesn't already have its own specific file — same
     * meaning the General option has always had in this workflow.
     *
     * Expects `tests` and `results` to already be loaded/available;
     * `results` is used as-is (SoftDeletes already excludes superseded
     * / removed files), so this never needs its own query.
     */
    public function currentResultsByTest(): \Illuminate\Support\Collection
    {
        $generalResult = $this->results->firstWhere('test_name', null);
        $specificByTest = $this->results->whereNotNull('test_name')->keyBy('test_name');

        return $this->tests->pluck('test_name')->mapWithKeys(function ($testName) use ($specificByTest, $generalResult) {
            return [$testName => $specificByTest->get($testName) ?? $generalResult];
        });
    }

    /**
     * Maps every requested test name to ITS OWN pending correction (or
     * null if that test isn't flagged) — the per-test counterpart of
     * currentResultsByTest(), and what makes "CBC needs correction,
     * FBS is fine" independently true and independently resolvable
     * (Section 8/14 of the multi-test correction workflow spec).
     *
     * A pending correction with a null test_name (legacy data, from
     * before the test selector was required) applies to every test
     * slot that doesn't already have its own more specific one.
     *
     * Expects `tests` and `corrections` to already be loaded/available.
     */
    public function pendingCorrectionsByTest(): \Illuminate\Support\Collection
    {
        $pending = $this->corrections->where('status', 'pending');
        $general = $pending->firstWhere('test_name', null);
        $specific = $pending->whereNotNull('test_name')->keyBy('test_name');

        return $this->tests->pluck('test_name')->mapWithKeys(function ($testName) use ($specific, $general) {
            return [$testName => $specific->get($testName) ?? $general];
        });
    }

    /**
     * Resolve whichever pending correction belongs to $testName (or the
     * General/null slot), if any — called right after a new result is
     * saved for that exact slot. Replacing test X must NEVER resolve a
     * different test's still-open correction (Section 14 of the spec).
     * Does not touch the request's overall status; call
     * recomputeStatus() afterwards.
     */
    public function resolvePendingCorrectionFor(?string $testName, int $resolvingResultId): bool
    {
        $query = $this->corrections()->where('status', 'pending');
        $testName === null ? $query->whereNull('test_name') : $query->where('test_name', $testName);

        $pending = $query->first();

        if (!$pending) {
            return false;
        }

        $pending->update([
            'status'              => 'resolved',
            'resolving_result_id' => $resolvingResultId,
            'resolved_at'         => now(),
        ]);

        return true;
    }

    /**
     * Recomputes the request's single overall `status` column from its
     * actual child rows, instead of every controller action having to
     * remember to flip it correctly by hand:
     *   - any test still has a pending correction  -> Correction Required
     *   - otherwise, at least one result exists     -> Result Received
     *   - otherwise (nothing uploaded yet)           -> Awaiting Result
     * Reviewed is a manual, locked, terminal state and is never touched
     * here — call sites never invoke this once Reviewed is set, but it
     * still refuses to move a Reviewed request backwards just in case.
     */
    public function recomputeStatus(): void
    {
        if ($this->status === 'Reviewed') {
            return;
        }

        if ($this->corrections()->where('status', 'pending')->exists()) {
            $newStatus = 'Correction Required';
        } elseif ($this->results()->exists()) {
            $newStatus = 'Result Received';
        } else {
            $newStatus = 'Awaiting Result';
        }

        if ($newStatus === $this->status) {
            return;
        }

        $this->status = $newStatus;
        $this->result_received_at = $newStatus === 'Result Received'
            ? ($this->result_received_at ?? now())
            : ($newStatus === 'Awaiting Result' ? null : $this->result_received_at);
        $this->save();
    }
}
