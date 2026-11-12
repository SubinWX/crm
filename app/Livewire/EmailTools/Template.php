<?php

namespace App\Livewire\EmailTools;

use App\Models\EmailTemplate;
use App\Support\Branding;
use App\Support\EmailBuilder;
use App\Support\PublicSite;
use App\Support\RichText;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Email Tools → Email Templates. Builds a complete HTML email with a header and footer that
 * can be copied into any mail program or email-sending service. Templates can be saved by name
 * and are shared with the team.
 */
#[Title('Email Templates')]
class Template extends Component
{
    public array $form = [];

    public ?int $loadedId = null;

    public string $name = '';

    public function mount(): void
    {
        $this->form = $this->starter();

        // Opened from Saved Email Templates (?open=12).
        $open = request()->query('open');
        if (is_scalar($open) && ctype_digit((string) $open)) {
            $this->load((int) $open);
        }
    }

    protected function starter(): array
    {
        $logo = Branding::logoUrl();
        $company = (string) (setting('company_name') ?: app_name());

        return array_merge(EmailBuilder::templateDefaults(), [
            'company' => $company, 'accent' => Branding::brand(), 'website' => PublicSite::get('logo_link'),
            'logo_url' => $logo && ! str_ends_with(strtolower((string) setting('logo_path')), '.svg') ? $logo : '',
            'heading' => 'Hello,',
            'body' => '<div>Thank you for getting in touch with us. Here is a quick update on your project:</div><ul><li>The first design is ready for your review.</li><li>We will share the next version by Friday.</li></ul><div>Reply to this email if you have any questions.</div>',
            'signoff' => "Regards,\n".$company,
            'footer_text' => $company,
            'footer_address' => (string) setting('company_address'),
            'footer_phone' => (string) setting('company_phone'), 'footer_email' => (string) setting('company_email'),
            'footer_note' => 'You are receiving this email because you are a client of '.$company.'.',
        ], collect(array_keys(EmailBuilder::SOCIALS))->mapWithKeys(fn ($n) => [$n => PublicSite::get('social_'.$n)])->all());
    }

    public function startNew(): void
    {
        $this->form = $this->starter();
        $this->reset('loadedId', 'name');
        $this->resetValidation();
    }

    public function load(int $id): void
    {
        $template = EmailTemplate::findOrFail($id);
        $this->form = array_merge(EmailBuilder::templateDefaults(), array_intersect_key($template->data ?? [], EmailBuilder::templateDefaults()));
        $this->loadedId = $template->id;
        $this->name = $template->name;
        $this->resetValidation();
    }

    public function delete(int $id): void
    {
        $template = EmailTemplate::findOrFail($id);
        abort_unless(auth()->user()->isSuperAdmin() || (int) $template->created_by === auth()->id(), 403);
        $template->delete();
        if ($this->loadedId === $id) {
            $this->reset('loadedId', 'name');
        }
        $this->dispatch('toast', message: 'Template deleted.');
    }

    protected function rules(): array
    {
        $link = ['nullable', 'string', 'max:300', function ($attribute, $value, $fail) {
            if (EmailBuilder::url($value) === '') {
                $fail('Enter a web address, for example https://www.example.com');
            }
        }];

        return [
            'name' => ['required', 'string', 'max:120'],
            'form.layout' => ['required', Rule::in(array_keys(EmailBuilder::TEMPLATE_LAYOUTS))],
            'form.company' => ['nullable', 'string', 'max:80'],
            'form.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.background' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.font' => ['required', Rule::in(array_keys(EmailBuilder::FONTS))],
            'form.preheader' => ['nullable', 'string', 'max:150'],
            'form.heading' => ['nullable', 'string', 'max:120'],
            'form.body' => ['nullable', 'string', 'max:20000'],
            'form.button_label' => ['nullable', 'string', 'max:40'],
            'form.button_url' => ['nullable', 'required_with:form.button_label', ...array_slice($link, 1)],
            'form.signoff' => ['nullable', 'string', 'max:200'],
            'form.footer_text' => ['nullable', 'string', 'max:300'],
            'form.footer_address' => ['nullable', 'string', 'max:300'],
            'form.footer_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s.]+$/'],
            'form.footer_email' => ['nullable', 'email', 'max:120'],
            'form.banner_position' => ['required', Rule::in(array_keys(EmailBuilder::BANNER_POSITIONS))],
            'form.banner_alt' => ['nullable', 'string', 'max:120'],
            'form.footer_note' => ['nullable', 'string', 'max:300'],
            'form.logo_url' => $link, 'form.hero_url' => $link, 'form.hero_link' => $link, 'form.website' => $link,
            'form.social_style' => ['required', Rule::in(array_keys(EmailBuilder::SOCIAL_STYLES))],
            'form.social_brand_colors' => ['required', Rule::in(['0', '1'])],
            ...collect(array_keys(EmailBuilder::SOCIALS))->mapWithKeys(fn ($n) => ["form.{$n}" => $link])->all(),
        ];
    }

    /** Saves over the template that is open, or as a new one when $asNew is true or none is open. */
    public function save(bool $asNew = false): void
    {
        $this->validate($this->rules(), ['form.button_url.required_with' => 'Add the address the button should open.', 'name.required' => 'Give the template a name to save it.'],
            collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace(['_url', '_'], [' address', ' '], $k)])->all() + ['name' => 'template name']);

        $data = ['name' => trim($this->name), 'data' => array_merge($this->form, ['body' => (string) RichText::clean($this->form['body'])])];
        $existing = ! $asNew && $this->loadedId ? EmailTemplate::find($this->loadedId) : null;
        $template = $existing ? tap($existing)->update($data) : EmailTemplate::create($data + ['created_by' => auth()->id()]);
        $this->loadedId = $template->id;

        $this->dispatch('toast', message: 'Template “'.$template->name.'” saved.');
    }

    public function render()
    {
        $images = array_filter([$this->form['logo_url'] ?? '', $this->form['hero_url'] ?? '']);

        return view('livewire.email-tools.template', [
            'html' => EmailBuilder::template($this->form),
            'savedCount' => EmailTemplate::count(),
            'localImages' => in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1'], true) && collect($images)->contains(fn ($u) => str_contains($u, '//'.request()->getHost())),
        ]);
    }
}
