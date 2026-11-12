<div>
    <x-page-header title="Settings" subtitle="Company details, defaults and the values behind every dropdown." />

    <div class="grid gap-5 lg:grid-cols-[14rem_minmax(0,1fr)]">
        <nav class="card h-fit p-2 text-sm" aria-label="Settings sections">
            <button type="button" wire:click="setTab('general')" @class(['block w-full cursor-pointer rounded-md px-3 py-2 text-left', 'bg-brand-50 font-semibold text-brand-800' => $tab === 'general', 'text-stone-700 hover:bg-stone-50' => $tab !== 'general'])>General</button>
            <button type="button" wire:click="setTab('branding')" @class(['block w-full cursor-pointer rounded-md px-3 py-2 text-left', 'bg-brand-50 font-semibold text-brand-800' => $tab === 'branding', 'text-stone-700 hover:bg-stone-50' => $tab !== 'branding'])>Branding</button>
            <a href="{{ route('settings.messaging') }}" class="block rounded-md px-3 py-2 text-stone-700 hover:bg-stone-50">Email &amp; WhatsApp →</a>
            <a href="{{ route('settings.public-pages') }}" class="block rounded-md px-3 py-2 text-stone-700 hover:bg-stone-50">Public Pages →</a>
            <a href="{{ route('settings.pdf') }}" class="block rounded-md px-3 py-2 text-stone-700 hover:bg-stone-50">PDF Settings →</a>
            <a href="{{ route('quote-templates.index') }}" class="block rounded-md px-3 py-2 text-stone-700 hover:bg-stone-50">Quote Templates →</a>
            <p class="px-3 pt-3 pb-1 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">Dropdown values</p>
            @foreach (\App\Models\Lookup::TYPES as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" @class(['block w-full cursor-pointer rounded-md px-3 py-2 text-left', 'bg-brand-50 font-semibold text-brand-800' => $tab === $key, 'text-stone-700 hover:bg-stone-50' => $tab !== $key])>{{ $label }}</button>
            @endforeach
        </nav>

        @if ($tab === 'branding')
            <form wire:submit="saveBranding" class="space-y-5">
                <section class="card">
                    <div class="card-header"><h2 class="card-title">App name</h2></div>
                    <div class="p-4">
                        <x-field label="Name" name="branding.app_name" hint="Shown in the sidebar, browser tab, sign-in pages, support portal and emails. Leave empty to use the company name ({{ setting('company_name') }}).">
                            <input class="input max-w-md" type="text" wire:model="branding.app_name" maxlength="60" placeholder="{{ setting('company_name') }}">
                        </x-field>
                    </div>
                </section>

                <section class="card">
                    <div class="card-header"><h2 class="card-title">Colours</h2><span class="text-xs text-stone-500">Two colours restyle the whole app, the support portal and the PDFs.</span></div>
                    <div class="space-y-5 p-4">
                        <div>
                            <p class="label">Quick themes</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Support\Branding::PRESETS as $name => [$brandHex, $sidebarHex])
                                    <button type="button" wire:click="applyPreset('{{ $name }}')" @class(['flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm', 'border-stone-900 bg-stone-50 font-semibold' => strtolower($branding['brand_color']) === $brandHex && strtolower($branding['sidebar_color']) === $sidebarHex, 'border-stone-300 bg-white hover:bg-stone-50' => ! (strtolower($branding['brand_color']) === $brandHex && strtolower($branding['sidebar_color']) === $sidebarHex)])>
                                        <span class="flex overflow-hidden rounded"><span class="size-4" style="background: {{ $brandHex }}"></span><span class="size-4" style="background: {{ $sidebarHex }}"></span></span>{{ $name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <x-field label="Brand colour" name="branding.brand_color" hint="Buttons, links, highlights and PDF headings.">
                                <div class="flex items-center gap-2">
                                    <input type="color" class="h-10 w-14 cursor-pointer rounded-md border border-stone-300 bg-white p-1" wire:model.live="branding.brand_color" aria-label="Brand colour picker">
                                    <input type="text" class="input w-32 font-mono" wire:model.live.debounce.400ms="branding.brand_color" maxlength="7" aria-label="Brand colour hex code">
                                </div>
                            </x-field>
                            <x-field label="Sidebar colour" name="branding.sidebar_color" hint="The left menu and the sign-in panel. Must be dark.">
                                <div class="flex items-center gap-2">
                                    <input type="color" class="h-10 w-14 cursor-pointer rounded-md border border-stone-300 bg-white p-1" wire:model.live="branding.sidebar_color" aria-label="Sidebar colour picker">
                                    <input type="text" class="input w-32 font-mono" wire:model.live.debounce.400ms="branding.sidebar_color" maxlength="7" aria-label="Sidebar colour hex code">
                                </div>
                            </x-field>
                        </div>
                        @if (\App\Support\Branding::validHex($branding['brand_color']) && \App\Support\Branding::validHex($branding['sidebar_color']))
                            <div>
                                <p class="label">Preview</p>
                                <div class="flex max-w-xl overflow-hidden rounded-lg border border-stone-200">
                                    <div class="w-40 space-y-1.5 p-3 text-xs" style="background: {{ $branding['sidebar_color'] }}">
                                        <p class="font-semibold text-white">{{ $branding['app_name'] ?: setting('company_name') }}</p>
                                        <p class="rounded px-2 py-1 text-white" style="background: rgba(255,255,255,.12)">Dashboard</p>
                                        <p class="px-2 py-1 text-stone-300">Clients</p>
                                    </div>
                                    <div class="flex flex-1 flex-wrap items-center gap-3 bg-white p-4 text-sm">
                                        <span class="rounded-md px-3 py-2 font-medium text-white" style="background: {{ $branding['brand_color'] }}">Primary button</span>
                                        <span class="font-medium underline" style="color: {{ \App\Support\Branding::mix($branding['brand_color'], '#000000', 0.18) }}">A link</span>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" style="background: {{ \App\Support\Branding::mix($branding['brand_color'], '#ffffff', 0.93) }}; color: {{ \App\Support\Branding::mix($branding['brand_color'], '#000000', 0.34) }}">Highlight</span>
                                    </div>
                                </div>
                                <p class="mt-1.5 text-xs text-stone-500">Red stays reserved for overdue and urgent items whatever you choose.</p>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="card">
                    <div class="card-header"><h2 class="card-title">Logo, icon and favicon</h2></div>
                    <div class="grid gap-6 p-4 md:grid-cols-2 xl:grid-cols-3">
                        <x-field label="Logo" name="logo" hint="Your full logo, any shape. PNG, JPG, WebP or SVG, up to 1 MB. Used on invoices, quotes and reports.">
                            <div class="flex items-center gap-4">
                                @if ($logo && ! $errors->has('logo') && $logo->isPreviewable()) <img src="{{ $logo->temporaryUrl() }}" alt="New logo preview" class="size-14 rounded-md border border-stone-200 bg-white object-contain p-1">
                                @elseif (\App\Support\Branding::logoUrl()) <img src="{{ \App\Support\Branding::logoUrl() }}" alt="Current logo" class="size-14 rounded-md border border-stone-200 bg-white object-contain p-1">
                                @else <span class="grid size-14 place-items-center rounded-md border border-dashed border-stone-300 bg-stone-50 text-[11px] text-stone-400">None</span> @endif
                                <div class="min-w-0 flex-1">
                                    <input type="file" class="input" accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg" wire:model="logo" aria-label="Logo">
                                    <p wire:loading wire:target="logo" class="mt-1 text-xs text-stone-500">Uploading…</p>
                                    @if (\App\Support\Branding::logoUrl() && ! $logo) <button type="button" class="link mt-1 text-xs text-red-700" wire:click="removeBrandFile('logo')" wire:confirm="Remove the logo?">Remove logo</button> @endif
                                </div>
                            </div>
                        </x-field>
                        <x-field label="App icon" name="icon" hint="A small square mark (PNG, JPG, WebP or SVG, up to 512 KB). Shown next to the app name in the sidebar, sign-in pages and support portal. Without one, the logo is squeezed into that square.">
                            <div class="flex items-center gap-4">
                                @if ($icon && ! $errors->has('icon') && $icon->isPreviewable()) <img src="{{ $icon->temporaryUrl() }}" alt="New icon preview" class="size-12 rounded-md border border-stone-200 bg-white object-contain">
                                @elseif (setting('icon_path')) <img src="{{ route('branding.asset', ['kind' => 'icon', 'v' => substr(md5(setting('icon_path')), 0, 8)]) }}" alt="Current icon" class="size-12 rounded-md border border-stone-200 bg-white object-contain">
                                @else <span class="grid size-12 place-items-center rounded-md border border-dashed border-stone-300 bg-stone-50 text-[11px] text-stone-400">None</span> @endif
                                <div class="min-w-0 flex-1">
                                    <input type="file" class="input" accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg" wire:model="icon" aria-label="App icon">
                                    <p wire:loading wire:target="icon" class="mt-1 text-xs text-stone-500">Uploading…</p>
                                    @if (setting('icon_path') && ! $icon) <button type="button" class="link mt-1 text-xs text-red-700" wire:click="removeBrandFile('icon')" wire:confirm="Remove the app icon?">Remove icon</button> @endif
                                </div>
                            </div>
                        </x-field>
                        <x-field label="Favicon" name="favicon" hint="The browser-tab icon. PNG, ICO or SVG, up to 256 KB, ideally 32×32 or 64×64. Without one, the app icon is used.">
                            <div class="flex items-center gap-4">
                                @if (\App\Support\Branding::faviconUrl() && ! $favicon) <img src="{{ \App\Support\Branding::faviconUrl() }}" alt="Current favicon" class="size-10 rounded border border-stone-200 bg-white object-contain p-1">
                                @else <span class="grid size-10 place-items-center rounded border border-dashed border-stone-300 bg-stone-50 text-[10px] text-stone-400">{{ $favicon ? 'New' : 'None' }}</span> @endif
                                <div class="min-w-0 flex-1">
                                    <input type="file" class="input" accept="image/png,.ico,image/x-icon,image/vnd.microsoft.icon,image/svg+xml,.svg" wire:model="favicon" aria-label="Favicon">
                                    <p wire:loading wire:target="favicon" class="mt-1 text-xs text-stone-500">Uploading…</p>
                                    @if (\App\Support\Branding::faviconUrl() && ! $favicon) <button type="button" class="link mt-1 text-xs text-red-700" wire:click="removeBrandFile('favicon')" wire:confirm="Remove the favicon?">Remove favicon</button> @endif
                                </div>
                            </div>
                        </x-field>
                    </div>
                </section>
                <div class="flex justify-end"><button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="logo,icon,favicon,saveBranding">Save branding</button></div>
            </form>
        @elseif ($tab === 'general')
            <form wire:submit="saveGeneral" class="space-y-5">
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Regional defaults</h2></div>
                    <div class="grid gap-4 p-4 md:grid-cols-2">
                        <x-field label="Default currency" name="general.currency" required hint="Used for hosting, payments, expenses and new invoices.">
                            <select class="input" wire:model="general.currency">
                                @foreach (\App\Livewire\Settings\Index::CURRENCIES as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                            </select>
                        </x-field>
                        <x-field label="Time zone" name="general.timezone" required hint="Decides what “today” means for reminders and due dates.">
                            <select class="input" wire:model="general.timezone">
                                @foreach ($timezones as $tz) <option value="{{ $tz }}">{{ $tz }}</option> @endforeach
                            </select>
                        </x-field>
                    </div>
                </section>
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Company (shown on invoices)</h2></div>
                    <div class="grid gap-4 p-4 md:grid-cols-2">
                        <x-field label="Company name" name="general.company_name" required><input class="input" type="text" wire:model="general.company_name"></x-field>
                        <x-field label="GSTIN / Tax ID" name="general.company_tax_id"><input class="input uppercase" type="text" wire:model="general.company_tax_id" maxlength="40" placeholder="32ABCDE1234F1Z5"></x-field>
                        <x-field label="State (for GST)" name="general.company_state" hint="Choosing your state switches invoices to GST: CGST + SGST for clients in the same state, IGST for other states. Leave empty if you do not charge GST.">
                            <select class="input" wire:model="general.company_state">
                                <option value="">Not using GST</option>
                                @foreach (\App\Support\Gst::STATES as $code => $name) @if ($code !== \App\Support\Gst::FOREIGN) <option value="{{ $code }}">{{ $code }} · {{ $name }}</option> @endif @endforeach
                            </select>
                        </x-field>
                        <x-field label="Email" name="general.company_email"><input class="input" type="email" wire:model="general.company_email"></x-field>
                        <x-field label="Phone" name="general.company_phone"><input class="input" type="tel" wire:model="general.company_phone"></x-field>
                        <x-field label="Address" name="general.company_address" class="md:col-span-2"><textarea class="input" rows="2" wire:model="general.company_address"></textarea></x-field>
                    </div>
                </section>
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Invoice defaults</h2></div>
                    <div class="grid gap-4 p-4 md:grid-cols-3">
                        <x-field label="Number prefix" name="general.invoice_prefix"><input class="input" type="text" wire:model="general.invoice_prefix"></x-field>
                        <x-field label="Default tax rate (%)" name="general.invoice_default_tax_rate"><input class="input" type="number" step="0.01" min="0" wire:model="general.invoice_default_tax_rate"></x-field>
                        <x-field label="Payment terms (days)" name="general.invoice_due_days"><input class="input" type="number" min="0" wire:model="general.invoice_due_days"></x-field>
                        <x-field label="Default HSN / SAC code" name="general.invoice_default_hsn" hint="Filled into new invoice lines. For example 998314 for IT design and development services."><input class="input" type="text" inputmode="numeric" maxlength="8" wire:model="general.invoice_default_hsn"></x-field>
                        <x-field label="Footer note" name="general.invoice_footer" class="md:col-span-3"><input class="input" type="text" wire:model="general.invoice_footer"></x-field>
                    </div>
                </section>
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Renewal reminders</h2><span class="text-xs text-stone-500">Days before the renewal date, separated by commas. Hosting is always reminded 2 months and 7 days before.</span></div>
                    <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach (\App\Models\Renewal::TYPES as $key => $label)
                            <x-field :label="$label" name="general.renewal_reminder_days_{{ $key }}"><input class="input" type="text" wire:model="general.renewal_reminder_days_{{ $key }}" placeholder="60,7"></x-field>
                        @endforeach
                    </div>
                </section>
                <div class="flex justify-end"><button type="submit" class="btn-primary">Save settings</button></div>
            </form>
        @else
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ \App\Models\Lookup::TYPES[$tab] }}</h2>
                    <span class="text-xs text-stone-500">Deactivated values stay on existing records but are hidden from new entries.</span>
                </div>
                <form wire:submit="addValue" class="flex flex-wrap items-start gap-3 border-b border-stone-200 p-4">
                    <div class="min-w-56 flex-1">
                        <input class="input" type="text" placeholder="Add a new value…" wire:model="newName" aria-label="New value name">
                        @error('newName') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    @if ($tab === 'server_type')
                        <label class="flex items-center gap-2 py-2 text-sm text-stone-700"><input type="checkbox" class="rounded border-stone-300" wire:model="newIsPaid"> Billed by the agency (sends reminders)</label>
                    @endif
                    <button type="submit" class="btn-primary"><x-icon name="plus" /> Add</button>
                </form>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Name</th>@if ($tab === 'server_type')<th>Paid Server Clients &amp; reminders</th>@endif<th>In use</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            @forelse ($values as $value)
                                <tr wire:key="lookup-{{ $value->id }}">
                                    <td>
                                        @if ($editingId === $value->id)
                                            <form wire:submit="rename" class="flex items-center gap-2">
                                                <input class="input max-w-64" type="text" wire:model="editingName" aria-label="Rename value" autofocus>
                                                <button type="submit" class="btn-primary btn-sm">Save</button>
                                                <button type="button" class="btn-ghost btn-sm" wire:click="cancelRename">Cancel</button>
                                            </form>
                                            @error('editingName') <p class="field-error">{{ $message }}</p> @enderror
                                        @else
                                            <span @class(['font-medium', 'text-stone-900' => $value->is_active, 'text-stone-400' => ! $value->is_active])>{{ $value->name }}</span>
                                        @endif
                                    </td>
                                    @if ($tab === 'server_type')
                                        <td><label class="flex items-center gap-2 text-sm"><input type="checkbox" class="rounded border-stone-300" @checked($value->is_paid) wire:click="togglePaid({{ $value->id }})"> Billed by the agency</label></td>
                                    @endif
                                    <td class="tabular-nums">{{ $value->usage }} {{ str('record')->plural($value->usage) }}</td>
                                    <td>@if ($value->is_active) <span class="badge-green">Active</span> @else <span class="badge-gray">Inactive</span> @endif</td>
                                    <td class="text-right whitespace-nowrap">
                                        <button type="button" class="btn-ghost btn-sm" wire:click="startRename({{ $value->id }})">Rename</button>
                                        <button type="button" class="btn-ghost btn-sm" wire:click="toggleActive({{ $value->id }})">{{ $value->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                        @if ($value->usage === 0)
                                            <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $value->id }})" wire:confirm="Delete “{{ $value->name }}”?">Delete</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-empty :colspan="$tab === 'server_type' ? 5 : 4">No values yet. Add the first one above.</x-empty>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</div>
