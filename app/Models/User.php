<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        // PROFILE
        'avatar',

        // PERSONAL INFO
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        'gender',
        'civil_status',
        'address',
        'contact_number',

        // VERIFICATION
        'id_type',
        'valid_id',

        // REASON
        'reason',

        // MEDICAL INFO
        'blood_type',
        'allergies',

        // EMERGENCY CONTACT
        'emergency_name',
        'emergency_contact_number',
        'relationship',
        'emergency_address',

        // LOGIN INFO
        'username',
        'email',
        'password',

        // OTP
        'email_otp',
        'otp_expires_at',

        // ACCOUNT CONTROL
        'role',
        'status',
        'approval_status',
        'profile_completed',

        // DOCTOR
        'specialization',
        'license_number',
        'signature',

        // STAFF
        'employee_id',
        'position',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'otp_expires_at'    => 'datetime',
        'birthdate'         => 'date',
        'profile_completed' => 'boolean',
    ];

    /**
     * Full name helper (users table has no `name` column,
     * only first_name/last_name).
     * Usage: $user->full_name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // =========================================================
    //  CASCADE DELETE  –  tanggalin lahat ng related records
    //  bago matanggal ang user
    // =========================================================
    protected static function boot()
    {
        parent::boot();

        static::deleting(function (User $user) {
            // Appointments (bilang patient)
            $user->appointments()->delete();

            // Appointments (bilang doctor)
            $user->doctorAppointments()->delete();
        });

        // Keep the cached `profile_completed` flag in sync automatically
        // any time a Patient or Doctor record is saved. This is a
        // convenience cache only — booking eligibility and account-settings
        // status must always re-check computeProfileComplete() directly
        // rather than trusting this flag.
        static::saving(function (User $user) {
            if (in_array($user->role, ['Patient', 'Doctor'], true)) {
                $user->profile_completed = $user->computeProfileComplete();
            }
        });
    }

    // =========================================================
    //  PROFILE COMPLETION (Patient & Doctor)
    // =========================================================

    /**
     * The required profile fields for a given role, mapped to their
     * human-readable labels. Defaults to the Patient field set for
     * backward compatibility with existing callers that don't pass a role.
     */
    public static function requiredProfileFields(?string $role = null): array
    {
        $role = $role ?? 'Patient';

        if ($role === 'Doctor') {
            return [
                'first_name'     => 'First Name',
                'last_name'      => 'Last Name',
                'birthdate'      => 'Birthdate',
                'gender'         => 'Gender',
                'civil_status'   => 'Civil Status',
                'address'        => 'Complete Address',
                'contact_number' => 'Contact Number',
                'specialization' => 'Specialization',
                'license_number' => 'License Number',
            ];
        }

        return [
            'first_name'               => 'First Name',
            'last_name'                => 'Last Name',
            'birthdate'                => 'Birthdate',
            'gender'                   => 'Gender',
            'civil_status'             => 'Civil Status',
            'address'                  => 'Complete Address',
            'contact_number'           => 'Contact Number',
            'blood_type'               => 'Blood Type',
            'allergies'                => 'Allergies',
            'emergency_name'           => 'Emergency Contact Name',
            'relationship'             => 'Emergency Contact Relationship',
            'emergency_contact_number' => 'Emergency Contact Number',
            'emergency_address'        => 'Emergency Contact Address',
        ];
    }

    /**
     * List of required profile fields (for this user's role) that are
     * still missing/empty. Always computed live from the actual column
     * values — never trusts the cached `profile_completed` boolean.
     *
     * @return array<int, string> Human-readable labels of missing fields.
     */
    public function getMissingProfileFields(): array
    {
        $missing = [];

        foreach (self::requiredProfileFields($this->role) as $column => $label) {
            $value = $this->{$column};

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * Whether all required Profile fields are actually complete for this
     * user's role (Patient and Doctor only — other roles are always
     * considered complete). This performs a live check of the real
     * columns (does not rely on the cached `profile_completed` boolean).
     */
    public function computeProfileComplete(): bool
    {
        if (!in_array($this->role, ['Patient', 'Doctor'], true)) {
            return true;
        }

        return count($this->getMissingProfileFields()) === 0;
    }

    // =========================================================
    //  ACCESSORS
    // =========================================================

    /**
     * Auto compute age
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birthdate
            ? Carbon::parse($this->birthdate)->age
            : null;
    }

    // =========================================================
    //  RELATIONSHIPS
    // =========================================================

    /**
     * Appointments kung saan siya ang PATIENT
     */
    public function appointments()
    {
        return $this->hasMany(\App\Models\Appointment::class, 'patient_id');
    }

    /**
     * Appointments kung saan siya ang DOCTOR
     */
    public function doctorAppointments()
    {
        return $this->hasMany(\App\Models\Appointment::class, 'doctor_id');
    }

    /**
     * Medical Certificate requests filed by this user (as Patient).
     */
    public function medicalCertificates()
    {
        return $this->hasMany(\App\Models\MedicalCertificate::class, 'patient_id');
    }

    /**
     * Medical Certificates issued/rejected by this user (as Doctor).
     */
    public function issuedMedicalCertificates()
    {
        return $this->hasMany(\App\Models\MedicalCertificate::class, 'doctor_id');
    }

    /**
     * Invoices billed to this user as a registered Patient
     * (Billing & Payments module).
     */
    public function invoices()
    {
        return $this->hasMany(\App\Models\Invoice::class, 'patient_id');
    }

    /**
     * Payments this user (Staff) has received/recorded at the counter.
     */
    public function receivedPayments()
    {
        return $this->hasMany(\App\Models\Payment::class, 'received_by');
    }

    // =========================================================
    //  PERMISSIONS
    // =========================================================

    /**
     * Granular feature permissions manually assigned to this user by
     * an Admin (Manage Permissions). Only meaningful for Doctor/Staff —
     * Admin bypasses permission checks entirely and Patient is never
     * assigned any.
     */
    public function permissions()
    {
        return $this->belongsToMany(\App\Models\Permission::class, 'user_permissions')
                    ->withTimestamps();
    }

    /**
     * Whether this user may perform a given permission-gated action.
     * Admin always returns true (top-level administrator, not subject
     * to feature-level permissions). Every other role — including
     * Patient — is checked against their assigned permissions.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->role === 'Admin') {
            return true;
        }

        return $this->permissions()->where('slug', $slug)->exists();
    }

    /**
     * Whether this user has at least one of the given permissions.
     * Same Admin bypass as hasPermission().
     */
    public function hasAnyPermission(array $slugs): bool
    {
        if ($this->role === 'Admin') {
            return true;
        }

        return $this->permissions()->whereIn('slug', $slugs)->exists();
    }
}