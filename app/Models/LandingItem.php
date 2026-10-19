<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Scopes\ExcludeArchivedScope;
use App\Services\LandingPageContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One repeatable piece of public landing page content. See the
 * landing_items migration — this is NOT the billing Service model.
 */
class LandingItem extends Model
{
    use Archivable;

    /** Key used in archive_entries for this model. */
    public static function archiveType(): string
    {
        return 'landing_item';
    }

    protected $fillable = [
        'section', 'category', 'title', 'body', 'icon',
        'image_path', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // Archived items vanish from the admin list AND the public landing page;
        // their row and image file stay untouched.
        static::addGlobalScope(new ExcludeArchivedScope());

        static::saved(fn () => LandingPageContent::forget());
        static::deleted(function (LandingItem $item) {
            LandingPageContent::forget();
            LandingPageContent::deleteImage($item->image_path);
        });
    }

    public function scopeSection($query, string $section)
    {
        return $query->where('section', $section)->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path && Storage::disk('public')->exists($this->image_path)
            ? asset('storage/' . $this->image_path)
            : null;
    }
}
