<?php

namespace App\Livewire\Settings;

use App\Models\Lookup;
use App\Models\Renewal;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Settings')]
class Index extends Component
{
    use WithFileUploads;

    public array $branding = [];

    public $logo;

    public $favicon;

    /** Small square mark for the sidebar and other tight spots. */
    public $icon;

    public const CURRENCIES = ['INR' => 'Indian Rupee (INR)', 'USD' => 'US Dollar (USD)', 'EUR' => 'Euro (EUR)', 'GBP' => 'Pound Sterling (GBP)', 'AED' => 'UAE Dirham (AED)', 'SGD' => 'Singapore Dollar (SGD)', 'AUD' => 'Australian Dollar (AUD)', 'CAD' => 'Canadian Dollar (CAD)'];

    /** general | one of Lookup::TYPES */
    #[Url(except: 'general')]
    public string $tab = 'general';

    public array $general = [];

    public string $newName = '';

    public bool $newIsPaid = false;

    public ?int $editingId = null;

    public string $editingName = '';

    public function boot(): void
    {
        Gate::authorize('admin');
    }

    public function mount(): void
    {
        foreach (array_keys(Setting::DEFAULTS) as $key) {
            $this->general[$key] = (string) setting($key);
        }
        $this->branding = ['app_name' => (string) setting('app_name'), 'brand_color' => Branding::brand(), 'sidebar_color' => Branding::sidebar()];
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['general', 'branding'], true) || array_key_exists($tab, Lookup::TYPES) ? $tab : 'general';
        $this->reset(['newName', 'newIsPaid', 'editingId', 'editingName']);
        $this->resetValidation();
    }

    public function applyPreset(string $name): void
    {
        if ($preset = Branding::PRESETS[$name] ?? null) {
            [$this->branding['brand_color'], $this->branding['sidebar_color']] = $preset;
        }
    }

    public function removeBrandFile(string $kind): void
    {
        abort_unless(in_array($kind, ['logo', 'icon', 'favicon'], true), 404);
        if ($path = setting("{$kind}_path")) {
            Storage::disk('local')->delete($path);
            Setting::put("{$kind}_path", '');
        }
    }

    public function saveBranding()
    {
        $this->validate([
            'branding.app_name' => ['nullable', 'string', 'max:60'],
            // Buttons and the sidebar carry white text, so both colours must be dark enough to read against.
            'branding.brand_color' => ['bail', 'required', 'regex:/^#[0-9a-fA-F]{6}$/', function ($attribute, $value, $fail) {
                if (Branding::contrastWithWhite($value) < 3.2) {
                    $fail('That colour is too light for white button text. Pick a darker shade.');
                }
            }],
            'branding.sidebar_color' => ['bail', 'required', 'regex:/^#[0-9a-fA-F]{6}$/', function ($attribute, $value, $fail) {
                if (Branding::contrastWithWhite($value) < 7) {
                    $fail('The sidebar needs a dark colour so its light text stays readable.');
                }
            }],
            // PNG, JPG, WebP or SVG. An SVG must be a plain drawing: one carrying scripts or outside links is refused.
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024', $safeSvg = function ($attribute, $value, $fail) {
                if (strtolower($value->getClientOriginalExtension()) === 'svg' || str_contains((string) $value->getMimeType(), 'svg')) {
                    if (! Branding::svgIsSafe((string) file_get_contents($value->getRealPath()))) {
                        $fail('This SVG contains scripts, links or embedded content. Export it as a plain SVG (shapes only) and try again.');
                    }
                }
            }],
            'icon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:512', $safeSvg],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg', 'max:256', $safeSvg],
        ], [], ['branding.app_name' => 'app name', 'branding.brand_color' => 'brand colour', 'branding.sidebar_color' => 'sidebar colour']);

        Setting::put('app_name', trim((string) $this->branding['app_name']));
        Setting::put('brand_color', strtolower($this->branding['brand_color']));
        Setting::put('sidebar_color', strtolower($this->branding['sidebar_color']));

        foreach (['logo' => $this->logo, 'icon' => $this->icon, 'favicon' => $this->favicon] as $kind => $file) {
            if ($file) {
                if ($old = setting("{$kind}_path")) {
                    Storage::disk('local')->delete($old);
                }
                // Keep the real extension so SVGs are stored and served as SVG.
                $extension = strtolower($file->getClientOriginalExtension()) === 'svg' ? 'svg' : $file->extension();
                Setting::put("{$kind}_path", $file->storeAs('branding', Str::random(40).'.'.$extension, 'local'));
            }
        }

        activity('updated', 'Branding updated');
        session()->flash('status', 'Branding saved. The new name, colours and images are now used everywhere.');

        // Reload so the whole page picks up the new colours.
        return $this->redirectRoute('settings.index', ['tab' => 'branding']);
    }

    public function saveGeneral(): void
    {
        $this->validate([
            'general.company_name' => ['required', 'string', 'max:255'],
            'general.company_address' => ['nullable', 'string', 'max:1000'],
            'general.company_email' => ['nullable', 'email', 'max:255'],
            'general.company_phone' => ['nullable', 'string', 'max:30'],
            'general.company_tax_id' => ['nullable', 'string', 'max:40'],
            'general.currency' => ['required', Rule::in(array_keys(self::CURRENCIES))],
            'general.timezone' => ['required', 'timezone:all'],
            'general.invoice_prefix' => ['nullable', 'string', 'max:10'],
            'general.invoice_default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'general.invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'general.invoice_footer' => ['nullable', 'string', 'max:500'],
            'general.renewal_reminder_days_domain' => $days = ['required', 'regex:/^\s*\d{1,3}(\s*,\s*\d{1,3}){0,5}\s*$/'],
            'general.renewal_reminder_days_maintenance' => $days,
            'general.renewal_reminder_days_retainer' => $days,
            'general.renewal_reminder_days_other' => $days,
        ], collect(Renewal::TYPES)->keys()->mapWithKeys(fn ($t) => ["general.renewal_reminder_days_{$t}.regex" => 'Use whole numbers of days separated by commas, for example 60,7.'])->all(), collect($this->general)->keys()->mapWithKeys(fn ($k) => ["general.{$k}" => str_replace('_', ' ', $k)])->all());

        foreach ($this->general as $key => $value) {
            if (array_key_exists($key, Setting::DEFAULTS)) {
                Setting::put($key, $value);
            }
        }
        activity('updated', 'Settings updated');
        $this->dispatch('toast', message: 'Settings saved.');
    }

    public function addValue(): void
    {
        abort_unless(array_key_exists($this->tab, Lookup::TYPES), 404);
        $this->validate(['newName' => ['required', 'string', 'max:255', Rule::unique('lookups', 'name')->where('type', $this->tab)]], [], ['newName' => 'name']);

        Lookup::create([
            'type' => $this->tab,
            'name' => trim($this->newName),
            'is_paid' => $this->tab === 'server_type' && $this->newIsPaid,
            'sort_order' => (int) Lookup::where('type', $this->tab)->max('sort_order') + 1,
        ]);
        activity('created', Lookup::TYPES[$this->tab].": added \"{$this->newName}\"");
        $this->reset(['newName', 'newIsPaid']);
    }

    public function startRename(int $id): void
    {
        $lookup = Lookup::where('type', $this->tab)->findOrFail($id);
        $this->editingId = $lookup->id;
        $this->editingName = $lookup->name;
    }

    public function rename(): void
    {
        $lookup = Lookup::where('type', $this->tab)->findOrFail($this->editingId);
        $this->validate(['editingName' => ['required', 'string', 'max:255', Rule::unique('lookups', 'name')->where('type', $this->tab)->ignore($lookup->id)]], [], ['editingName' => 'name']);
        $lookup->update(['name' => trim($this->editingName)]);
        $this->reset(['editingId', 'editingName']);
    }

    public function cancelRename(): void
    {
        $this->reset(['editingId', 'editingName']);
    }

    /** Deactivated values disappear from dropdowns but stay on existing records. */
    public function toggleActive(int $id): void
    {
        $lookup = Lookup::where('type', $this->tab)->findOrFail($id);
        $lookup->update(['is_active' => ! $lookup->is_active]);
        activity('updated', Lookup::TYPES[$lookup->type].": \"{$lookup->name}\" ".($lookup->is_active ? 'reactivated' : 'deactivated'));
    }

    public function togglePaid(int $id): void
    {
        $lookup = Lookup::where('type', 'server_type')->findOrFail($id);
        $lookup->update(['is_paid' => ! $lookup->is_paid]);
    }

    public function delete(int $id): void
    {
        $lookup = Lookup::where('type', $this->tab)->findOrFail($id);
        if ($lookup->usageCount() > 0) {
            $this->dispatch('toast', message: 'This value is in use. Deactivate it instead.', type: 'error');

            return;
        }
        $lookup->delete();
    }

    public function render()
    {
        $values = collect();
        if (array_key_exists($this->tab, Lookup::TYPES)) {
            $values = Lookup::ofType($this->tab)->get()->each(fn ($l) => $l->usage = $l->usageCount());
        }

        return view('livewire.settings.index', [
            'values' => $values,
            'timezones' => $this->tab === 'general' ? timezone_identifiers_list() : [],
        ]);
    }
}
