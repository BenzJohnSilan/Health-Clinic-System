<?php

namespace Tests\Feature;

use App\Models\LandingItem;
use App\Models\Service;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\LandingPageContent;
use App\Support\LandingText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin → Website Management and the dynamic public landing page.
 */
class WebsiteManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function admin(): User
    {
        return $this->userWithRole('Admin');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        LandingPageContent::forget();
    }

    private function heroPayload(array $override = []): array
    {
        return array_merge([
            'hero_badge'           => 'Open Daily',
            'hero_title_line1'     => 'Caring For You,',
            'hero_title_highlight' => 'Every Day',
            'hero_description'     => 'A new description for the hero.',
            'hero_primary_label'   => 'Join Now',
            'hero_primary_url'     => '',
            'hero_secondary_label' => 'Read More',
            'hero_secondary_url'   => '',
        ], $override);
    }

    // ---------------------------------------------------------------- public page

    public function test_landing_page_loads_with_original_content_and_links(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Our Priority')
            ->assertSee('General Consultation')
            ->assertSee('How do I book an appointment?')
            ->assertSee('Barangay Dayap, Calauan', false)
            ->assertSee('vioraTrigger', false)
            ->assertSee(route('register'), false)
            ->assertSee(route('login'), false);
    }

    public function test_hero_is_text_only_without_picture_and_picture_is_frameless_with_one(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('hero-grid is-text-only', false)
            ->assertDontSee('portal-card', false)
            ->assertDontSee('Private &amp; Secure', false)
            ->assertDontSee('hero-banner-img', false);

        WebsiteSetting::create(['key' => 'hero_image', 'value' => 'landing/hero.png']);
        WebsiteSetting::create(['key' => 'about_image', 'value' => 'landing/about.png']);
        Storage::disk('public')->put('landing/hero.png', 'x');
        Storage::disk('public')->put('landing/about.png', 'x');
        LandingPageContent::forget();

        $this->get('/')->assertOk()
            ->assertDontSee('is-text-only', false)
            ->assertSee('hero-banner-img', false)
            ->assertSee('about-img-photo', false)
            ->assertDontSee('about-img-wrap', false)
            ->assertDontSee('portal-card', false);
    }

    public function test_landing_page_works_when_nothing_is_configured(): void
    {
        LandingItem::query()->delete();
        WebsiteSetting::query()->delete();
        LandingPageContent::forget();

        // No items at all -> original content is used.
        $this->get('/')->assertOk()->assertSee('Health Checkup')->assertSee('Frequently Asked Questions');
    }

    public function test_landing_page_survives_missing_website_tables(): void
    {
        Schema::drop('landing_items');
        Schema::drop('website_settings');
        LandingPageContent::forget();

        $this->get('/')->assertOk()->assertSee('Our Priority');
    }

    // ---------------------------------------------------------------- authorization

    public function test_guests_and_non_admins_cannot_reach_management_routes(): void
    {
        $item = LandingItem::first();
        $routes = [
            ['get',    route('admin.website.index')],
            ['put',    route('admin.website.settings.update', 'hero')],
            ['post',   route('admin.website.items.store')],
            ['put',    route('admin.website.items.update', $item)],
            ['post',   route('admin.website.items.toggle', $item)],
            ['post',   route('admin.website.items.move', [$item, 'down'])],
            ['delete', route('admin.website.items.destroy', $item)],
        ];

        foreach ($routes as [$verb, $url]) {
            $this->{$verb}($url)->assertRedirect('/login');
        }

        foreach (['Patient', 'Doctor', 'Staff'] as $role) {
            $user = $this->userWithRole($role);
            foreach ($routes as [$verb, $url]) {
                $this->actingAs($user)->{$verb}($url)->assertForbidden();
            }
        }

        $this->assertDatabaseHas('landing_items', ['id' => $item->id]);
        $this->assertDatabaseCount('website_settings', 0);
    }

    public function test_admin_can_open_website_management_for_every_tab(): void
    {
        $admin = $this->admin();

        foreach (['hero', 'about', 'services', 'features', 'steps', 'workflow', 'faqs', 'contact', 'footer'] as $tab) {
            $this->actingAs($admin)->get(route('admin.website.index', ['tab' => $tab]))
                ->assertOk()->assertSee('Website Management');
        }
    }

    // ---------------------------------------------------------------- hero / settings

    public function test_admin_edits_hero_and_it_appears_publicly(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.website.settings.update', 'hero'), $this->heroPayload())
            ->assertRedirect(route('admin.website.index', ['tab' => 'hero']))
            ->assertSessionHas('success');

        $this->get('/')
            ->assertSee('Caring For You,')
            ->assertSee('Every Day')
            ->assertSee('Join Now')
            ->assertSee('A new description for the hero.')
            ->assertDontSee('Our Priority')
            // empty destinations keep the original ones
            ->assertSee('href="' . route('register') . '" class="btn-primary-hero"', false)
            ->assertSee('href="#about" class="btn-secondary-hero"', false);

        $this->assertDatabaseHas('user_logs', ['module' => 'Website Management', 'action' => 'Updated Website Content']);
    }

    public function test_hero_validation_errors_are_reported_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.website.index', ['tab' => 'hero']))
            ->put(route('admin.website.settings.update', 'hero'), $this->heroPayload([
                'hero_title_line1' => '',
                'hero_primary_url' => 'javascript:alert(1)',
            ]))
            ->assertRedirect(route('admin.website.index', ['tab' => 'hero']))
            ->assertSessionHasErrors(['hero_title_line1', 'hero_primary_url']);

        $this->assertDatabaseCount('website_settings', 0);
    }

    public function test_text_is_escaped_and_only_bold_markup_is_allowed(): void
    {
        $admin = $this->admin();
        $item = LandingItem::where('section', 'faqs')->first();

        $this->actingAs($admin)->put(route('admin.website.items.update', $item), [
            'title'     => '<script>alert(1)</script> Q?',
            'body'      => 'Plain <b>html</b> and **bold** text',
            'category'  => 'clinic',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; Q?', $html);
        $this->assertStringContainsString('Plain &lt;b&gt;html&lt;/b&gt; and <strong>bold</strong> text', $html);
    }

    public function test_contact_and_location_settings_flow_to_footer_and_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.website.settings.update', 'contact'), [
            'contact_label' => 'Reach Us', 'contact_title' => 'Talk To Us', 'contact_sub' => 'Anytime',
            'contact_address' => "1 Test St\nSome City", 'contact_phones' => "+63 900 111 2222",
            'contact_emails' => "hello@example.com",
        ])->assertSessionHasNoErrors();

        $this->put(route('admin.website.settings.update', 'location'), [
            'location_label' => 'Find Us', 'location_title' => 'Visit', 'location_sub' => '',
            'location_address' => '1 Test St', 'location_hours' => "Daily: 8 AM – 8 PM",
            'location_directions' => '', 'location_parking' => '',
            'location_map_url' => 'https://www.google.com/maps/embed?pb=!1m18!2dtest',
        ])->assertSessionHasNoErrors();

        $this->put(route('admin.website.settings.update', 'footer'), [
            'brand_name' => 'MyClinic', 'footer_description' => 'Footer text', 'footer_address' => '1 Test St',
            'footer_phone' => '+63 900 111 2222', 'footer_email' => 'hello@example.com',
            'footer_copyright_name' => 'My Clinic Inc', 'footer_privacy_url' => '/privacy', 'footer_terms_url' => '',
        ])->assertSessionHasNoErrors();

        $this->get('/')
            ->assertSee('Reach Us')
            ->assertSee('hello@example.com')
            ->assertSee('Daily: 8 AM – 8 PM')
            ->assertSee('MyClinic')
            ->assertSee('My Clinic Inc')
            ->assertSee('href="/privacy"', false)
            ->assertSee('https://www.google.com/maps/embed?pb=!1m18!2dtest', false)
            // empty directions / parking hide their cards
            ->assertDontSee('How to Get There')
            ->assertDontSee('Parking Available')
            // chatbot gets the new facts
            ->assertSee('"email":"hello@example.com"', false);
    }

    public function test_map_and_contact_validation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.website.settings.update', 'location'), [
            'location_label' => 'a', 'location_title' => 'b', 'location_address' => 'c', 'location_hours' => 'd',
            'location_map_url' => 'https://evil.example/maps/embed?x=1',
        ])->assertSessionHasErrors('location_map_url');

        $this->put(route('admin.website.settings.update', 'contact'), [
            'contact_label' => 'a', 'contact_title' => 'b', 'contact_address' => 'c',
            'contact_phones' => 'abc', 'contact_emails' => 'not-an-email',
        ])->assertSessionHasErrors(['contact_phones', 'contact_emails']);

        $this->put(route('admin.website.settings.update', 'nonsense'), [])->assertNotFound();
    }

    // ---------------------------------------------------------------- images

    public function test_admin_uploads_replaces_and_removes_hero_image(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.website.settings.update', 'hero'),
            $this->heroPayload(['hero_image' => UploadedFile::fake()->image('../../evil name.png', 600, 400)]))
            ->assertSessionHasNoErrors();

        $first = WebsiteSetting::where('key', 'hero_image')->value('value');
        $this->assertMatchesRegularExpression('#^landing/[A-Za-z0-9_\-]+\.png$#', $first);
        Storage::disk('public')->assertExists($first);
        $this->get('/')->assertSee('class="hero-banner-img"', false)->assertSee($first, false);

        $this->put(route('admin.website.settings.update', 'hero'),
            $this->heroPayload(['hero_image' => UploadedFile::fake()->image('new.jpg', 600, 400)]))
            ->assertSessionHasNoErrors();

        $second = WebsiteSetting::where('key', 'hero_image')->value('value');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);   // no orphan
        Storage::disk('public')->assertExists($second);

        $this->put(route('admin.website.settings.update', 'hero'), $this->heroPayload(['remove_hero_image' => 1]));
        Storage::disk('public')->assertMissing($second);
        $this->get('/')->assertDontSee('hero-banner-img', false);
    }

    /** A real (non-faked) upload, so MIME sniffing looks at the content, not the name. */
    private function realFileWithContent(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'wsup');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function test_unsafe_uploads_are_rejected(): void
    {
        $admin = $this->admin();
        $bad = [
            UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            $this->realFileWithContent('pic.png', '<?php echo 1; ?>'),   // PHP disguised as PNG
            UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml'),
            UploadedFile::fake()->image('huge.png')->size(5000),
        ];

        foreach ($bad as $file) {
            $this->actingAs($admin)->put(route('admin.website.settings.update', 'hero'),
                $this->heroPayload(['hero_image' => $file]))->assertSessionHasErrors('hero_image');
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('website_settings', 0);
    }

    public function test_stored_image_paths_outside_landing_dir_are_never_used_or_deleted(): void
    {
        Storage::disk('public')->put('avatars/keep.png', 'x');

        WebsiteSetting::create(['key' => 'about_image', 'value' => '../avatars/keep.png']);
        $this->get('/')->assertOk()->assertDontSee('about-img-photo', false);

        LandingPageContent::deleteImage('../avatars/keep.png');
        LandingPageContent::deleteImage('avatars/keep.png');
        Storage::disk('public')->assertExists('avatars/keep.png');
    }

    // ---------------------------------------------------------------- FAQs & items

    public function test_faq_create_edit_hide_reorder_delete(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->post(route('admin.website.items.store'), [
            'section' => 'faqs', 'title' => 'Do you take HMO?', 'body' => 'Yes, **most** HMOs.',
            'category' => 'clinic', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $faq = LandingItem::where('title', 'Do you take HMO?')->firstOrFail();
        $this->assertSame(5, LandingItem::where('section', 'faqs')->where('category', 'clinic')->count());
        $this->get('/')->assertSee('Do you take HMO?')->assertSee('Yes, <strong>most</strong> HMOs.', false);

        // edit
        $this->put(route('admin.website.items.update', $faq), [
            'title' => 'Do you accept HMO cards?', 'body' => 'Please call us.', 'category' => 'account', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->get('/')->assertSee('Do you accept HMO cards?')->assertDontSee('Do you take HMO?');
        $this->assertSame('account', $faq->fresh()->category);

        // hide
        $this->post(route('admin.website.items.toggle', $faq))->assertRedirect();
        $this->assertFalse($faq->fresh()->is_active);
        $this->get('/')->assertDontSee('Do you accept HMO cards?');
        $this->post(route('admin.website.items.toggle', $faq));
        $this->assertTrue($faq->fresh()->is_active);

        // reorder: move the new (last overall) FAQ up one place
        $before = LandingItem::section('faqs')->pluck('id')->all();
        $this->post(route('admin.website.items.move', [$faq, 'up']))->assertRedirect();
        $after = LandingItem::section('faqs')->pluck('id')->all();
        $n = count($before);
        $this->assertSame($faq->id, $after[$n - 2]);
        $this->assertSame($before[$n - 2], $after[$n - 1]);
        $this->assertSame(range(1, $n), LandingItem::section('faqs')->pluck('sort_order')->all());

        // first item cannot move up (no change, no error)
        $firstId = $after[0];
        $this->post(route('admin.website.items.move', [LandingItem::find($firstId), 'up']))->assertRedirect();
        $this->assertSame($firstId, LandingItem::section('faqs')->value('id'));

        // delete
        $this->delete(route('admin.website.items.destroy', $faq))->assertRedirect();
        $this->assertDatabaseMissing('landing_items', ['id' => $faq->id]);
        $this->get('/')->assertDontSee('Do you accept HMO cards?');
    }

    public function test_item_validation(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.website.items.store'), ['section' => 'faqs', 'title' => '', 'body' => '', 'category' => 'bogus'])
            ->assertSessionHasErrors(['title', 'body', 'category']);

        $this->post(route('admin.website.items.store'), ['section' => 'services', 'title' => 'X', 'body' => 'Y', 'icon' => '"><script>'])
            ->assertSessionHasErrors('icon');

        $this->post(route('admin.website.items.store'), ['section' => 'billing', 'title' => 'X'])->assertStatus(422);
    }

    public function test_hidden_category_hides_its_tab_and_steps_are_numbered_by_order(): void
    {
        $this->actingAs($this->admin());

        foreach (LandingItem::where('section', 'faqs')->where('category', 'records')->get() as $i) {
            $this->post(route('admin.website.items.toggle', $i));
        }
        $this->get('/')->assertDontSee('data-cat="records"', false)->assertSee('data-cat="appointments"', false);

        $first = LandingItem::section('steps')->first();
        $this->post(route('admin.website.items.toggle', $first));
        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString($first->title, $html);
        $this->assertSame(3, substr_count($html, 'step-num-badge">0'));   // 01, 02, 03
    }

    public function test_service_cards_are_independent_of_billing_services(): void
    {
        $serviceCount = Service::count();
        $billing = Service::create(['name' => 'Consultation', 'description' => 'fee', 'price' => 500, 'is_active' => true]);
        $card = LandingItem::where('section', 'services')->first();

        $this->actingAs($this->admin());
        $this->post(route('admin.website.items.toggle', $card));
        $this->delete(route('admin.website.items.destroy', $card));
        $this->put(route('admin.website.items.update', LandingItem::where('section', 'services')->first()), [
            'title' => 'Renamed Card', 'body' => 'New text', 'icon' => 'bxs-heart', 'is_active' => 1,
        ]);

        $fresh = $billing->fresh();
        $this->assertSame('Consultation', $fresh->name);
        $this->assertEquals(500, $fresh->price);
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertSame($serviceCount + 1, Service::count());
        $this->get('/')->assertSee('Renamed Card');
    }

    public function test_service_card_image_upload_and_cleanup_on_delete(): void
    {
        $this->actingAs($this->admin());
        $card = LandingItem::where('section', 'services')->first();

        $this->put(route('admin.website.items.update', $card), [
            'title' => $card->title, 'body' => $card->body, 'icon' => $card->icon, 'is_active' => 1,
            'image' => UploadedFile::fake()->image('card.webp', 200, 200),
        ])->assertSessionHasNoErrors();

        $path = $card->fresh()->image_path;
        Storage::disk('public')->assertExists($path);
        $this->get('/')->assertSee('service-photo', false);

        $this->delete(route('admin.website.items.destroy', $card));
        Storage::disk('public')->assertMissing($path);
    }

    // ---------------------------------------------------------------- cache & helpers

    public function test_cache_is_invalidated_on_every_change(): void
    {
        $this->get('/')->assertSee('Our Priority');          // warms the cache
        WebsiteSetting::updateOrCreate(['key' => 'hero_title_highlight'], ['value' => 'Fresh Words']);
        $this->get('/')->assertSee('Fresh Words')->assertDontSee('Our Priority');

        $item = LandingItem::where('section', 'workflow')->first();
        $item->update(['title' => 'Changed Step']);
        $this->get('/')->assertSee('Changed Step');
    }

    public function test_landing_text_helper(): void
    {
        $this->assertSame("a &lt;i&gt; <strong>b</strong><br>\nc", (string) LandingText::format("a <i> **b**\nc"));
        $this->assertSame('**b**', (string) LandingText::format('**b**', false));
        $this->assertTrue(LandingText::isSafeUrl('#about'));
        $this->assertTrue(LandingText::isSafeUrl('/login'));
        $this->assertTrue(LandingText::isSafeUrl('https://example.com/a?b=1'));
        $this->assertTrue(LandingText::isSafeUrl(''));
        foreach (['javascript:alert(1)', '//evil.com', 'data:text/html,x', 'ftp://x.com', 'https://x.com/a b', "/a\nb"] as $bad) {
            $this->assertFalse(LandingText::isSafeUrl($bad), $bad);
        }
    }
    public function test_copy_refresh_migration_only_replaces_untouched_default_text(): void
    {
        LandingItem::query()->delete();
        WebsiteSetting::query()->delete();

        // Previous default text, still untouched by the Admin.
        LandingItem::create([
            'section' => 'services', 'title' => 'General Consultation', 'sort_order' => 1,
            'body' => 'Sit down with a doctor to discuss your health concerns and get the guidance you need.',
        ]);
        // Same slot, but the Admin rewrote it.
        LandingItem::create([
            'section' => 'services', 'title' => 'General Consultation', 'sort_order' => 2,
            'body' => 'My own custom text.',
        ]);
        WebsiteSetting::create(['key' => 'hero_primary_label', 'value' => 'Get Started']);
        WebsiteSetting::create(['key' => 'hero_secondary_label', 'value' => 'My Custom Label']);

        $migration = require database_path('migrations/2026_10_11_100200_refresh_landing_copy.php');
        $migration->up();

        $this->assertSame(
            'Meet with a doctor to discuss your health concerns and receive appropriate medical guidance.',
            LandingItem::where('sort_order', 1)->value('body')
        );
        $this->assertSame('My own custom text.', LandingItem::where('sort_order', 2)->value('body'));
        $this->assertSame('Create an Account', WebsiteSetting::where('key', 'hero_primary_label')->value('value'));
        $this->assertSame('My Custom Label', WebsiteSetting::where('key', 'hero_secondary_label')->value('value'));
    }
}