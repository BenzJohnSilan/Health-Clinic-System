<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\User;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\UserLog;
use Carbon\Carbon;

class StaffPatientController extends Controller
{
    // Relationship dropdown options shared by the Add and Edit forms.
    public const RELATIONSHIPS = [
        'Parent', 'Spouse', 'Sibling', 'Child', 'Relative', 'Friend', 'Guardian', 'Other',
    ];

    // Human-readable labels for the fields we allow Staff to edit, used to
    // build the Activity Log "changed field" summary.
    private const EDITABLE_FIELD_LABELS = [
        'first_name'        => 'First Name',
        'middle_name'       => 'Middle Name',
        'last_name'         => 'Last Name',
        'suffix'            => 'Suffix',
        'birthdate'         => 'Birthdate',
        'gender'            => 'Gender',
        'civil_status'      => 'Civil Status',
        'contact_number'    => 'Contact Number',
        'address'           => 'Complete Address',
        'blood_type'        => 'Blood Type',
        'allergies'         => 'Allergies',
        'emergency_name'    => 'Emergency Contact Name',
        'relationship'      => 'Relationship',
        'emergency_contact' => 'Emergency Contact Number',
        'emergency_address' => 'Emergency Address',
    ];

    // ================= LIST ALL PATIENTS =================
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $type   = $request->query('type', 'all'); // all | registered | walkin
        if (!in_array($type, ['all', 'registered', 'walkin'], true)) {
            $type = 'all';
        }

        // Registered patients (from users table)
        $registeredPatients = collect();
        if ($type === 'all' || $type === 'registered') {
            $registeredQuery = User::where('role', 'Patient');
            $this->applySearch($registeredQuery, $search);

            $registeredPatients = $registeredQuery->get()->map(function ($user) {
                return [
                    'id'                => 'user_' . $user->id,
                    'raw_id'            => $user->id,
                    'type'              => 'user',
                    'first_name'        => $user->first_name,
                    'middle_name'       => $user->middle_name ?? '',
                    'last_name'         => $user->last_name,
                    'suffix'            => $user->suffix ?? '',
                    'birthdate'         => $user->birthdate
                                              ? Carbon::parse($user->birthdate)->format('Y-m-d')
                                              : '',
                    'age'               => $user->birthdate
                                              ? Carbon::parse($user->birthdate)->age
                                              : '-',
                    'gender'            => $user->gender ?? '',
                    'civil_status'      => $user->civil_status ?? '',
                    'contact_number'    => $user->contact_number ?? '',
                    'address'           => $user->address ?? '',
                    'blood_type'        => $user->blood_type ?? '',
                    'allergies'         => $user->allergies ?? '',
                    'emergency_name'    => $user->emergency_name ?? '',
                    // NOTE: the users table stores this column as
                    // `emergency_contact_number` (patients table calls it
                    // `emergency_contact`) — normalized to one key here so
                    // the view/edit UI never has to care which table a
                    // patient came from.
                    'emergency_contact' => $user->emergency_contact_number ?? '',
                    'relationship'      => $user->relationship ?? '',
                    'emergency_address' => $user->emergency_address ?? '',
                    'is_walk_in'        => false,
                ];
            });
        }

