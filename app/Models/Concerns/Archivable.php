<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lets a model be hidden from its normal listings by the Admin Archive.
 *
 * No column is added to the model's table: "archived" simply means a row for
 * this record exists in archive_entries. The using model must define
 * `public static function archiveType(): string`.
 */
trait Archivable
{
    /** Active records only (anything NOT in the Archive). */
    public function scopeNotArchived(Builder $query): Builder
    {
        $model = $query->getModel();

        return $query->whereNotExists(function ($sub) use ($model) {
            $sub->select(DB::raw(1))
                ->from('archive_entries')
                ->where('archive_entries.archivable_type', static::archiveType())
                ->whereColumn('archive_entries.archivable_id', $model->getQualifiedKeyName());
        });
    }

    public function isArchived(): bool
    {
        return DB::table('archive_entries')
            ->where('archivable_type', static::archiveType())
            ->where('archivable_id', $this->getKey())
            ->exists();
    }
}
