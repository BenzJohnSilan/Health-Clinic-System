<?php

namespace App\Models;

use App\Services\LandingPageContent;
use App\Services\SystemSettings;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        // The landing page shows the clinic name/logo, so refresh its cache too.
        static::saved(function () {
            SystemSettings::forget();
            LandingPageContent::forget();
        });
        static::deleted(function () {
            SystemSettings::forget();
            LandingPageContent::forget();
        });
    }
}
