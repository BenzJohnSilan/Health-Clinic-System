<?php

namespace App\Models;

use App\Services\LandingPageContent;
use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => LandingPageContent::forget());
        static::deleted(fn () => LandingPageContent::forget());
    }
}
