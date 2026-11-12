<?php

namespace App\Livewire\PublicPages;

use App\Models\Setting;
use App\Support\PublicPage;
use App\Support\PublicSite;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Page Settings" for one public page (Careers → Page Settings, Support → Page Settings):
 * the banner at the top and the call-to-action band above the footer.
 * The logo, header button, footer and social links are shared and live in Administration → Public Pages.
 */
class PageSettings extends Component
{
    use WithFileUploads;

    /** careers | support. Set by the route. */
    #[Locked]
    public string $page = 'careers';

    public array $form = [];

    public $banner;

    public function mount(string $page = 'careers'): void
    {
        $this->page = $page;
        $this->form = $this->design()->stored();
    }

    public function booted(): void
    {
        Gate::authorize($this->design()->permission());
    }

    protected function design(): PublicPage
    {
        return PublicPage::for($this->page);
    }

    public function removeBanner(): void
    {
        $key = $this->page.'_banner_path';
        if ($path = setting($key)) {
            Storage::disk('local')->delete($path);
            Setting::put($key, '');
        }
        $this->reset('banner');
        $this->dispatch('toast', message: 'Banner image removed.');
    }

    public function save(): void
    {
        $on = ($this->form['cta_enabled'] ?? '0') === '1';

        $this->validate([
            'form.tagline' => ['nullable', 'string', 'max:60'],
            'form.banner_heading' => ['nullable', 'string', 'max:80'],
            'form.banner_text' => ['nullable', 'string', 'max:300'],
            'form.banner_overlay' => ['required', 'integer', 'between:0,90'],
            'form.banner_align' => ['required', Rule::in(array_keys(PublicPage::ALIGNMENTS))],
            'form.banner_height' => ['required', Rule::in(array_keys(PublicPage::HEIGHTS))],
            'form.cta_enabled' => ['required', Rule::in(['0', '1'])],
            'form.cta_heading' => [Rule::requiredIf($on), 'nullable', 'string', 'max:100'],
            'form.cta_text' => ['nullable', 'string', 'max:300'],
            'form.cta_button_label' => [Rule::requiredIf($on), 'nullable', 'string', 'max:40'],
            'form.cta_button_url' => [Rule::requiredIf($on), 'nullable', 'string', 'max:300', function (string $attribute, mixed $value, \Closure $fail) {
                if (! PublicSite::validLink($value)) {
                    $fail('Enter a full link starting with https://, a page on this site starting with /, or mailto: / tel: for email or phone.');
                }
            }],
            'form.cta_style' => ['required', Rule::in(array_keys(PublicPage::CTA_STYLES))],
            'form.cta_new_tab' => ['required', Rule::in(['0', '1'])],
            'banner' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ], [
            'banner.max' => 'The banner image must be 4 MB or smaller.',
            'form.cta_button_url.required' => 'Add the address the button should open.',
        ], [
            'form.tagline' => 'small line', 'form.banner_heading' => 'heading', 'form.banner_text' => 'description',
            'form.cta_heading' => 'heading', 'form.cta_text' => 'text', 'form.cta_button_label' => 'button text', 'form.cta_button_url' => 'button address',
        ]);

        $design = $this->design();
        foreach (PublicPage::fields() as $field) {
            $design->put($field, $this->form[$field] ?? '');
        }
        if ($this->banner) {
            $key = $this->page.'_banner_path';
            if ($old = setting($key)) {
                Storage::disk('local')->delete($old);
            }
            Setting::put($key, $this->banner->storeAs('public-pages', Str::random(40).'.'.$this->banner->extension(), 'local'));
        }
        $this->reset('banner');
        $this->form = $design->stored();

        activity('updated', $design->name().' settings updated');
        $this->dispatch('toast', message: $design->name().' saved.');
    }

    public function render()
    {
        $design = $this->design();

        return view('livewire.public-pages.page-settings', [
            'design' => $design,
            'bannerUrl' => $design->bannerUrl(),
        ])->title($design->name().' Settings');
    }
}
