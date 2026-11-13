<?php

namespace Tests\Feature;

use App\Livewire\Notifications\Bell;
use App\Livewire\Notifications\Center;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ClientAdded;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingAndHelpTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_app_name_colours_logo_and_favicon_are_changed_from_settings(): void
    {
        // Defaults: the built-in colours, no logo, the company name as the app name.
        $this->assertSame('Agency CRM', app_name());
        $this->assertSame(Branding::DEFAULT_BRAND, Branding::brand());
        $this->get(route('branding.asset', 'logo'))->assertNotFound();

        Livewire::actingAs($this->admin)->test(SettingsIndex::class)->call('setTab', 'branding')
            ->set('branding.app_name', 'WX Desk')
            ->call('applyPreset', 'Indigo')->assertSet('branding.brand_color', '#4338ca')->assertSet('branding.sidebar_color', '#1e1b4b')
            ->set('logo', UploadedFile::fake()->image('logo.png', 256, 256))
            ->set('favicon', UploadedFile::fake()->image('favicon.png', 64, 64))
            ->call('saveBranding')->assertHasNoErrors()->assertRedirect();
        Setting::flush();

        $this->assertSame('WX Desk', app_name());
        $this->assertSame('#4338ca', Branding::brand());
        $this->assertSame('#4338ca', Branding::palette()['--color-brand-600']);
        $this->assertSame('#1e1b4b', Branding::palette()['--color-ink-900']);

        // The new name, colours and images reach the app, both sign-in pages and the public support portal.
        foreach ([route('dashboard'), route('settings.index'), route('help')] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('WX Desk')->assertSee('--color-brand-600: #4338ca', false)
                ->assertSee('--color-ink-900: #1e1b4b', false)->assertSee('rel="icon"', false)->assertSee('/branding/logo', false);
        }
        auth()->logout();
        foreach ([route('login'), route('staff.login'), route('portal.home'), route('portal.login')] as $url) {
            $this->get($url)->assertOk()->assertSee('WX Desk')->assertSee('--color-brand-600: #4338ca', false)->assertSee('/branding/logo', false);
        }
        // Logo and favicon are public (needed before sign-in); nothing else is served from there.
        $this->get(route('branding.asset', 'logo'))->assertOk()->assertHeader('content-type', 'image/png');
        $this->get(route('branding.asset', 'favicon'))->assertOk();
        $this->get('/branding/invoices')->assertNotFound();

        // PDFs pick up the colour and logo too. Logos are scaled to fit a fixed box whatever their shape.
        Storage::disk('local')->put('branding/wide.png', (function () {
            $image = imagecreatetruecolor(1400, 200);
            ob_start();
            imagepng($image);

            return ob_get_clean();
        })());
        $wide = Branding::pdfImage('branding/wide.png', 190, 44);
        $this->assertSame([190, 27], [$wide['width'], $wide['height']]);          // a wide wordmark is limited by width
        $square = Branding::pdfLogo();
        $this->assertSame([44, 44], [$square['width'], $square['height']]);       // a square mark is limited by height
        $this->assertNull(Branding::pdfImage('missing.png', 100, 100));
        $this->assertNotNull(Branding::logoDataUri());
        $client = Client::create(['name' => 'Acme', 'logo_path' => 'branding/wide.png']);
        $invoice = Invoice::create(['number' => 'INV-B-1', 'client_id' => $client->id, 'invoice_date' => '2026-11-01', 'total' => 100]);
        $html = view('pdf.invoice', ['invoice' => $invoice->load(['client', 'items'])])->render();
        $this->assertStringContainsString(Branding::shade(700), $html);
        $this->assertSame(2, substr_count($html, '<img src="data:image/png;base64,'));   // company logo and client logo
        $this->assertStringContainsString('width: 110px; height: 16px', $html);         // client logo fitted to its smaller box
        $this->actingAs($this->admin)->get(route('invoices.pdf', $invoice))->assertOk();

        // A separate square icon is used next to the app name (and as the browser-tab icon when no favicon is set);
        // the full logo stays for documents.
        Livewire::actingAs($this->admin)->test(SettingsIndex::class)->set('icon', UploadedFile::fake()->image('icon.png', 128, 128))->call('saveBranding')->assertHasNoErrors();
        Setting::flush();
        $this->assertStringContainsString('/branding/icon', Branding::iconUrl());
        $this->assertStringContainsString('/branding/logo', Branding::logoUrl());
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('/branding/icon', false);
        $this->get(route('branding.asset', 'icon'))->assertOk();
        Livewire::actingAs($this->admin)->test(SettingsIndex::class)->call('removeBrandFile', 'favicon');
        Setting::flush();
        $this->assertStringContainsString('/branding/icon', Branding::faviconUrl());
        Livewire::actingAs($this->admin)->test(SettingsIndex::class)->call('removeBrandFile', 'icon');
        Setting::flush();
        $this->assertStringContainsString('/branding/logo', Branding::iconUrl());   // back to the logo

        // Colours that would make white text unreadable are refused.
        Livewire::actingAs($this->admin)->test(SettingsIndex::class)->set('branding.brand_color', '#ffe98a')->call('saveBranding')->assertHasErrors('branding.brand_color');
        Livewire::actingAs($this->admin)->test(SettingsIndex::class)->set('branding.sidebar_color', '#e5e7eb')->call('saveBranding')->assertHasErrors('branding.sidebar_color');
        Livewire::actingAs($this->admin)->test(SettingsIndex::class)->set('branding.brand_color', 'red')->call('saveBranding')->assertHasErrors('branding.brand_color');
        // SVG is accepted for the logo, icon and favicon when it is a plain drawing…
        $plain = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g"><stop offset="0" stop-color="#4338ca"/></linearGradient></defs><rect width="64" height="64" rx="12" fill="url(#g)"/><text x="32" y="42" text-anchor="middle" fill="#fff">C</text></svg>';
        Livewire::actingAs($this->admin)->test(SettingsIndex::class)
            ->set('icon', UploadedFile::fake()->createWithContent('icon.svg', $plain))->set('logo', UploadedFile::fake()->createWithContent('logo.svg', $plain))
            ->set('favicon', UploadedFile::fake()->createWithContent('favicon.svg', $plain))->call('saveBranding')->assertHasNoErrors();
        Setting::flush();
        $this->assertStringEndsWith('.svg', setting('icon_path'));
        $served = $this->get(route('branding.asset', 'icon'))->assertOk()->assertHeader('content-type', 'image/svg+xml');
        $this->assertStringContainsString('sandbox', $served->headers->get('content-security-policy'));
        $this->get(route('branding.asset', 'favicon'))->assertOk()->assertHeader('content-type', 'image/svg+xml');
        // …and an SVG logo still goes onto PDFs, sized from its viewBox.
        $this->assertSame([44, 44], [Branding::pdfLogo()['width'], Branding::pdfLogo()['height']]);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', Branding::pdfLogo()['src']);
        $this->assertStringStartsWith('%PDF', $this->actingAs($this->admin)->get(route('invoices.pdf', $invoice))->assertOk()->getContent());

        // …but not when it carries scripts, event handlers, embedded pages or outside links.
        foreach ([
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><rect width="1" height="1"/></a></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><iframe src="https://example.test"/></foreignObject></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.test/track.png"/></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(https://example.test/x.css);</style></svg>',
            'not an svg at all',
        ] as $bad) {
            $this->assertFalse(Branding::svgIsSafe($bad), $bad);
            Livewire::actingAs($this->admin)->test(SettingsIndex::class)->set('icon', UploadedFile::fake()->createWithContent('icon.svg', $bad))->call('saveBranding')->assertHasErrors('icon');
        }
        $this->assertTrue(Branding::svgIsSafe($plain));
        foreach (['logo', 'icon', 'favicon'] as $kind) {
            Livewire::actingAs($this->admin)->test(SettingsIndex::class)->call('removeBrandFile', $kind);
        }
        Setting::flush();
        Setting::flush();
        $this->assertSame('#4338ca', Branding::brand());

        // Removing the logo and clearing the name falls back to the defaults.
        Livewire::actingAs($this->admin)->test(SettingsIndex::class)->call('removeBrandFile', 'logo')->set('branding.app_name', '')->call('saveBranding')->assertHasNoErrors();
        Setting::flush();
        $this->assertNull(Branding::logoUrl());
        $this->assertSame('Agency CRM', app_name());

        // Only Super Admins can change branding.
        Livewire::actingAs(User::factory()->staff(['clients.manage'])->create())->test(SettingsIndex::class)->assertForbidden();
    }

    public function test_sidebar_menus_and_help_page_follow_the_role(): void
    {
        $staff = User::factory()->staff(['clients.view'])->create();
        $sub = User::factory()->create(['role' => User::ROLE_SUB_ADMIN, 'permissions' => null]);

        // Super Admin: every main menu with its sub-menu, plus the Help link.
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Dashboard', 'crm-nav-clients', 'All Clients', 'crm-nav-projects', 'Projects &amp; Services', 'crm-nav-office', 'Sales', 'Support', 'Finance', 'Administration', 'Help &amp; Tutorial'], false)
            ->assertSee('aria-controls="crm-nav-finance"', false)->assertSee('Quotes &amp; Proposals', false);

        // Staff without sales or finance do not get those menus at all.
        $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertSee('crm-nav-projects', false)
            ->assertDontSee('crm-nav-finance', false)->assertDontSee('crm-nav-sales', false)->assertDontSee('crm-nav-administration', false);

        // The help page explains only what the reader can use.
        $this->actingAs($this->admin)->get(route('help'))->assertOk()
            ->assertSee('Getting started')->assertSee('Leads, quotes and proposals')->assertSee('Invoices, payments and expenses')->assertSee('Users, roles and settings')->assertSee(route('staff.login'));
        $this->actingAs($staff)->get(route('help'))->assertOk()->assertSee('Clients')->assertSee('Tasks and follow-ups')
            ->assertDontSee('Invoices, payments and expenses')->assertDontSee('Leads, quotes and proposals')->assertDontSee('Users, roles and settings');
        $this->actingAs($sub)->get(route('help'))->assertOk()->assertSee('Getting started')->assertSee('Clients')->assertDontSee('Tasks and follow-ups')->assertDontSee('Hosting and renewals');

        auth()->logout();
        $this->get(route('help'))->assertRedirect(route('login'));
    }

    public function test_notifications_can_be_marked_important_and_deleted(): void
    {
        $other = User::factory()->superAdmin()->create();
        foreach ([$this->admin, $other] as $user) {
            foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
                $user->notify(new ClientAdded(Client::create(['name' => $name.' '.$user->id]), 'Tester'));
            }
        }
        [$first, $second, $third] = $this->admin->notifications()->reorder()->orderBy('created_at')->orderBy('id')->get()->all();
        $center = Center::class;

        // Mark one as important: it is flagged, listed first, and can be filtered.
        Livewire::actingAs($this->admin)->test($center)->call('toggleImportant', $first->id)->assertSee('Important');
        $this->assertTrue((bool) $first->fresh()->is_important);
        $this->assertSame($first->id, Livewire::actingAs($this->admin)->test($center)->viewData('notifications')->first()->id);
        Livewire::actingAs($this->admin)->test($center)->set('importantOnly', true)->assertSee($first->data['title'])->assertDontSee($second->data['title'])->assertDontSee($third->data['title']);
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Important');   // shown in the bell too

        // Delete a single notification.
        Livewire::actingAs($this->admin)->test($center)->call('delete', $second->id);
        $this->assertNull($second->fresh());
        $this->assertSame(2, $this->admin->notifications()->count());

        // "Delete read" removes read ones but keeps unread and important ones.
        $first->markAsRead();
        Livewire::actingAs($this->admin)->test($center)->call('deleteRead');
        $this->assertSame(2, $this->admin->notifications()->count());          // important (read) + unread both kept
        $third->markAsRead();
        Livewire::actingAs($this->admin)->test($center)->call('deleteRead');
        $this->assertSame([$first->id], $this->admin->notifications()->pluck('id')->all());
        Livewire::actingAs($this->admin)->test($center)->call('toggleImportant', $first->id);
        $this->assertFalse((bool) $first->fresh()->is_important);

        // The bell dropdown can delete too, without opening the Notification Center.
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('Delete notification:', false);
        Livewire::actingAs($this->admin)->test(Bell::class)->call('delete', $first->id)->assertSee('all caught up');
        $this->assertSame(0, $this->admin->notifications()->count());
        Livewire::actingAs($this->admin)->test(Bell::class)->call('delete', $other->notifications()->first()->id)->assertNotFound();
        $first = $this->admin->notifications()->first();   // none left; the checks below use the other person's

        // Nobody can touch another person's notifications.
        $theirs = $other->notifications()->first();
        Livewire::actingAs($this->admin)->test($center)->call('delete', $theirs->id)->assertNotFound();
        Livewire::actingAs($this->admin)->test($center)->call('toggleImportant', $theirs->id)->assertNotFound();
        $this->assertSame(3, $other->notifications()->count());
    }
}
