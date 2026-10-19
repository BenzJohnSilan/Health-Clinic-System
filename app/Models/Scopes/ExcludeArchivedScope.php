<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope for Admin-only content (contact messages, website items) so an
 * archived row disappears from every query — lists, counts, route model
 * binding — without touching each controller. The Archive service reads these
 * rows with withoutGlobalScopes().
 */
class ExcludeArchivedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->notArchived();
    }
}