        // Walk-in patients (from patients table)
        $walkInPatients = collect();
        if ($type === 'all' || $type === 'walkin') {
            $walkInQuery = Patient::where('is_walk_in', true);
            $this->applySearch($walkInQuery, $search);

            $walkInPatients = $walkInQuery->get()->map(function ($patient) {
                return [
                    'id'                => 'patient_' . $patient->id,
                    'raw_id'            => $patient->id,
                    'type'              => 'patient',
                    'first_name'        => $patient->first_name,
                    'middle_name'       => $patient->middle_name ?? '',
                    'last_name'         => $patient->last_name,
                    'suffix'            => $patient->suffix ?? '',
                    'birthdate'         => $patient->birthdate
                                              ? Carbon::parse($patient->birthdate)->format('Y-m-d')
                                              : '',
                    'age'               => $patient->birthdate
                                              ? Carbon::parse($patient->birthdate)->age
                                              : '-',
                    'gender'            => $patient->gender ?? '',
                    'civil_status'      => $patient->civil_status ?? '',
                    'contact_number'    => $patient->contact_number ?? '',
                    'address'           => $patient->address ?? '',
                    'blood_type'        => $patient->blood_type ?? '',
                    'allergies'         => $patient->allergies ?? '',
                    'emergency_name'    => $patient->emergency_name ?? '',
                    'emergency_contact' => $patient->emergency_contact ?? '',
                    'relationship'      => $patient->relationship ?? '',
                    'emergency_address' => $patient->emergency_address ?? '',
                    'is_walk_in'        => true,
                ];
            });
        }

        // Merge & sort
        $allPatients = $registeredPatients
            ->concat($walkInPatients)
            ->sortBy('first_name')
            ->values();

        // Manual pagination (search/type are preserved automatically —
        // they're part of $request->query(), which is passed straight
        // into the paginator's `query` option below and appended to every
        // page link it generates).
        $perPage     = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pageItems   = $allPatients->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $patients = new LengthAwarePaginator(
            $pageItems,
            $allPatients->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Doctors
        $doctors = User::where('role', 'Doctor')
            ->orderBy('last_name')
            ->get();

        $bookedSlots = Appointment::select(
            'doctor_id',
            'appointment_date',
            'appointment_time'
        )
        ->whereNotIn('status', ['Cancelled', 'Rejected'])
        ->get();

        $relationships = self::RELATIONSHIPS;

        // Per-action permission flags for this Staff user, same convention
        // as the shared Medicine Inventory view ($canAdd/$canEdit/etc. in
        // MedicineController::index). Reaching this action at all already
        // required `view_patients` (route middleware), so that one isn't
        // repeated here — only the finer-grained action permissions are.
        $user = auth()->user();
        $canAddWalkIn           = $user->hasPermission('add_walk_in_patient');
        $canEditPatient         = $user->hasPermission('edit_patient');
        $canViewAppointments    = $user->hasPermission('view_patient_appointments');
        $canScheduleAppointment = $user->hasPermission('schedule_patient_appointment');

        return view('staff.patient', compact(
            'patients',
            'doctors',
            'bookedSlots',
            'search',
            'type',
            'relationships',
            'canAddWalkIn',
            'canEditPatient',
            'canViewAppointments',
            'canScheduleAppointment'
        ));
    }

    /**
     * Case-insensitive partial match on name (incl. full name) and contact
     * number, applied directly on the query (server-side) rather than on
     * an already-loaded collection.
     */
    private function applySearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%' . strtolower($search) . '%';

