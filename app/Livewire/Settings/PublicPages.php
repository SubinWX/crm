<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Support\Branding;
use App\Support\PublicPage;
use App\Support\PublicSite;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Administration → Public Pages: what the careers pages and the support portal share.
 * Logo and its link, the link and button at the top right, the footer and social media.
 */
#[Title('Public Pages')]
class PublicPages extends Component
{
    use WithFileUploads;

    public array $form = [];

    public $logo;

    public function boot(): void
    {
        Gate::authorize('admin');
    }

    public function mount(): void
    {
        $this->form = PublicSite::stored();
    }

    public function removeLogo(): void
    {
        if ($path = setting('site_logo_path')) {
            Storage::disk('local')->delete($path);
            Setting::put('site_logo_path', '');
        }
        $this->reset('logo');
        $this->dispatch('toast', message: 'Logo removed.');
    }

    public function save(): void
    {
        $link = function (string $attribute, mixed $value, \Closure $fail) {
            if (! PublicSite::validLink($value)) {
                $fail('Enter a full link starting with https://, or a page on this site starting with /.');
            }
        };
        $social = ['nullable', 'string', 'max:300', 'url:http,https'];

        $this->validate([
            'form.site_name' => ['nullable', 'string', 'max:60'],
            'form.logo_link' => ['nullable', 'string', 'max:300', $link],
            'form.nav_label' => ['nullable', 'string', 'max:40'],
            'form.nav_url' => ['nullable', 'required_with:form.nav_label', 'string', 'max:300', $link],
            'form.button_label' => ['nullable', 'string', 'max:40'],
            'form.button_url' => ['nullable', 'required_with:form.button_label', 'string', 'max:300', $link],
            'form.button_style' => ['required', Rule::in(array_keys(PublicSite::BUTTON_STYLES))],
            'form.button_shape' => ['required', Rule::in(array_keys(PublicSite::BUTTON_SHAPES))],
            'form.button_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.button_new_tab' => ['required', Rule::in(['0', '1'])],
            'form.footer_copyright' => ['nullable', 'string', 'max:200'],
            'form.terms_label' => ['nullable', 'string', 'max:40'],
            'form.terms_url' => ['nullable', 'string', 'max:300', $link],
            'form.privacy_label' => ['nullable', 'string', 'max:40'],
            'form.privacy_url' => ['nullable', 'string', 'max:300', $link],
            ...collect(array_keys(PublicSite::SOCIALS))->mapWithKeys(fn ($n) => ["form.social_{$n}" => $social])->all(),
            // An SVG logo must be a plain drawing: one carrying scripts or outside links is refused.
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024', function ($attribute, $value, $fail) {
                if (strtolower($value->getClientOriginalExtension()) === 'svg' || str_contains((string) $value->getMimeType(), 'svg')) {
                    if (! Branding::svgIsSafe((string) file_get_contents($value->getRealPath()))) {
                        $fail('This SVG contains scripts, links or embedded content. Export it as a plain SVG (shapes only) and try again.');
                    }
                }
            }],
        ], [
            'form.nav_url.required_with' => 'Add the address the link should open.',
            'form.button_url.required_with' => 'Add the address the button should open.',
            'form.button_color.regex' => 'Pick a colour, or clear the field to use your brand colour.',
            'logo.max' => 'The logo must be 1 MB or smaller.',
        ], collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace(['social_', '_url', '_'], ['', ' link', ' '], $k)])->all());

        foreach (array_keys(PublicSite::FIELDS) as $field) {
            Setting::put('site_'.$field, trim((string) ($this->form[$field] ?? '')));
        }
        if ($this->logo) {
            if ($old = setting('site_logo_path')) {
                Storage::disk('local')->delete($old);
            }
            // Keep the real extension so SVGs are stored and served as SVG.
            $extension = strtolower($this->logo->getClientOriginalExtension()) === 'svg' ? 'svg' : $this->logo->extension();
            Setting::put('site_logo_path', $this->logo->storeAs('public-pages', Str::random(40).'.'.$extension, 'local'));
        }
        $this->reset('logo');
        $this->form = PublicSite::stored();

        activity('updated', 'Public pages settings updated');
        $this->dispatch('toast', message: 'Saved. The careers pages and support portal now use these.');
    }

    public function render()
    {
        return view('livewire.settings.public-pages', [
            'logoUrl' => PublicSite::logoUrl(),
            'pages' => collect(array_keys(PublicPage::PAGES))->map(fn ($key) => PublicPage::for($key)),
        ]);
    }
}
