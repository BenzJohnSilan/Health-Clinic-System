<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only snapshot of an Appointment's status at a point in time.
 *
 * Why this exists: `appointments.status` is a single mutable column, so
 * once it changes (e.g. Approved -> Cancelled) there is no way to still
 * know "this appointment WAS Approved" from the appointments table alone.
 * The Patient notification feed needs that old event to still exist (and
 * stay marked as read) even after the status moves on — this table is
 * what makes that possible, without needing a data migration/backfill of
 * old data (rows are only created going forward, see Appointment::boot()).
 */
class AppointmentStatusEvent extends Model
{
    protected $table = 'appointment_status_events';

    protected $fillable = [
        'appointment_id',
        'status',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}
