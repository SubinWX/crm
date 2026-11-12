<?php

namespace Tests\Feature;

use App\Livewire\PublicPages\PageSettings;
use App\Livewire\Settings\PublicPages;
use App\Models\JobVacancy;
use App\Models\Setting;
use App\Models\User;
use App\Support\PublicPage;
use App\Support\PublicSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->superAdmin()->create();
        JobVacancy::create(['title' => 'Laravel Developer', 'slug' => 'laravel-developer', 'status' => 'open', 'description' => 'Build and maintain client web applications.']);
    }

    public function test_the_logo_header_footer_and_social_links_are_shared_by_every_public_page(): void
    {
        // Before anything is customised both pages are complete, with their own wording and no extras.
        $this->get('/careers')->assertOk()->assertSee('Open positions')->assertSee('All rights reserved.')->assertDontSee('aria-label="LinkedIn"', false);
        $this->get('/support')->assertOk()->assertSee('How can we help with your project?')->assertSee('Support Portal')->assertSee('Create an account')
            ->assertSee('All rights reserved.')->assertDontSee('Terms &amp; Conditions', false);
        $this->get('/site/logo')->assertNotFound();

        $settings = Livewire::actingAs($this->admin)->test(PublicPages::class)->assertSee('Careers page')->assertSee('Support portal');

        // Unsafe links, a link or button without an address, a bad colour and an unsafe SVG are refused.
        $settings->set('form.logo_link', 'javascript:alert(1)')->set('form.nav_label', 'About us')->set('form.button_label', 'Visit website')->set('form.button_color', 'red')
            ->set('form.social_linkedin', 'not a link')->call('save')->assertHasErrors(['form.logo_link', 'form.nav_url', 'form.button_url', 'form.button_color', 'form.social_linkedin']);
        $settings->set('logo', UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))->call('save')->assertHasErrors('logo');
        $settings->set('logo', UploadedFile::fake()->create('logo.pdf', 50, 'application/pdf'))->call('save')->assertHasErrors('logo');

        $settings->set('form.logo_link', 'https://acme.example')->set('form.site_name', 'Acme Studio')->set('form.nav_url', 'https://acme.example/about')
            ->set('form.button_url', 'https://acme.example/contact')->set('form.button_style', 'outline')->set('form.button_shape', 'pill')->set('form.button_color', '#0055AA')
            ->set('form.footer_copyright', '© Acme Studio Pvt Ltd')->set('form.terms_url', 'https://acme.example/terms')->set('form.privacy_url', '/privacy')
            ->set('form.social_linkedin', 'https://www.linkedin.com/company/acme')->set('form.social_instagram', 'https://instagram.com/acme')
            ->set('logo', UploadedFile::fake()->image('logo.png', 300, 80))->call('save')->assertHasNoErrors();

        Storage::disk('local')->assertExists(setting('site_logo_path'));
        $this->assertSame('border:1.5px solid #0055aa;color:#0055aa;background:transparent;border-radius:9999px;', PublicSite::button()['style']);

        // Careers pages, the support portal home and its sign-in page all carry the same header and footer.
        auth()->logout();
        foreach (['/careers', '/careers/laravel-developer', '/support', '/support/login', '/support/register'] as $url) {
            $this->get($url)->assertOk()->assertSee('/site/logo', false)->assertSee('href="https://acme.example"', false)
                ->assertSee('About us')->assertSee('Visit website')->assertSee('href="https://acme.example/contact"', false)
                ->assertSee('© Acme Studio Pvt Ltd')->assertSee('Terms &amp; Conditions', false)->assertSee('Privacy Policy')
                ->assertSee('aria-label="LinkedIn"', false)->assertSee('aria-label="Instagram"', false)->assertDontSee('aria-label="Facebook"', false);
        }
        $this->get('/site/logo')->assertOk();
        // Each page keeps its own links next to the shared ones.
        $this->get('/careers')->assertSee('Open positions')->assertDontSee('Create account');
        $this->get('/support')->assertSee('Create account')->assertSee('Sign in');

        // Removing the logo and clearing the button go back to the defaults everywhere.
        $old = setting('site_logo_path');
        Livewire::actingAs($this->admin)->test(PublicPages::class)->assertSet('form.site_name', 'Acme Studio')->call('removeLogo')
            ->set('form.button_label', '')->set('form.button_url', '')->call('save')->assertHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $this->assertNull(PublicSite::button());
        auth()->logout();
        $this->get('/support')->assertOk()->assertDontSee('/site/logo', false)->assertDontSee('Visit website')->assertSee('Acme Studio');

        // Only Super Admins change what is shared.
        $careersStaff = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['careers.manage', 'support.manage']]);
        $this->actingAs($careersStaff)->get(route('settings.public-pages'))->assertForbidden();
        Livewire::actingAs($careersStaff)->test(PublicPages::class)->assertForbidden();
        $this->actingAs($this->admin)->get(route('settings.public-pages'))->assertOk()->assertSee('Public Pages');
    }

    public function test_each_page_has_its_own_banner_and_bottom_call_to_action(): void
    {
        foreach (['careers' => ['/careers', 'Careers at Acme'], 'support' => ['/support', 'We are here to help']] as $page => [$url, $heading]) {
            $design = PublicPage::for($page);
            $this->assertNull($design->cta());
            $this->get("/site/banner/{$page}")->assertNotFound();

            $settings = Livewire::actingAs($this->admin)->test(PageSettings::class, ['page' => $page])->assertSee($design->name().' Settings')
                ->assertSet('form.banner_heading', $design->default('banner_heading'))->assertSet('form.cta_enabled', '0');

            // Switching the call-to-action on needs a heading, button text and a safe address; the banner must be an image.
            $settings->set('form.cta_enabled', '1')->set('form.cta_heading', '')->set('form.cta_button_label', '')->call('save')->assertHasErrors(['form.cta_heading', 'form.cta_button_label', 'form.cta_button_url']);
            $settings->set('form.cta_heading', 'Start a project')->set('form.cta_button_label', 'Talk to us')->set('form.cta_button_url', 'javascript:alert(1)')
                ->set('banner', UploadedFile::fake()->create('banner.pdf', 100, 'application/pdf'))->call('save')->assertHasErrors(['form.cta_button_url', 'banner']);

            $settings->set('form.banner_heading', $heading)->set('form.banner_text', "Banner text for {$page}.")->set('form.tagline', ucfirst($page).' desk')
                ->set('form.banner_overlay', '70')->set('form.banner_align', 'center')->set('form.banner_height', 'lg')
                ->set('form.cta_text', "Closing line for {$page}.")->set('form.cta_button_url', 'mailto:hello@acme.example')->set('form.cta_style', 'dark')
                ->set('banner', UploadedFile::fake()->image('team.jpg', 1600, 500))->call('save')->assertHasNoErrors();

            Storage::disk('local')->assertExists(setting("{$page}_banner_path"));
            auth()->logout();
            $this->get($url)->assertOk()->assertSee($heading)->assertSee("Banner text for {$page}.")->assertSee(ucfirst($page).' desk')
                ->assertSee("/site/banner/{$page}", false)->assertSee('rgb(12 18 16 / 0.7)', false)
                ->assertSee('Start a project')->assertSee("Closing line for {$page}.")->assertSee('Talk to us')->assertSee('href="mailto:hello@acme.example"', false)->assertSee('bg-ink-900', false);
            $this->get("/site/banner/{$page}")->assertOk();
        }
        $this->get('/site/banner/passwords')->assertNotFound();

        // One page's settings never leak into the other.
        $this->get('/careers')->assertSee('Careers at Acme')->assertDontSee('We are here to help')->assertDontSee('Closing line for support.');
        $this->get('/support')->assertSee('We are here to help')->assertDontSee('Careers at Acme');
        // A job's own page uses the job title in the banner; the support sign-in page has no banner or call-to-action.
        $this->get('/careers/laravel-developer')->assertOk()->assertSee('Laravel Developer')->assertSee('/site/banner/careers', false)->assertSee('Start a project');
        $this->get('/support/login')->assertOk()->assertDontSee('/site/banner/support', false)->assertDontSee('Start a project');

        // Clearing optional lines hides them; clearing the heading brings the default back; switching the band off removes it.
        Livewire::actingAs($this->admin)->test(PageSettings::class, ['page' => 'support'])->assertSet('form.banner_heading', 'We are here to help')
            ->call('removeBanner')->set('form.banner_heading', '')->set('form.banner_text', '')->set('form.tagline', '')->set('form.cta_enabled', '0')->call('save')->assertHasNoErrors()
            ->assertSet('form.banner_heading', 'How can we help with your project?')->assertSet('form.banner_text', '');
        $this->assertSame(PublicPage::HIDDEN, DB::table('settings')->where('key', 'support_banner_text')->value('value'));
        auth()->logout();
        $this->get('/support')->assertOk()->assertSee('How can we help with your project?')->assertDontSee('Banner text for support.')->assertDontSee('Support desk')
            ->assertDontSee('/site/banner/support', false)->assertDontSee('Start a project');
        $this->get('/careers')->assertSee('Start a project'); // untouched

        // Each page's settings need that area's permission.
        $careersOnly = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['careers.manage']]);
        $this->actingAs($careersOnly)->get(route('careers.settings'))->assertOk()->assertSee('Careers page Settings');
        $this->actingAs($careersOnly)->get(route('support.settings'))->assertForbidden();
        Livewire::actingAs($careersOnly)->test(PageSettings::class, ['page' => 'support'])->assertForbidden();
        $this->actingAs($this->admin)->get(route('support.settings'))->assertOk()->assertSee('Support portal Settings');
    }

    public function test_settings_saved_for_the_careers_page_earlier_are_carried_over_to_the_shared_ones(): void
    {
        foreach (['careers_button_label' => 'Visit us', 'careers_button_url' => 'https://old.example', 'careers_social_youtube' => 'https://youtube.com/@old', 'careers_logo_path' => 'careers/old-logo.png', 'careers_banner_heading' => 'Old heading', 'careers_nav_label' => 'Open roles'] as $key => $value) {
            DB::table('settings')->insert(['key' => $key, 'value' => $value]);
        }
        Storage::disk('local')->put('careers/old-logo.png', 'png');

        (require database_path('migrations/2026_11_12_160000_move_careers_design_to_public_site_settings.php'))->up();
        Setting::flush();

        $this->assertSame(['Visit us', 'https://old.example'], [PublicSite::button()['label'], PublicSite::button()['url']]);
        $this->assertSame(['youtube' => 'https://youtube.com/@old'], PublicSite::socials());
        $this->assertNotNull(PublicSite::logoUrl());
        $this->assertSame('Old heading', PublicPage::for('careers')->get('banner_heading')); // page-specific settings stay with the page
        $this->assertSame(0, DB::table('settings')->whereIn('key', ['careers_button_label', 'careers_logo_path', 'careers_nav_label'])->count());
    }
}
