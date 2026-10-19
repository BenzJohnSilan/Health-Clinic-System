<?php

namespace App\Services;

use App\Models\LandingItem;
use App\Models\SystemSetting;
use App\Models\WebsiteSetting;
use App\Support\LandingDefaults;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reads the public landing page content (settings + active items) in two
 * queries, caches the result, and falls back to App\Support\LandingDefaults
 * whenever something is missing — including when the website tables do not
 * exist yet — so the public page can never fail because of this module.
 *
 * Every write to WebsiteSetting / LandingItem calls forget(), so Admin edits
 * show on the public page immediately.
 */
class LandingPageContent
{
    public const CACHE_KEY = 'landing.content.v1';
    public const IMAGE_DIR = 'landing';

    /**
     * @return array{settings: array<string,string>, items: array<string, array<int,array>>}
     */
    public static function get(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => self::build());
        } catch (Throwable $e) {
            Log::warning('Landing page content unavailable, using defaults: ' . $e->getMessage());

            return self::fallback();
        }
    }

    public static function forget(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable $e) {
            // Cache being unavailable must never block an Admin save.
        }
    }

    /** Settings with defaults applied — what the Admin forms show. */
    public static function settings(): array
    {
        return self::get()['settings'];
    }

    private static function build(): array
    {
        $settings = LandingDefaults::settings();

        foreach (WebsiteSetting::query()->pluck('value', 'key') as $key => $value) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = (string) $value;
            }
        }

        // The clinic name saved in Admin > System Settings is the clinic's
        // official name and wins over the landing "Brand name". Only an
        // actually saved, non-empty value counts; never the default.
        $official = trim((string) SystemSetting::query()->where('key', 'clinic_name')->value('value'));
        if ($official !== '') {
            $settings['brand_name'] = $official;
        }

        $all = LandingItem::query()->orderBy('sort_order')->orderBy('id')->get();

        // Nothing in the table at all (never seeded) -> original content.
        if ($all->isEmpty()) {
            return self::fallback($settings);
        }

        $items = array_fill_keys(LandingDefaults::SECTIONS, []);

        foreach ($all->where('is_active', true) as $item) {
            if (!array_key_exists($item->section, $items)) {
                continue;
            }
            $items[$item->section][] = [
                'id'       => $item->id,
                'category' => $item->category,
                'title'    => $item->title,
                'body'     => $item->body,
                'icon'     => self::safeIcon($item->icon),
                'image'    => $item->image_url,
            ];
        }

        $settings['hero_image_url']  = self::imageUrl($settings['hero_image']);
        $settings['about_image_url'] = self::imageUrl($settings['about_image']);

        return ['settings' => $settings, 'items' => $items];
    }

    private static function fallback(?array $settings = null): array
    {
        $settings ??= LandingDefaults::settings();
        $settings['hero_image_url']  = null;
        $settings['about_image_url'] = null;

        $items = array_fill_keys(LandingDefaults::SECTIONS, []);
        foreach (LandingDefaults::items() as $i => $row) {
            $items[$row['section']][] = [
                'id'       => null,
                'category' => $row['category'],
                'title'    => $row['title'],
                'body'     => $row['body'],
                'icon'     => $row['icon'],
                'image'    => null,
            ];
        }

        return ['settings' => $settings, 'items' => $items];
    }

    public static function safeIcon(?string $icon): ?string
    {
        return $icon && preg_match('/^bx[sl]?-[a-z0-9-]+$/', $icon) ? $icon : null;
    }

    private static function imageUrl(?string $path): ?string
    {
        return self::isManagedPath($path) && Storage::disk('public')->exists($path)
            ? asset('storage/' . $path)
            : null;
    }

    // ------------------------------------------------------------------
    //  Image handling (public disk, landing/ only)
    // ------------------------------------------------------------------

    /** A stored path is only ever trusted if it is a plain file in landing/. */
    public static function isManagedPath(?string $path): bool
    {
        return is_string($path)
            && preg_match('#^' . self::IMAGE_DIR . '/[A-Za-z0-9_\-]+\.(jpg|jpeg|png|webp)$#', $path) === 1;
    }

    /** Store an already-validated upload under a generated safe name. */
    public static function storeImage(UploadedFile $file): string
    {
        // store() names the file with a random hash + the extension guessed
        // from the real MIME type — the client-supplied name is never used.
        return $file->store(self::IMAGE_DIR, 'public');
    }

    public static function deleteImage(?string $path): void
    {
        if (self::isManagedPath($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
