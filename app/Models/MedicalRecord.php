<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    protected $table = 'medical_records';

    protected $fillable = [
        'patient_id',
        'walkin_patient_id',
        'doctor_id',
        'appointment_id',
        'chief_complaint',
        'history_of_present_illness',
        'physical_examination',
        'diagnosis',
        'treatment',
        'notes',
        'blood_pressure',
        'temperature',
        'weight',
        'height',
        'pulse_rate',
        'respiratory_rate',
        'oxygen_saturation',
        'vitals_not_taken',
        'vitals_recorded_by',
        'vitals_recorded_at',
    ];

    protected $casts = [
        'vitals_not_taken'   => 'array',
        'vitals_recorded_at' => 'datetime',
    ];

    /**
     * Vital Signs recorded by Staff. Key = column name, value = label and
     * the FIXED unit the system displays (Staff never types a unit).
     */
    public const VITALS = [
        'blood_pressure'    => ['label' => 'Blood Pressure',   'unit' => 'mmHg',        'placeholder' => '120/80'],
        'temperature'       => ['label' => 'Temperature',      'unit' => '°C',          'placeholder' => '36.7'],
        'pulse_rate'        => ['label' => 'Pulse Rate',       'unit' => 'bpm',         'placeholder' => '78'],
        'respiratory_rate'  => ['label' => 'Respiratory Rate', 'unit' => 'breaths/min', 'placeholder' => '18'],
        'oxygen_saturation' => ['label' => 'SpO₂',             'unit' => '%',           'placeholder' => '98'],
        'weight'            => ['label' => 'Weight',           'unit' => 'kg',          'placeholder' => '65'],
        'height'            => ['label' => 'Height',           'unit' => 'cm',          'placeholder' => '170'],
    ];

    public const NOT_TAKEN_REASONS = [
        'Patient declined',
        'Equipment unavailable',
        'Not available',
        'Other',
    ];

    /**
     * Read-only display rows for the Doctor (and any viewer): one entry per
     * vital with its value + fixed unit, or a clear "Not taken" state.
     */
    public function vitalsForDisplay(): array
    {
        $notTaken = $this->vitals_not_taken ?? [];
        $rows = [];

        foreach (self::VITALS as $key => $meta) {
            $raw    = $this->{$key};
            $status = 'empty';
            $value  = null;
            $unit   = null;
            $reason = null;

            if (filled($raw)) {
                $status = 'recorded';
                $value  = trim($raw);
                // Legacy rows were Doctor-typed free text and may already
                // contain a unit; only append ours to plain numeric values.
                $unit   = preg_match('/^[\d.\/\s]+$/', $value) ? $meta['unit'] : null;
            } elseif (isset($notTaken[$key])) {
                $status = 'not_taken';
                $reason = $notTaken[$key]['reason'] ?? 'Not available';
                if (!empty($notTaken[$key]['note'])) {
                    $reason .= ' — ' . $notTaken[$key]['note'];
                }
            }

            $rows[$key] = [
                'label'  => $meta['label'],
                'unit'   => $unit,
                'value'  => $value,
                'status' => $status,
                'reason' => $reason,
            ];
        }

        return $rows;
    }

    public function vitalsRecordedBy()
    {
        return $this->belongsTo(User::class, 'vitals_recorded_by');
    }

    /**
     * Records that hold real consultation content. A Staff-created,
     * vitals-only record must not show up as a "medical record" in the
     * Doctor's Medical Records / patient history lists.
     */
    public function scopeWithConsultation($query)
    {
        return $query->where(function ($q) {
            foreach ([
                'chief_complaint', 'history_of_present_illness', 'physical_examination',
                'diagnosis', 'treatment', 'notes',
            ] as $col) {
                $q->orWhereNotNull($col);
            }
        });
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function walkInPatient()
    {
        return $this->belongsTo(Patient::class, 'walkin_patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}