        $query->where(function ($q) use ($like) {
            $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
              ->orWhereRaw('LOWER(middle_name) LIKE ?', [$like])
              ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
              ->orWhereRaw("LOWER(CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name)) LIKE ?", [$like])
              ->orWhereRaw('LOWER(contact_number) LIKE ?', [$like]);
        });
    }

    // ================= SHARED VALIDATION RULES =================
    private function validationRules(): array
    {
        return [
            // Personal
            'first_name'        => 'required|string|max:50',
            'middle_name'       => 'nullable|string|max:50',
            'last_name'         => 'required|string|max:50',
            'suffix'            => 'nullable|string|max:10',
            'birthdate'         => [
                'required', 'date', 'before_or_equal:today',
                'after:' . now()->subYears(120)->toDateString(),
            ],
            'gender'            => 'required|in:Male,Female,Other',
            'civil_status'      => 'required|in:Single,Married,Widowed,Separated',
            'contact_number'    => ['required', 'regex:/^09[0-9]{9}$/'],
            'address'           => 'required|string|max:255',

            // Medical
            'blood_type'        => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,Unknown',
            'allergy_status'    => 'required|in:none,has',
            'allergies_detail'  => 'required_if:allergy_status,has|nullable|string|max:1000',

            // Emergency Contact
            'emergency_name'     => 'required|string|max:100',
            'relationship'       => 'required|in:' . implode(',', self::RELATIONSHIPS),
            'relationship_other' => 'required_if:relationship,Other|nullable|string|max:50',
            'emergency_contact'  => ['required', 'regex:/^09[0-9]{9}$/'],
            'emergency_address'  => 'nullable|string|max:255',
        ];
    }

    private function validationMessages(): array
    {
        return [
            'birthdate.required'             => 'Birthdate is required.',
            'birthdate.before_or_equal'      => 'Birthdate cannot be in the future.',
            'birthdate.after'                => 'Please enter a valid birthdate.',
            'contact_number.regex'           => 'Contact number must start with 09 and be exactly 11 digits.',
            'emergency_contact.regex'        => 'Emergency contact number must start with 09 and be exactly 11 digits.',
            'allergy_status.required'        => 'Please select whether the patient has any known allergies.',
            'allergies_detail.required_if'   => 'Please specify the patient\'s allergies.',
            'relationship.required'          => 'Please select the emergency contact\'s relationship to the patient.',
            'relationship_other.required_if' => 'Please specify the relationship.',
        ];
    }

    /**
     * Resolve the final `allergies` / `relationship` values that get stored,
     * from the transient `allergy_status` / `allergies_detail` and
     * `relationship` / `relationship_other` form fields. This mirrors the
     * convention already used for the Patient's own profile form
     * (see PatientController::updateProfile).
     */
    private function resolveDerivedFields(array $validated): array
    {
        $validated['allergies'] = $validated['allergy_status'] === 'has'
            ? $validated['allergies_detail']
            : 'No Known Allergies';

        $validated['relationship'] = $validated['relationship'] === 'Other'
            ? $validated['relationship_other']
            : $validated['relationship'];

        return $validated;
    }

    /**
     * Lightweight duplicate check before creating a walk-in patient: an
     * exact contact-number match, or a matching first + last name +
     * birthdate, across both registered and walk-in records. Anything
     * looser risks blocking legitimate patients who happen to share a
     * common name.
     */
    private function findLikelyDuplicate(string $firstName, string $lastName, ?string $birthdate, string $contact): bool
    {
        if ($contact !== '') {
            if (User::where('role', 'Patient')->where('contact_number', $contact)->exists()) {
                return true;
            }
            if (Patient::where('contact_number', $contact)->exists()) {
                return true;
            }
        }

        if ($firstName !== '' && $lastName !== '' && $birthdate) {
            $matchesName = function ($query) use ($firstName, $lastName, $birthdate) {
                return $query
                    ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
                    ->whereRaw('LOWER(last_name) = ?', [strtolower($lastName)])
                    ->whereDate('birthdate', $birthdate);
            };

            if ($matchesName(User::where('role', 'Patient'))->exists()) {
                return true;
            }
            if ($matchesName(Patient::query())->exists()) {
                return true;
            }
        }

        return false;
    }

    // ================= STORE WALK-IN PATIENT =================
    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules(), $this->validationMessages());
        $validated = $this->resolveDerivedFields($validated);

        $isDuplicate = $this->findLikelyDuplicate(
            $validated['first_name'],
            $validated['last_name'],
            $validated['birthdate'],
            $validated['contact_number']
        );

        if ($isDuplicate && $request->input('confirm_duplicate') !== '1') {
            return back()->withInput()->with(
                'duplicate_warning',
                'A patient with a matching name, birthdate, or contact number already exists in the clinic records. '
                . 'If this is a different person, click "Save Patient" again to add them anyway.'
            );
        }

        $patient = Patient::create([
            'first_name'        => $validated['first_name'],
            'middle_name'       => $validated['middle_name'] ?? null,
            'last_name'         => $validated['last_name'],
            'suffix'            => $validated['suffix'] ?? null,
            'birthdate'         => $validated['birthdate'],
            'gender'            => $validated['gender'],
            'civil_status'      => $validated['civil_status'],
            'contact_number'    => $validated['contact_number'],
            'address'           => $validated['address'],
            'blood_type'        => $validated['blood_type'] ?? null,
            'allergies'         => $validated['allergies'],
            'emergency_name'    => $validated['emergency_name'],
            'emergency_contact' => $validated['emergency_contact'],
            'relationship'      => $validated['relationship'],
            'emergency_address' => $validated['emergency_address'] ?? null,
            'is_walk_in'        => true,
        ]);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Added Patient',
            'module'  => 'Patients',
            'details' => 'Added patient ' . $patient->full_name . ' to the clinic records.',
        ]);

        return redirect()->route('staff.patients.index', $request->only(['search', 'type']))
            ->with('success', 'Walk-in patient added successfully.');
    }

    // ================= UPDATE PATIENT (from View Patient → Edit Patient) =================
    public function update(Request $request, string $type, $id)
    {
        if (!in_array($type, ['user', 'patient'], true)) {
            abort(404);
        }

        $validated = $request->validate($this->validationRules(), $this->validationMessages());
        $validated = $this->resolveDerivedFields($validated);

        $newValues = [
            'first_name'        => $validated['first_name'],
            'middle_name'       => $validated['middle_name'] ?? null,
            'last_name'         => $validated['last_name'],
            'suffix'            => $validated['suffix'] ?? null,
            'birthdate'         => $validated['birthdate'],
            'gender'            => $validated['gender'],
            'civil_status'      => $validated['civil_status'],
            'contact_number'    => $validated['contact_number'],
            'address'           => $validated['address'],
            'blood_type'        => $validated['blood_type'] ?? null,
            'allergies'         => $validated['allergies'],
            'emergency_name'    => $validated['emergency_name'],
            'relationship'      => $validated['relationship'],
            'emergency_contact' => $validated['emergency_contact'],
            'emergency_address' => $validated['emergency_address'] ?? null,
        ];

        if ($type === 'user') {
            $record = User::where('role', 'Patient')->findOrFail($id);

            // Registered accounts keep a unique contact number — never
            // silently let an edit collide with another account.
            if ($newValues['contact_number'] !== $record->contact_number) {
                $taken = User::where('contact_number', $newValues['contact_number'])
                    ->where('id', '!=', $record->id)
                    ->exists();

                if ($taken) {
                    return back()->withInput()
                        ->withErrors(['contact_number' => 'This contact number is already used by another account.']);
                }
            }

            $oldValues = [
                'first_name'        => $record->first_name,
                'middle_name'       => $record->middle_name,
                'last_name'         => $record->last_name,
                'suffix'            => $record->suffix,
                'birthdate'         => $record->birthdate ? Carbon::parse($record->birthdate)->format('Y-m-d') : null,
                'gender'            => $record->gender,
                'civil_status'      => $record->civil_status,
                'contact_number'    => $record->contact_number,
                'address'           => $record->address,
                'blood_type'        => $record->blood_type,
                'allergies'         => $record->allergies,
                'emergency_name'    => $record->emergency_name,
                'relationship'      => $record->relationship,
                'emergency_contact' => $record->emergency_contact_number,
                'emergency_address' => $record->emergency_address,
            ];

            $record->update([
                'first_name'               => $newValues['first_name'],
                'middle_name'              => $newValues['middle_name'],
                'last_name'                => $newValues['last_name'],
                'suffix'                   => $newValues['suffix'],
                'birthdate'                => $newValues['birthdate'],
                'gender'                   => $newValues['gender'],
                'civil_status'             => $newValues['civil_status'],
                'contact_number'           => $newValues['contact_number'],
                'address'                  => $newValues['address'],
                'blood_type'               => $newValues['blood_type'],
                'allergies'                => $newValues['allergies'],
                'emergency_name'           => $newValues['emergency_name'],
                'relationship'             => $newValues['relationship'],
                'emergency_contact_number' => $newValues['emergency_contact'],
                'emergency_address'        => $newValues['emergency_address'],
            ]);
        } else {
            // Walk-in patient — scoped the same way the Patients list and
            // the View Appointments route are, so an edited URL id can
            // never reach a registered patient's `patients` row.
            $record = Patient::where('is_walk_in', true)->findOrFail($id);

            $oldValues = [
                'first_name'        => $record->first_name,
                'middle_name'       => $record->middle_name,
                'last_name'         => $record->last_name,
                'suffix'            => $record->suffix,
                'birthdate'         => $record->birthdate ? Carbon::parse($record->birthdate)->format('Y-m-d') : null,
                'gender'            => $record->gender,
                'civil_status'      => $record->civil_status,
                'contact_number'    => $record->contact_number,
                'address'           => $record->address,
                'blood_type'        => $record->blood_type,
                'allergies'         => $record->allergies,
                'emergency_name'    => $record->emergency_name,
                'relationship'      => $record->relationship,
                'emergency_contact' => $record->emergency_contact,
                'emergency_address' => $record->emergency_address,
            ];

            $record->update($newValues);
        }

        $fullName = trim($record->first_name . ' ' . $record->last_name);

        $changes = [];
        foreach (self::EDITABLE_FIELD_LABELS as $key => $label) {
            $old = $oldValues[$key] ?? null;
            $new = $newValues[$key] ?? null;

            if ((string) $old !== (string) $new) {
                $oldDisplay = ($old === null || $old === '') ? '—' : $old;
                $newDisplay = ($new === null || $new === '') ? '—' : $new;
                $changes[] = "{$label} from \"{$oldDisplay}\" to \"{$newDisplay}\"";
            }
        }

        if (!empty($changes)) {
            UserLog::create([
                'user_id' => auth()->id(),
                'action'  => 'Updated Patient',
                'module'  => 'Patients',
                'details' => "Updated patient information for {$fullName}. Changed " . implode('; ', $changes) . '.',
            ]);
        }

        return redirect()->route('staff.patients.index', $request->only(['search', 'type', 'page']))
            ->with('success', 'Patient information updated successfully.');
    }

    // ================= VIEW APPOINTMENTS (read-only, per patient) =================
    // Reached from Patients ⋮ → View Appointments. Shows only the selected
    // patient's appointment history using the existing `appointments` table —
    // no new appointment-management actions are added here (those remain on
    // the Staff → Appointments page).
    public function appointments(Request $request, string $type, $id)
    {
        // Resolve the patient record for the given type, scoped the same way
        // the Patients list itself is scoped (role=Patient users, or
        // is_walk_in=true patients). This also prevents staff from reaching
        // an unrelated user/patient record just by editing the URL.
        if ($type === 'user') {
            $patientRecord = User::where('role', 'Patient')->findOrFail($id);
        } else {
            $patientRecord = Patient::where('is_walk_in', true)->findOrFail($id);
        }

        $patientInfo = [
            'raw_id'     => $patientRecord->id,
            'type'       => $type,
            'full_name'  => trim(
                $patientRecord->first_name . ' ' .
                ($patientRecord->middle_name ? $patientRecord->middle_name . ' ' : '') .
                $patientRecord->last_name
            ) . (!empty($patientRecord->suffix) ? ', ' . $patientRecord->suffix : ''),
            'is_walk_in' => $type === 'patient',
        ];

        $query = Appointment::with('doctor')
            ->when($type === 'user',
                fn ($q) => $q->where('patient_id', $patientRecord->id),
                fn ($q) => $q->where('walkin_patient_id', $patientRecord->id)
            )
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time');

        $appointments = $query->paginate(10)->appends($request->query());

        return view('staff.patient-appointments', compact('patientInfo', 'appointments'));
    }
}