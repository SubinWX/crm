<?php

namespace Tests\Feature;

use App\Livewire\EmailTools\SavedSignatures;
use App\Livewire\EmailTools\SavedTemplates;
use App\Livewire\EmailTools\Signature;
use App\Livewire\EmailTools\Template;
use App\Models\EmailSignature;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Support\EmailBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EmailToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function details(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Asha Menon', 'title' => 'Creative Director', 'company' => 'Acme Studio', 'phone' => '+91 495 123 4567', 'mobile' => '+91 98765 43210',
            'email' => 'asha@acme.example', 'website' => 'www.acme.example', 'address' => "12 Beach Road\nKozhikode", 'accent' => '#0055AA',
            'photo_url' => 'https://cdn.acme.example/asha.jpg', 'logo_url' => 'https://cdn.acme.example/logo.png',
            'linkedin' => 'https://www.linkedin.com/in/asha', 'instagram' => 'instagram.com/acme',
        ];
    }

    public function test_signatures_are_table_based_escaped_and_come_in_three_layouts(): void
    {
        foreach (array_keys(EmailBuilder::SIGNATURE_LAYOUTS) as $layout) {
            $html = EmailBuilder::signature($this->details(['layout' => $layout]));

            // Laid out with tables and inline styles only: nothing email programs strip or block.
            $this->assertStringStartsWith('<table role="presentation"', $html);
            foreach (['<script', '<style', '<svg', '<div class', 'class="', 'position:', 'display:flex', 'javascript:'] as $unsupported) {
                $this->assertStringNotContainsString($unsupported, $html, "{$layout} contains {$unsupported}");
            }
            foreach (['Asha Menon', 'Creative Director', 'Acme Studio', 'href="mailto:asha@acme.example"', 'href="tel:+919876543210"', 'href="https://www.acme.example"', 'acme.example</a>',
                'href="https://www.linkedin.com/in/asha"', 'href="https://instagram.com/acme"', '#0055aa', 'https://cdn.acme.example/logo.png'] as $expected) {
                $this->assertStringContainsString($expected, $html, "{$layout} is missing {$expected}");
            }
            $this->assertStringNotContainsString('Facebook', $html); // only the networks that were filled in
        }

        // The photo appears in the layouts that have one, with fixed sizes so Outlook does not blow it up.
        $this->assertStringContainsString('<img src="https://cdn.acme.example/asha.jpg" width="88" height="88"', EmailBuilder::signature($this->details(['layout' => 'classic'])));
        $this->assertStringNotContainsString('asha.jpg', EmailBuilder::signature($this->details(['layout' => 'compact'])));
        $this->assertStringContainsString('border-top:3px solid #0055aa', EmailBuilder::signature($this->details(['layout' => 'modern'])));
        $this->assertStringContainsString('12 Beach Road, Kozhikode', EmailBuilder::signature($this->details()));

        // Showcase: larger photo with an optional colour behind it, coloured designation, logo on the right, a rule,
        // symbol-led contact lines, a starred highlight line with a link, square badges and a linked banner underneath.
        $showcase = EmailBuilder::signature($this->details([
            'layout' => 'showcase', 'photo_bg' => '#6D3DF5', 'social_style' => 'squares', 'highlight_label' => 'Rate us', 'highlight_text' => 'How happy are you with our services?',
            'highlight_url' => 'acme.example/rate', 'banner_url' => 'https://cdn.acme.example/banner.png', 'banner_link' => 'https://acme.example/offer', 'note' => 'Confidential.',
        ]));
        foreach (['width="600" style="border-collapse:collapse;width:100%;max-width:600px;', '<img src="https://cdn.acme.example/asha.jpg" width="126" height="126"', 'background-color:#6d3df5;',
            'color:#0055aa;">Creative Director</span>', 'align="right"', 'border-top:1px solid #d5d5d5', '&#9993;', '&#127760;', 'color:#f5b400;">&#9733;</td>',
            '<strong style="color:#5f6368;">Rate us</strong>', '<a href="https://acme.example/rate" target="_blank" style="color:#5f6368;text-decoration:underline;">How happy are you with our services?</a>',
            'border-radius:3px', '<a href="https://acme.example/offer" target="_blank" style="text-decoration:none;"><img src="https://cdn.acme.example/banner.png" width="600"',
            '<td colspan="2" style="padding:12px 0 0;', 'Confidential.'] as $expected) {
            $this->assertStringContainsString($expected, $showcase, "showcase is missing {$expected}");
        }
        $bare = EmailBuilder::signature($this->details(['layout' => 'showcase', 'banner_url' => '', 'highlight_label' => '', 'highlight_text' => '', 'banner_link' => 'javascript:alert(1)']));
        $this->assertStringNotContainsString('&#9733;', $bare);       // no highlight line without wording
        $this->assertStringNotContainsString('colspan="2" style="padding:18px', $bare); // no banner row without an image
        // The other layouts ignore the showcase-only fields.
        $this->assertStringNotContainsString('banner.png', EmailBuilder::signature($this->details(['layout' => 'classic', 'banner_url' => 'https://cdn.acme.example/banner.png'])));

        // Social links as badges (each network's colour, or the accent), as text, or hidden.
        $this->assertStringContainsString('bgcolor="#0a66c2"', EmailBuilder::signature($this->details()));
        $this->assertStringNotContainsString('#0a66c2', $accentOnly = EmailBuilder::signature($this->details(['social_brand_colors' => '0'])));
        $this->assertStringContainsString('bgcolor="#0055aa"', $accentOnly);
        $this->assertStringContainsString('>LinkedIn</a>', EmailBuilder::signature($this->details(['social_style' => 'text'])));
        $this->assertStringNotContainsString('linkedin.com', EmailBuilder::signature($this->details(['social_style' => 'none'])));

        // Whatever is typed is escaped, and unsafe addresses, colours and emails are dropped.
        $hostile = EmailBuilder::signature($this->details([
            'name' => '<script>alert(1)</script>Asha', 'title' => '" onmouseover="x', 'website' => 'javascript:alert(1)', 'photo_url' => 'data:text/html,<b>x</b>',
            'linkedin' => 'https://ok.example/"><img src=x onerror=alert(1)>', 'accent' => 'red;background:url(x)', 'email' => 'not-an-email', 'note' => "Line 1\n<b>bold</b>",
        ]));
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;Asha', $hostile);
        $this->assertStringContainsString('&quot; onmouseover=&quot;x', $hostile);
        $this->assertStringContainsString('Line 1<br>&lt;b&gt;bold&lt;/b&gt;', $hostile);
        foreach (['<script>', 'javascript:', 'data:text', 'onerror=alert', 'ok.example', 'url(x)', 'mailto:', '<b>bold'] as $bad) {
            $this->assertStringNotContainsString($bad, $hostile);
        }
        $this->assertSame(['https://www.example.com', 'https://example.com/a?b=1', '', ''], [EmailBuilder::url('www.example.com'), EmailBuilder::url('https://example.com/a?b=1'), EmailBuilder::url('ftp://x'), EmailBuilder::url('http://a b')]);
    }

    public function test_each_person_builds_and_saves_their_own_signature(): void
    {
        Storage::fake('local');
        Setting::put('company_name', 'Acme Studio');
        Setting::put('company_phone', '+91 495 123 4567');
        $staff = User::factory()->create(['name' => 'Ravi Kumar', 'email' => 'ravi@acme.example', 'phone' => '9876543210', 'role' => User::ROLE_STAFF, 'permissions' => []]);
        $other = User::factory()->create(['role' => User::ROLE_SUB_ADMIN]);

        // Starts from what the CRM already knows, and the preview updates as fields change.
        $page = Livewire::actingAs($staff)->test(Signature::class)->assertSet('form.name', 'Ravi Kumar')->assertSet('form.email', 'ravi@acme.example')
            ->assertSet('form.company', 'Acme Studio')->assertSet('form.layout', 'classic')->assertSee('Copy signature')->assertSee('Copy HTML code')->assertSee('Download .html');
        $page->set('form.title', 'SEO Lead')->assertViewHas('html', fn ($html) => str_contains($html, 'SEO Lead') && str_contains($html, 'Ravi Kumar'))
            ->set('form.layout', 'modern')->assertViewHas('html', fn ($html) => str_contains($html, 'border-top:3px solid'));

        // Uploading a photo cuts it square and gives it a public address the email can load.
        $page->set('photo', UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'))->assertHasErrors('photo');
        $page->set('photo', UploadedFile::fake()->image('me.png', 900, 600))->assertHasNoErrors();
        $url = $page->get('form.photo_url');
        $this->assertMatchesRegularExpression('~/e/[A-Za-z0-9]{40}\.jpg$~', $url);
        $file = 'email-assets/'.basename($url);
        Storage::disk('local')->assertExists($file);
        $this->assertSame([320, 320], array_slice(getimagesizefromstring(Storage::disk('local')->get($file)), 0, 2));
        auth()->logout();
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/jpeg'); // no sign-in needed
        $this->get('/e/'.str_repeat('a', 40).'.jpg')->assertNotFound();
        $this->get('/e/..%2F..%2F.env')->assertNotFound();
        $this->actingAs($staff);

        // A cut-out photo uploaded for the Showcase layout gets the chosen colour behind it.
        $cutout = Livewire::actingAs($staff)->test(Signature::class)->set('form.layout', 'showcase')->assertSee('Highlight line and banner')->set('form.photo_bg', '#6d3df5');
        $transparent = imagecreatetruecolor(200, 200);
        imagesavealpha($transparent, true);
        imagefill($transparent, 0, 0, imagecolorallocatealpha($transparent, 0, 0, 0, 127));
        ob_start();
        imagepng($transparent);
        $cutout->set('photo', UploadedFile::fake()->createWithContent('cutout.png', ob_get_clean()))->assertHasNoErrors();
        $pixel = imagecolorsforindex($made = imagecreatefromstring(Storage::disk('local')->get('email-assets/'.basename($cutout->get('form.photo_url')))), imagecolorat($made, 5, 5));
        $this->assertEqualsWithDelta([109, 61, 245], [$pixel['red'], $pixel['green'], $pixel['blue']], 6);
        $cutout->call('removePhoto')->set('form.highlight_url', 'javascript:x')->set('form.photo_bg', 'purple')->call('save')->assertHasErrors(['form.highlight_url', 'form.photo_bg']);
        Livewire::actingAs($staff)->test(Signature::class)->set('form.layout', 'classic')->assertDontSee('Highlight line and banner');

        // Saving needs a name and valid links; it is stored per person and reloaded next time.
        $page->set('form.name', '')->set('form.linkedin', 'not a link at all')->set('form.accent', 'blue')->call('save')->assertHasErrors(['form.name', 'form.linkedin', 'form.accent']);
        $page->set('form.name', 'Ravi Kumar')->set('form.linkedin', 'linkedin.com/in/ravi')->set('form.accent', '#aa3300')->call('save')->assertHasNoErrors();
        $first = EmailSignature::where('user_id', $staff->id)->firstOrFail();
        $this->assertSame(['My signature', 'SEO Lead', 'modern', $url], [$first->name, $first->data['title'], $first->data['layout'], $first->data['photo_url']]);

        // Several signatures can be kept as a named list: save changes, save as new, open another, delete one.
        $page->set('name', '')->call('save')->assertHasErrors('name');
        $page->set('name', 'Main')->set('form.title', 'Head of SEO')->call('save')->assertHasNoErrors();
        $this->assertSame([1, 'Main', 'Head of SEO'], [EmailSignature::count(), $first->fresh()->name, $first->fresh()->data['title']]);
        $page->set('name', 'Short reply')->set('form.layout', 'compact')->call('save', true)->assertHasNoErrors()->assertViewHas('savedCount', 2);
        $this->assertSame(['Main' => 'modern', 'Short reply' => 'compact'], EmailSignature::where('user_id', $staff->id)->orderBy('name')->get()->mapWithKeys(fn ($s) => [$s->name => $s->data['layout']])->all());
        $second = EmailSignature::where('name', 'Short reply')->firstOrFail();
        $page->call('load', $first->id)->assertSet('name', 'Main')->assertSet('form.layout', 'modern')->assertSet('loadedId', $first->id);
        $page->call('startNew')->assertSet('loadedId', null)->assertSet('name', 'My signature')->assertSet('form.title', 'Staff')->assertSet('form.photo_url', '');
        // Both saved copies share the uploaded photo, so deleting one keeps the file for the other.
        $page->call('delete', $second->id);
        $this->assertNull($second->fresh());
        Storage::disk('local')->assertExists($file);
        $page->call('load', $first->id);
        $first->refresh();
        Livewire::actingAs($staff)->test(Signature::class)->assertSet('form.title', 'Head of SEO')->assertSet('form.layout', 'modern')->assertSet('form.photo_url', $url)->assertSet('name', 'Main');
        // Other people neither see nor open it; a Super Admin sees the team's list and can open one to help a colleague.
        Livewire::actingAs($other)->test(Signature::class)->assertSet('form.name', $other->name)->assertSet('form.photo_url', '')->assertDontSee('Head of SEO')->assertViewHas('savedCount', 0)
            ->call('load', $first->id)->assertForbidden();
        Livewire::actingAs($other)->test(Signature::class)->call('delete', $first->id)->assertForbidden();
        $boss = User::factory()->superAdmin()->create();
        Livewire::actingAs($boss)->test(Signature::class)->call('load', $first->id)->assertSet('form.title', 'Head of SEO');

        // The Saved Signatures page: your own list (Super Admins also see the team's), with search, preview, copy, duplicate and delete.
        $this->actingAs($staff);
        $list = Livewire::actingAs($staff)->test(SavedSignatures::class)->assertSee('Main')->assertSee('Head of SEO')->assertSee('Modern')->assertSee('Copy signature')->assertSee('Copy HTML')
            ->assertViewHas('html', fn ($html) => str_starts_with($html[$first->id], '<table role="presentation"') && str_contains($html[$first->id], 'Ravi Kumar'));
        $list->set('search', 'zzz')->assertViewHas('signatures', fn ($rows) => $rows->isEmpty())->set('search', 'head of')->assertViewHas('signatures', fn ($rows) => $rows->pluck('name')->all() === ['Main'])->set('search', '');
        $list->call('preview', $first->id)->assertSet('previewId', $first->id)->assertSee('Open to edit')->call('closeModal')->assertSet('previewId', null);
        $list->call('duplicate', $first->id)->assertViewHas('signatures', fn ($rows) => $rows->pluck('name')->sort()->values()->all() === ['Main', 'Main (copy)']);
        $copy = EmailSignature::where('name', 'Main (copy)')->firstOrFail();
        $this->assertSame([$staff->id, $first->data['photo_url']], [$copy->user_id, $copy->data['photo_url']]);
        $list->call('delete', $copy->id);
        Storage::disk('local')->assertExists($file); // "Main" still uses the photo
        Livewire::actingAs($other)->test(SavedSignatures::class)->assertViewHas('signatures', fn ($rows) => $rows->isEmpty())->assertSee('No signatures saved yet');
        Livewire::actingAs($other)->test(SavedSignatures::class)->call('delete', $first->id)->assertForbidden();
        Livewire::actingAs($other)->test(SavedSignatures::class)->call('preview', $first->id)->assertForbidden();
        Livewire::actingAs($boss)->test(SavedSignatures::class)->assertSee('Main')->assertSee('Ravi Kumar');
        // Opening from the list loads that signature into the builder; "New signature" starts blank.
        Livewire::actingAs($staff)->withQueryParams(['open' => $first->id])->test(Signature::class)->assertSet('loadedId', $first->id)->assertSet('name', 'Main');
        Livewire::actingAs($staff)->withQueryParams(['new' => 1])->test(Signature::class)->assertSet('loadedId', null)->assertSet('name', 'My signature');
        Livewire::actingAs($other)->withQueryParams(['open' => $first->id])->test(Signature::class)->assertForbidden();
        $this->flushSession()->actingAs($staff)->get(route('email-tools.saved-signatures'))->assertOk()->assertSee('Saved Signatures')->assertSee('Main');
        $this->actingAs($staff);

        // A replaced or removed photo is deleted once no saved signature uses it any more; a pasted outside address is never touched.
        $this->actingAs($staff);
        $page->set('photo', UploadedFile::fake()->image('new.jpg', 400, 400));
        Storage::disk('local')->assertExists($file); // the saved signature still shows the old one
        $second = 'email-assets/'.basename($page->get('form.photo_url'));
        $page->call('save')->assertHasNoErrors();
        Storage::disk('local')->assertMissing($file);
        $page->call('removePhoto')->assertSet('form.photo_url', '');
        Storage::disk('local')->assertExists($second);
        $page->call('save')->assertHasNoErrors();
        Storage::disk('local')->assertMissing($second);
        $page->set('form.photo_url', 'https://cdn.acme.example/asha.jpg')->call('removePhoto')->assertSet('form.photo_url', '');

        // "Start over" refills from the profile; the page is open to every team member.
        $page->set('form.title', 'Something else')->call('startOver')->assertSet('form.title', 'Staff')->assertSet('form.name', 'Ravi Kumar');
        foreach ([$staff, $other] as $user) {
            $this->flushSession()->actingAs($user)->get(route('email-tools.signature'))->assertOk()->assertSee('Email Signatures')->assertSee('Email Templates');
        }
        auth()->logout();
        $this->flushSession()->get(route('email-tools.signature'))->assertRedirect();
    }

    public function test_email_templates_have_a_header_and_footer_and_can_be_saved_and_reused(): void
    {
        $data = [
            'layout' => 'band', 'company' => 'Acme Studio', 'accent' => '#0055aa', 'website' => 'www.acme.example', 'preheader' => 'Your design is ready',
            'heading' => 'Your first design is ready', 'body' => '<div>Hello <strong>Anita</strong>,</div><ul><li>Home page</li><li>About page</li></ul><div>See <a href="https://acme.example/preview">the preview</a>.</div><script>alert(1)</script>',
            'button_label' => 'View the design', 'button_url' => 'https://acme.example/preview', 'signoff' => "Regards,\nAsha", 'footer_text' => "Acme Studio\n12 Beach Road, Kozhikode",
            'footer_note' => 'You are receiving this because you are a client.', 'linkedin' => 'https://www.linkedin.com/company/acme',
        ];
        $html = EmailBuilder::template($data);

        // A complete, responsive email document built from tables.
        foreach (['<!DOCTYPE html>', '<meta name="viewport" content="width=device-width, initial-scale=1">', '@media only screen and (max-width:620px)', 'class="wrap" width="600"', 'role="presentation"',
            'bgcolor="#0055aa"', 'Your design is ready', '>Your first design is ready</h1>', '<strong>Anita</strong>', '<li style="margin:0 0 4px;">Home page</li>',
            '<a style="color:#0055aa;text-decoration:underline;" href="https://acme.example/preview"', '>View the design</a>', 'Regards,<br>Asha', 'Acme Studio<br>12 Beach Road, Kozhikode',
            'You are receiving this because you are a client.', 'href="https://www.linkedin.com/company/acme"', '>acme.example</a>'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        foreach (['<script>', 'alert(1)', '<svg', 'javascript:'] as $bad) {
            $this->assertStringNotContainsString($bad, $html);
        }

        // Layouts: a white header with a coloured line, a solid band, or a band with a wide picture. A logo replaces the name.
        $this->assertStringContainsString('border-bottom:3px solid #0055aa', EmailBuilder::template(['layout' => 'clean'] + $data));
        $hero = EmailBuilder::template(['layout' => 'hero', 'logo_url' => 'https://cdn.acme.example/logo.png'] + $data);
        $this->assertStringContainsString('<img src="https://cdn.acme.example/logo.png" height="40" alt="Acme Studio"', $hero);

        // Banner image: a plain full-width <img> on any layout, optionally a link, placed under the header, after the message or above the footer.
        $banner = ['hero_url' => 'https://cdn.acme.example/banner.jpg', 'banner_alt' => 'Festive "offer"', 'hero_link' => 'acme.example/offer'];
        $this->assertStringNotContainsString('<img', $html); // none until an address is given
        foreach (array_keys(EmailBuilder::TEMPLATE_LAYOUTS) as $layout) {
            $withBanner = EmailBuilder::template(['layout' => $layout] + $banner + $data);
            $this->assertStringContainsString('<tr><td style="padding:0;"><a href="https://acme.example/offer" target="_blank" style="text-decoration:none;"><img src="https://cdn.acme.example/banner.jpg" width="600" alt="Festive &quot;offer&quot;" style="display:block;width:100%;max-width:600px;height:auto;border:0;"></a></td></tr>', $withBanner);
        }
        $order = fn (string $position) => (function (string $h) {
            return [strpos($h, 'banner.jpg') < strpos($h, 'Your first design is ready</h1>'), strpos($h, 'banner.jpg') < strpos($h, 'You are receiving this')];
        })(EmailBuilder::template(['banner_position' => $position] + $banner + $data));
        $this->assertSame([[true, true], [false, true], [false, true]], [$order('top'), $order('middle'), $order('bottom')]);
        $this->assertStringNotContainsString('<a href="https://acme.example/offer"', EmailBuilder::template(['hero_link' => ''] + $banner + $data));
        $this->assertStringNotContainsString('<img', EmailBuilder::template(['hero_url' => 'javascript:alert(1)'] + $data));

        // Footer: company name, then the address line by line, then phone and email as links.
        $footer = EmailBuilder::template(['footer_text' => 'Acme Studio', 'footer_address' => "12 Beach Road\nKozhikode 673001", 'footer_phone' => '+91 495 123 4567', 'footer_email' => 'hello@acme.example'] + $data);
        foreach (['font-weight:bold;color:#57534e;">Acme Studio</p>', '>12 Beach Road<br>Kozhikode 673001</p>', '<a href="tel:+914951234567"', '<a href="mailto:hello@acme.example"'] as $expected) {
            $this->assertStringContainsString($expected, $footer);
        }
        $this->assertStringNotContainsString('mailto:', EmailBuilder::template(['footer_email' => 'not-an-email'] + $data));
        $this->assertStringNotContainsString('View the design', EmailBuilder::template(['button_url' => 'javascript:alert(1)'] + $data));

        // The screen: starts with a filled-in example, saves by name, and shares saved templates with the team.
        $admin = User::factory()->superAdmin()->create();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => []]);
        Setting::put('company_name', 'Acme Studio');

        $page = Livewire::actingAs($staff)->test(Template::class)->assertSet('form.company', 'Acme Studio')->assertSee('Copy email')->assertSee('Copy HTML code')
            ->assertViewHas('html', fn ($html) => str_contains($html, '<!DOCTYPE html>') && str_contains($html, 'Acme Studio'));
        $page->assertSee('Banner image address')->assertSee('Address')->set('form.footer_phone', 'call me')->set('form.footer_email', 'nope')->set('form.hero_link', 'javascript:x')->set('name', 'x')
            ->call('save')->assertHasErrors(['form.footer_phone', 'form.footer_email', 'form.hero_link']);
        $page->set('form.footer_phone', '+91 495 123 4567')->set('form.footer_email', 'hello@acme.example')->set('form.hero_link', '')->set('name', '');
        $page->call('save')->assertHasErrors('name');
        $page->set('name', 'Project update')->set('form.heading', 'Update on your project')->set('form.button_label', 'Open')->call('save')->assertHasErrors('form.button_url');
        $page->set('form.button_url', 'acme.example/project')->set('form.body', '<div>Progress this week.</div><iframe src="https://x.example"></iframe>')->call('save')->assertHasNoErrors();
        $template = EmailTemplate::firstOrFail();
        $this->assertSame(['Project update', $staff->id, 'Update on your project', '<div>Progress this week.</div>'], [$template->name, $template->created_by, $template->data['heading'], $template->data['body']]);

        // Saving again updates it; "Save as new" makes a copy; another team member can open it.
        $page->set('form.heading', 'Second update')->call('save')->assertHasNoErrors();
        $this->assertSame([1, 'Second update'], [EmailTemplate::count(), $template->fresh()->data['heading']]);
        $page->set('name', 'Monthly newsletter')->call('save', true);
        $this->assertSame(['Monthly newsletter', 'Project update'], EmailTemplate::orderBy('name')->pluck('name')->all());

        // The Saved Email Templates page lists them for the whole team, with search, preview, duplicate and copy buttons.
        $list = Livewire::actingAs($admin)->test(SavedTemplates::class)->assertSee('Project update')->assertSee('Monthly newsletter')->assertSee('Second update')->assertSee('Copy email')
            ->assertViewHas('html', fn ($html) => str_contains($html[$template->id], '<!DOCTYPE html>') && str_contains($html[$template->id], 'Second update'));
        $list->set('search', 'newsletter')->assertViewHas('templates', fn ($rows) => $rows->pluck('name')->all() === ['Monthly newsletter'])->set('search', '');
        $list->set('search', 'second UPDATE')->assertViewHas('templates', fn ($rows) => $rows->count() === 2)->set('search', '');
        $list->call('preview', $template->id)->assertSet('previewId', $template->id)->assertSee('Open to edit')->call('closeModal');
        $list->call('duplicate', $template->id);
        $this->assertSame($admin->id, EmailTemplate::where('name', 'Project update (copy)')->firstOrFail()->created_by);
        EmailTemplate::where('name', 'Project update (copy)')->delete();
        Livewire::actingAs($admin)->withQueryParams(['open' => $template->id])->test(Template::class)->assertSet('loadedId', $template->id)->assertSet('name', 'Project update');

        $adminPage = Livewire::actingAs($admin)->withQueryParams(['open' => ''])->test(Template::class)->assertViewHas('savedCount', 2)
            ->call('load', $template->id)->assertSet('name', 'Project update')->assertSet('form.heading', 'Second update')
            ->assertViewHas('html', fn ($html) => str_contains($html, 'Second update') && str_contains($html, 'https://acme.example/project'));
        $adminPage->call('startNew')->assertSet('name', '')->assertSet('loadedId', null)->assertSet('form.heading', 'Hello,');

        // Only the person who made a template, or a Super Admin, can delete it.
        $outsider = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => []]);
        Livewire::actingAs($outsider)->test(SavedTemplates::class)->assertSee('Project update')->call('delete', $template->id)->assertForbidden();
        $this->assertNotNull($template->fresh());
        Livewire::actingAs($admin)->test(SavedTemplates::class)->call('delete', $template->id);
        $this->assertNull($template->fresh());
        $this->flushSession()->actingAs($staff)->get(route('email-tools.saved-templates'))->assertOk()->assertSee('Saved Email Templates')->assertSee('Monthly newsletter');
        $this->actingAs($staff)->get(route('email-tools.templates'))->assertOk()->assertSee('Saved templates (1)');
    }
}
