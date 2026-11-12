<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Support\Branding;
use App\Support\PdfDesign;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Administration → PDF Settings: the logo, header, footer, border and colour used on every PDF. */
#[Title('PDF Settings')]
class PdfSettings extends Component
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
        $this->form = PdfDesign::stored();
    }

    public function removeLogo(): void
    {
        if ($path = setting('pdf_logo_path')) {
            Storage::disk('local')->delete($path);
            Setting::put('pdf_logo_path', '');
        }
        $this->reset('logo');
        $this->dispatch('toast', message: 'PDF logo removed. PDFs use the app logo again.');
    }

    public function save(): void
    {
        $this->validate([
            'form.header_style' => ['required', Rule::in(array_keys(PdfDesign::HEADER_STYLES))],
            'form.header_title' => ['nullable', 'string', 'max:80'],
            'form.header_tagline' => ['nullable', 'string', 'max:80'],
            'form.header_details' => ['nullable', 'string', 'max:400', function ($attribute, $value, $fail) {
                if (count(preg_split('/\r\n|\r|\n/', trim((string) $value))) > 4) {
                    $fail('Use at most four lines so the header fits.');
                }
            }],
            'form.footer_text' => ['nullable', 'string', 'max:300', function ($attribute, $value, $fail) {
                if (count(preg_split('/\r\n|\r|\n/', trim((string) $value))) > 2) {
                    $fail('Use at most two lines so the footer fits.');
                }
            }],
            'form.footer_contact' => ['nullable', 'string', 'max:160'],
            'form.page_numbers' => ['required', Rule::in(['0', '1'])],
            'form.page_border' => ['required', Rule::in(['0', '1'])],
            'form.table_style' => ['required', Rule::in(array_keys(PdfDesign::TABLE_STYLES))],
            'form.accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // PNG or JPG print most reliably; an SVG must be a plain drawing.
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:1024', function ($attribute, $value, $fail) {
                if (strtolower($value->getClientOriginalExtension()) === 'svg' || str_contains((string) $value->getMimeType(), 'svg')) {
                    if (! Branding::svgIsSafe((string) file_get_contents($value->getRealPath()))) {
                        $fail('This SVG contains scripts, links or embedded content. Export it as a plain SVG (shapes only) and try again.');
                    }
                }
            }],
        ], [
            'form.accent_color.regex' => 'Pick a colour, or clear the field to use your brand colour.',
            'logo.max' => 'The logo must be 1 MB or smaller.',
        ], collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace('_', ' ', $k)])->all());

        foreach (array_keys(PdfDesign::FIELDS) as $field) {
            Setting::put('pdf_'.$field, trim((string) ($this->form[$field] ?? '')));
        }
        if ($this->logo) {
            if ($old = setting('pdf_logo_path')) {
                Storage::disk('local')->delete($old);
            }
            $extension = strtolower($this->logo->getClientOriginalExtension()) === 'svg' ? 'svg' : $this->logo->extension();
            Setting::put('pdf_logo_path', $this->logo->storeAs('branding', Str::random(40).'.'.$extension, 'local'));
        }
        $this->reset('logo');
        $this->form = PdfDesign::stored();

        activity('updated', 'PDF settings updated');
        $this->dispatch('toast', message: 'PDF settings saved. Open the sample to see them.');
    }

    public function render()
    {
        return view('livewire.settings.pdf-settings', [
            'ownLogo' => PdfDesign::hasOwnLogo(),
            'logoPreview' => PdfDesign::logo(170, 50),
        ]);
    }
}
