<?php

namespace App\Livewire\EmailTools;

use App\Models\EmailSignature;
use App\Support\Branding;
use App\Support\EmailBuilder;
use App\Support\PublicSite;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Email Tools → Email Signatures. Fill in the details, pick a layout, then copy the finished
 * signature (or its HTML) into Gmail, Outlook or any other mail program. Each person's details
 * are saved for next time.
 */
#[Title('Email Signatures')]
class Signature extends Component
{
    use WithFileUploads;

    public array $form = [];

    public $photo;

    /** The saved signature that is open, if any. */
    public ?int $loadedId = null;

    public string $name = 'My signature';

    public function mount(): void
    {
        $this->form = $this->starter();

        // Opened from Saved Signatures (?open=12), asked for a blank one (?new=1), or the one worked on last.
        $open = request()->query('open');
        if (is_scalar($open) && ctype_digit((string) $open)) {
            $this->open($this->mine((int) $open));
        } elseif (! request()->boolean('new') && ($latest = EmailSignature::where('user_id', auth()->id())->latest('updated_at')->latest('id')->first())) {
            $this->open($latest);
        }
    }

    protected function open(EmailSignature $signature): void
    {
        $this->form = array_merge($this->starter(), array_intersect_key($signature->data ?? [], EmailBuilder::signatureDefaults()));
        $this->loadedId = $signature->id;
        $this->name = $signature->name;
        $this->reset('photo');
        $this->resetValidation();
    }

    /** People open their own signatures; Super Admins can open anyone's, to set one up for a colleague. */
    protected function mine(int $id): EmailSignature
    {
        $signature = EmailSignature::findOrFail($id);
        abort_unless($signature->user_id === auth()->id() || auth()->user()->isSuperAdmin(), 403);

        return $signature;
    }

    public function load(int $id): void
    {
        $this->open($this->mine($id));
    }

    public function startNew(): void
    {
        $this->form = $this->starter();
        $this->reset('loadedId', 'photo');
        $this->name = 'My signature';
        $this->resetValidation();
    }

    public function delete(int $id): void
    {
        $signature = $this->mine($id);
        $photo = (string) ($signature->data['photo_url'] ?? '');
        $signature->delete();
        $this->deletePhotoIfUnused($photo);
        if ($this->loadedId === $id) {
            $this->startNew();
        }
        $this->dispatch('toast', message: 'Signature deleted.');
    }

    /** A first draft from what the CRM already knows about this person and the company. */
    protected function starter(): array
    {
        $user = auth()->user();
        $logo = Branding::logoUrl();

        return array_merge(EmailBuilder::signatureDefaults(), [
            'name' => $user->name, 'email' => $user->email, 'mobile' => (string) $user->phone,
            'title' => $user->role_label, 'company' => (string) (setting('company_name') ?: app_name()),
            'phone' => (string) setting('company_phone'), 'address' => (string) setting('company_address'),
            'website' => PublicSite::get('logo_link'),
            // SVG logos are not shown by most email programs, so only a bitmap logo is offered as the default.
            'logo_url' => $logo && ! str_ends_with(strtolower((string) setting('logo_path')), '.svg') ? $logo : '',
            'accent' => Branding::brand(),
        ], collect(array_keys(EmailBuilder::SOCIALS))->mapWithKeys(fn ($n) => [$n => PublicSite::get('social_'.$n)])->all());
    }

