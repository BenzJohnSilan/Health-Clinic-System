<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingItem;
use App\Models\UserLog;
use App\Models\WebsiteSetting;
use App\Services\LandingPageContent;
use App\Support\LandingDefaults;
use App\Support\LandingText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Admin → Website Management.
 *
 * Reachable only through the `admin` route group (auth + verified +
 * role:Admin), the same gate as Manage Permissions and Backup & Recovery.
 * It edits public landing page DATA only; the page layout stays in Blade.
 */
class WebsiteManagementController extends Controller
{
    private const TABS = [
        'hero', 'about', 'services', 'features', 'steps',
        'workflow', 'faqs', 'contact', 'footer',
    ];

    /** Sections whose items need a description / answer. */
    private const BODY_REQUIRED = ['services', 'features', 'steps', 'faqs'];

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'hero';

        $items = LandingItem::query()
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('section');

        return view('admin.website.index', [
            'tab'           => $tab,
            'tabs'          => self::TABS,
            'settings'      => LandingPageContent::settings(),
            'items'         => $items,
            'faqCategories' => LandingDefaults::FAQ_CATEGORIES,
            'heroImageUrl'  => $this->currentImageUrl('hero_image'),
            'aboutImageUrl' => $this->currentImageUrl('about_image'),
        ]);
    }

    // ------------------------------------------------------------------
    //  Single-instance settings
    // ------------------------------------------------------------------

    public function updateSettings(Request $request, string $group)
    {
        $groups = LandingDefaults::groups();
        abort_unless(isset($groups[$group]), 404);

        $data = $request->validate($this->settingRules($group), $this->messages());

        DB::transaction(function () use ($group, $groups, $data, $request) {
            foreach ($groups[$group] as $key) {
                $value = trim((string) ($data[$key] ?? ''));

                // Empty link fields mean "use the original destination".
                if (in_array($key, ['footer_privacy_url', 'footer_terms_url'], true) && $value === '') {
                    $value = '#';
                }

                WebsiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
            }

            if (in_array($group, ['hero', 'about'], true)) {
                $this->syncSettingImage($request, "{$group}_image");
            }
        });

        $this->log('Updated Website Content', ucfirst($group) . ' section');

        return $this->back($this->tabFor($group), ucfirst($group) . ' content saved.');
    }

    // ------------------------------------------------------------------
    //  Repeatable items
    // ------------------------------------------------------------------

    public function storeItem(Request $request)
    {
        $section = $this->validSection($request);
        $data    = $this->validateItem($request, $section);

        $item = new LandingItem($this->itemAttributes($data, $section));
        $item->section    = $section;
        $item->sort_order = ((int) LandingItem::where('section', $section)->max('sort_order')) + 1;

        if ($request->hasFile('image') && $section === 'services') {
            $item->image_path = LandingPageContent::storeImage($request->file('image'));
        }

        $item->save();

        $this->log('Added Website Item', "{$section}: {$item->title}");

        return $this->back($section, 'Item added.');
    }

    public function updateItem(Request $request, LandingItem $item)
    {
        $section = $item->section;
        $data    = $this->validateItem($request, $section);

        $item->fill($this->itemAttributes($data, $section));

        if ($section === 'services') {
            $old = $item->image_path;

            if ($request->hasFile('image')) {
                $item->image_path = LandingPageContent::storeImage($request->file('image'));
            } elseif ($request->boolean('remove_image')) {
                $item->image_path = null;
            }

            $item->save();

            if ($old && $old !== $item->image_path) {
                LandingPageContent::deleteImage($old);
            }
        } else {
            $item->save();
        }

        $this->log('Updated Website Item', "{$section}: {$item->title}");

        return $this->back($section, 'Item updated.');
    }

    public function toggleItem(LandingItem $item)
    {
        $item->update(['is_active' => !$item->is_active]);

        $this->log($item->is_active ? 'Showed Website Item' : 'Hid Website Item', "{$item->section}: {$item->title}");

        return $this->back($item->section, $item->is_active ? 'Item is now visible.' : 'Item is now hidden.');
    }

    public function moveItem(LandingItem $item, string $direction)
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        DB::transaction(function () use ($item, $direction) {
            $ids = LandingItem::where('section', $item->section)
                ->orderBy('sort_order')->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')->all();

            $pos    = array_search($item->id, $ids, true);
            $target = $direction === 'up' ? $pos - 1 : $pos + 1;

            if ($pos !== false && isset($ids[$target])) {
                [$ids[$pos], $ids[$target]] = [$ids[$target], $ids[$pos]];
            }

            // Renumber the whole section so order is always 1..n.
            foreach ($ids as $i => $id) {
                LandingItem::where('id', $id)->update(['sort_order' => $i + 1]);
            }
        });

        LandingPageContent::forget();

        return $this->back($item->section, null);
    }

    public function destroyItem(LandingItem $item)
    {
        $section = $item->section;
        $title   = $item->title;

        $item->delete();   // model event removes its image file

        $this->log('Deleted Website Item', "{$section}: {$title}");

        return $this->back($section, 'Item deleted.');
    }

    // ------------------------------------------------------------------
    //  Validation
    // ------------------------------------------------------------------

    private function imageRules(): array
    {
        return ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'];
    }

    private function urlRule(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) {
            if (!LandingText::isSafeUrl($value)) {
                $fail('Use a page anchor (#about), a site path (/login) or a full http(s) address.');
            }
        };
    }

    private function settingRules(string $group): array
    {
        $section = fn (int $sub = 300) => [
            "{$group}_label" => ['required', 'string', 'max:60'],
            "{$group}_title" => ['required', 'string', 'max:120'],
            "{$group}_sub"   => ['nullable', 'string', 'max:' . $sub],
        ];

        return match ($group) {
            'hero' => [
                'hero_badge'           => ['nullable', 'string', 'max:80'],
                'hero_title_line1'     => ['required', 'string', 'max:80'],
                'hero_title_highlight' => ['nullable', 'string', 'max:80'],
                'hero_description'     => ['required', 'string', 'max:400'],
                'hero_primary_label'   => ['required', 'string', 'max:40'],
                'hero_primary_url'     => ['nullable', 'string', 'max:255', $this->urlRule()],
                'hero_secondary_label' => ['required', 'string', 'max:40'],
                'hero_secondary_url'   => ['nullable', 'string', 'max:255', $this->urlRule()],
                'hero_image'           => $this->imageRules(),
                'remove_hero_image'    => ['nullable', 'boolean'],
            ],
            'about' => [
                'about_label'           => ['required', 'string', 'max:60'],
                'about_title'           => ['required', 'string', 'max:80'],
                'about_title_highlight' => ['nullable', 'string', 'max:60'],
                'about_text_1'          => ['required', 'string', 'max:1200'],
                'about_text_2'          => ['nullable', 'string', 'max:1200'],
                'about_image'           => $this->imageRules(),
                'remove_about_image'    => ['nullable', 'boolean'],
            ],
            'services', 'features', 'steps', 'workflow', 'faqs' => $section(),
            'contact' => $section() + [
                'contact_address' => ['required', 'string', 'max:250'],
                'contact_phones'  => ['required', 'string', 'max:200', 'regex:/^[0-9+()\-.\s]+$/'],
                'contact_emails'  => ['required', 'string', 'max:250', function (string $a, $v, \Closure $fail) {
                    foreach (LandingText::lines($v) as $line) {
                        if (!filter_var($line, FILTER_VALIDATE_EMAIL)) {
                            $fail('Each line must be a valid email address.');
                            return;
                        }
                    }
                }],
            ] + $section(),
            'location' => [
                'location_label'      => ['required', 'string', 'max:60'],
                'location_title'      => ['required', 'string', 'max:120'],
                'location_sub'        => ['nullable', 'string', 'max:300'],
                'location_address'    => ['required', 'string', 'max:250'],
                'location_directions' => ['nullable', 'string', 'max:600'],
                'location_parking'    => ['nullable', 'string', 'max:400'],
                'location_hours'      => ['required', 'string', 'max:250'],
                'location_map_url'    => ['nullable', 'string', 'max:1500', function (string $a, $v, \Closure $fail) {
                    if ($v !== '' && !preg_match('#^https://www\.google\.com/maps/embed\?[^\s"<>]+$#', $v)) {
                        $fail('Paste the address from Google Maps → Share → Embed a map (it starts with https://www.google.com/maps/embed).');
                    }
                }],
            ],
            'footer' => [
                'brand_name'            => ['required', 'string', 'max:40'],
                'footer_description'    => ['required', 'string', 'max:300'],
                'footer_address'        => ['required', 'string', 'max:200'],
                'footer_phone'          => ['required', 'string', 'max:40', 'regex:/^[0-9+()\-.\s]+$/'],
                'footer_email'          => ['required', 'email', 'max:120'],
                'footer_copyright_name' => ['required', 'string', 'max:80'],
                'footer_privacy_url'    => ['nullable', 'string', 'max:255', $this->urlRule()],
                'footer_terms_url'      => ['nullable', 'string', 'max:255', $this->urlRule()],
            ],
        };
    }

    private function validSection(Request $request): string
    {
        $section = (string) $request->input('section');
        abort_unless(in_array($section, LandingDefaults::SECTIONS, true), 422, 'Unknown section.');

        return $section;
    }

    private function validateItem(Request $request, string $section): array
    {
        $rules = [
            'title'     => ['required', 'string', 'max:255'],
            'body'      => [in_array($section, self::BODY_REQUIRED, true) ? 'required' : 'nullable', 'string', 'max:1500'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($section === 'faqs') {
            $rules['category'] = ['required', Rule::in(array_keys(LandingDefaults::FAQ_CATEGORIES))];
        } else {
            $rules['icon'] = ['required', 'string', 'max:60', 'regex:/^bx[sl]?-[a-z0-9-]+$/'];
        }

        if ($section === 'services') {
            $rules['image'] = $this->imageRules();
        }

        return Validator::make($request->all(), $rules, $this->messages() + [
            'icon.regex' => 'Icon must be a Boxicons class such as bxs-heart or bx-plus-medical.',
        ])->validate();
    }

    private function itemAttributes(array $data, string $section): array
    {
        return [
            'title'     => trim($data['title']),
            'body'      => isset($data['body']) ? trim($data['body']) : null,
            'category'  => $section === 'faqs' ? $data['category'] : null,
            'icon'      => $section === 'faqs' ? null : $data['icon'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function messages(): array
    {
        return [
            'image'                 => 'The file must be an image (JPG, PNG or WebP).',
            'mimes'                 => 'Only JPG, PNG or WebP images are allowed.',
            'mimetypes'             => 'Only JPG, PNG or WebP images are allowed.',
            'hero_image.max'        => 'The image may not be larger than 2 MB.',
            'about_image.max'       => 'The image may not be larger than 2 MB.',
            'image.max'             => 'The image may not be larger than 2 MB.',
            'contact_phones.regex'  => 'Phone numbers may only contain digits, spaces and + ( ) - .',
            'footer_phone.regex'    => 'Phone numbers may only contain digits, spaces and + ( ) - .',
        ];
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /** Upload / replace / remove the image stored in a website setting. */
    private function syncSettingImage(Request $request, string $key): void
    {
        $current = WebsiteSetting::where('key', $key)->value('value');

        if ($request->hasFile($key)) {
            $new = LandingPageContent::storeImage($request->file($key));
            WebsiteSetting::updateOrCreate(['key' => $key], ['value' => $new]);
            LandingPageContent::deleteImage($current);
        } elseif ($request->boolean("remove_{$key}")) {
            WebsiteSetting::updateOrCreate(['key' => $key], ['value' => '']);
            LandingPageContent::deleteImage($current);
        }
    }

    private function currentImageUrl(string $key): ?string
    {
        $settings = LandingPageContent::settings();

        return $settings[$key . '_url'] ?? null;
    }

    /** Admin tab that hosts a settings group. */
    private function tabFor(string $group): string
    {
        return $group === 'location' ? 'contact' : $group;
    }

    private function back(string $tab, ?string $message)
    {
        $redirect = redirect()->route('admin.website.index', ['tab' => $tab]);

        return $message ? $redirect->with('success', $message) : $redirect;
    }

    private function log(string $action, string $details): void
    {
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => $action,
            'module'  => 'Website Management',
            'details' => $details,
        ]);
    }
}
