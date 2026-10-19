<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata row for one archived record (see the archive_entries migration).
 * The archived record itself stays in its own table.
 */
class ArchiveEntry extends Model
{
    protected $fillable = [
        'archivable_type',
        'archivable_id',
        'label',
        'sub_label',
        'state',
        'archived_by',
        'archived_by_name',
        'archived_at',
    ];

    protected $casts = [
        'state'       => 'array',
        'archived_at' => 'datetime',
    ];

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /** Name to show: the live account if it still exists, else the saved copy. */
    public function archivedByName(): string
    {
        if ($this->archivedBy) {
            return trim($this->archivedBy->first_name . ' ' . $this->archivedBy->last_name);
        }

        return $this->archived_by_name ?: '—';
    }
}