    /** A photo is cut to a square and given a public address, because email programs can only show images they can download. */
    public function updatedPhoto(): void
    {
        $this->validate(['photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096']], ['photo.max' => 'The photo must be 4 MB or smaller.']);
        $bytes = EmailBuilder::squarePhoto($this->photo->getRealPath(), 320, $this->form['photo_bg'] ?? null);
        if (! $bytes) {
            $this->addError('photo', 'That image could not be read. Try a JPG or PNG.');

            return;
        }
        $this->storePhoto($bytes);
        $this->reset('photo');
    }

    public function useProfilePicture(): void
    {
        $path = auth()->user()->avatar_path;
        $bytes = $path && Storage::disk('local')->exists($path) ? EmailBuilder::squarePhoto(Storage::disk('local')->path($path)) : null;
        if (! $bytes) {
            $this->dispatch('toast', message: 'You have no profile picture yet. Add one from the menu at the top right, or upload a photo here.');

            return;
        }
        $this->storePhoto($bytes);
    }

    protected function storePhoto(string $bytes): void
    {
        $this->deletePhotoIfUnused((string) ($this->form['photo_url'] ?? ''));
        $name = Str::random(40).'.jpg';
        Storage::disk('local')->put('email-assets/'.$name, $bytes);
        $this->form['photo_url'] = route('email.asset', $name);
    }

    public function removePhoto(): void
    {
        $this->deletePhotoIfUnused((string) ($this->form['photo_url'] ?? ''));
        $this->form['photo_url'] = '';
    }

    /**
     * Deletes an uploaded headshot once nothing needs it. Only a file this screen created (its address
     * points at our own image route) is ever touched, and never one that a saved signature still uses:
     * "Save as new" copies share the same photo.
     */
    protected function deletePhotoIfUnused(string $url): void
    {
        $prefix = url('/e').'/';
        if (! str_starts_with($url, $prefix) || ! preg_match('/^[A-Za-z0-9]{40}\.jpg$/', $name = substr($url, strlen($prefix)))) {
            return;
        }
        if (EmailSignature::where('data->photo_url', $url)->exists()) {
            return;
        }
        Storage::disk('local')->delete('email-assets/'.$name);
    }

    public function startOver(): void
    {
        $this->form = array_merge($this->starter(), ['photo_url' => $this->form['photo_url'] ?? '']);
        $this->resetValidation();
    }

    protected function rules(): array
    {
        $link = ['nullable', 'string', 'max:300', function ($attribute, $value, $fail) {
            if (EmailBuilder::url($value) === '') {
                $fail('Enter a web address, for example https://www.example.com');
            }
        }];

        return [
            'form.layout' => ['required', Rule::in(array_keys(EmailBuilder::SIGNATURE_LAYOUTS))],
            'form.name' => ['required', 'string', 'max:80'],
            'form.title' => ['nullable', 'string', 'max:80'],
            'form.company' => ['nullable', 'string', 'max:80'],
            'form.phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s.]+$/'],
            'form.mobile' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s.]+$/'],
            'form.email' => ['nullable', 'email', 'max:120'],
            'form.address' => ['nullable', 'string', 'max:200'],
            'form.note' => ['nullable', 'string', 'max:400'],
            'form.website' => $link, 'form.photo_url' => $link, 'form.logo_url' => $link,
            'form.highlight_url' => $link, 'form.banner_url' => $link, 'form.banner_link' => $link,
            'form.highlight_label' => ['nullable', 'string', 'max:40'],
            'form.highlight_text' => ['nullable', 'string', 'max:120'],
            'form.photo_bg' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.photo_shape' => ['required', Rule::in(array_keys(EmailBuilder::PHOTO_SHAPES))],
            'form.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.text_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.font' => ['required', Rule::in(array_keys(EmailBuilder::FONTS))],
            'form.social_style' => ['required', Rule::in(array_keys(EmailBuilder::SOCIAL_STYLES))],
            'form.social_brand_colors' => ['required', Rule::in(['0', '1'])],
            ...collect(array_keys(EmailBuilder::SOCIALS))->mapWithKeys(fn ($n) => ["form.{$n}" => $link])->all(),
        ];
    }

    /** Saves over the signature that is open, or as a new one when $asNew is true or none is open. */
    public function save(bool $asNew = false): void
    {
        $this->validate($this->rules() + ['name' => ['required', 'string', 'max:120']],
            ['form.*.regex' => 'This is not in the expected format.', 'name.required' => 'Give the signature a name to save it.'],
            collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace('_', ' ', $k)])->all() + ['name' => 'signature name']);

        $existing = ! $asNew && $this->loadedId ? $this->mine($this->loadedId) : null;
        $previousPhoto = (string) ($existing?->data['photo_url'] ?? '');
        $signature = $existing
            ? tap($existing)->update(['name' => trim($this->name), 'data' => $this->form])
            : EmailSignature::create(['user_id' => auth()->id(), 'name' => trim($this->name), 'data' => $this->form]);
        $this->loadedId = $signature->id;
        if ($previousPhoto !== ($this->form['photo_url'] ?? '')) {
            $this->deletePhotoIfUnused($previousPhoto);
        }

        $this->dispatch('toast', message: 'Signature “'.$signature->name.'” saved.');
    }

    public function render()
    {
        $html = EmailBuilder::signature($this->form);
        $own = parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost();
        $images = array_filter([$this->form['photo_url'] ?? '', $this->form['logo_url'] ?? '']);

        return view('livewire.email-tools.signature', [
            'html' => $html,
            'savedCount' => EmailSignature::when(! auth()->user()->isSuperAdmin(), fn ($q) => $q->where('user_id', auth()->id()))->count(),
            // Images served from this computer cannot be seen by the people who receive the email.
            'localImages' => in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1'], true) && collect($images)->contains(fn ($u) => str_contains($u, '//'.request()->getHost()) || str_contains($u, '//'.$own)),
        ]);
    }
}
