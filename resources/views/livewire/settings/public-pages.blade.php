<div>
    <x-page-header title="Public Pages" subtitle="The logo, header button, footer and social links shared by your careers pages and support portal.">
        <button type="submit" form="public-pages-form" class="btn-primary" wire:loading.attr="disabled" wire:target="save,logo">Save changes</button>
    </x-page-header>

    <div class="mb-5 grid max-w-4xl gap-3 sm:grid-cols-2">
        @foreach ($pages as $p)
            <div class="card flex items-center justify-between gap-3 p-4" wire:key="page-{{ $p->page }}">
                <div class="min-w-0 text-sm">
                    <p class="font-semibold text-stone-900">{{ $p->name() }}</p>
                    <p class="truncate text-xs text-stone-500">Banner and bottom call-to-action are set on its own page.</p>
                </div>
                <div class="flex shrink-0 gap-1.5">
                    <a href="{{ $p->url() }}" target="_blank" rel="noopener" class="btn-ghost btn-sm border border-stone-200">View</a>
                    @can($p->permission()) <a href="{{ route($p->page === 'careers' ? 'careers.settings' : 'support.settings') }}" class="btn-secondary btn-sm">Page settings</a> @endcan
                </div>
            </div>
        @endforeach
    </div>

    <form id="public-pages-form" wire:submit="save" class="max-w-4xl space-y-5">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Header: logo and name</h2></div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <x-field label="Logo" name="logo" class="md:col-span-2" hint="PNG, JPG, WebP or SVG, up to 1 MB. A wide logo on a transparent background works best. Without one, your app icon and name are shown.">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex h-14 min-w-32 items-center justify-center rounded-md border border-stone-200 bg-white px-3">
                            @if ($logo && ! $errors->has('logo') && $logo->isPreviewable() && ! str_contains((string) $logo->getMimeType(), 'svg'))
                                <img src="{{ $logo->temporaryUrl() }}" alt="New logo preview" class="max-h-10 max-w-48 object-contain">
                            @elseif ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Current logo" class="max-h-10 max-w-48 object-contain">
                            @else
                                <span class="text-xs text-stone-400">No logo</span>
                            @endif
                        </div>
                        <input type="file" class="input max-w-xs" wire:model="logo" accept=".png,.jpg,.jpeg,.webp,.svg" aria-label="Logo">
                        @if ($logoUrl) <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removeLogo" wire:confirm="Remove the logo from the public pages?">Remove</button> @endif
                        <span wire:loading wire:target="logo" class="text-xs text-stone-500">Uploading…</span>
                    </div>
                </x-field>
                <x-field label="Logo link" name="form.logo_link" hint="Where the logo takes people, e.g. your website. Empty = the start of that page."><input class="input" type="text" wire:model="form.logo_link" placeholder="https://yourcompany.com"></x-field>
                <x-field label="Name beside the logo" name="form.site_name" hint="Empty = {{ app_name() }}. Hidden when a logo is uploaded."><input class="input" type="text" wire:model="form.site_name" maxlength="60"></x-field>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Header: right-side link and button</h2><span class="text-xs text-stone-500">Shown after each page’s own links</span></div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <x-field label="Link text" name="form.nav_label" hint="Leave empty for no extra link."><input class="input" type="text" wire:model.live.debounce.400ms="form.nav_label" maxlength="40" placeholder="e.g. About us"></x-field>
                <x-field label="Link address" name="form.nav_url"><input class="input" type="text" wire:model="form.nav_url" placeholder="https://yourcompany.com/about"></x-field>
                <x-field label="Button text" name="form.button_label" hint="Leave empty for no button."><input class="input" type="text" wire:model.live.debounce.400ms="form.button_label" maxlength="40" placeholder="e.g. Visit our website"></x-field>
                <x-field label="Button address" name="form.button_url"><input class="input" type="text" wire:model="form.button_url" placeholder="https://yourcompany.com"></x-field>
                <x-field label="Button style" name="form.button_style">
                    <select class="input" wire:model.live="form.button_style">
                        @foreach (\App\Support\PublicSite::BUTTON_STYLES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Button shape" name="form.button_shape">
                    <select class="input" wire:model.live="form.button_shape">
                        @foreach (\App\Support\PublicSite::BUTTON_SHAPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Button colour" name="form.button_color" hint="Empty = your brand colour.">
                    <div class="flex items-center gap-2">
                        <input type="color" class="h-9 w-12 cursor-pointer rounded border border-stone-300 bg-white p-0.5" value="{{ \App\Support\Branding::validHex($form['button_color']) ?: \App\Support\Branding::shade(600) }}" x-on:input="$wire.set('form.button_color', $event.target.value)" aria-label="Pick button colour">
                        <input class="input" type="text" wire:model.live.debounce.400ms="form.button_color" placeholder="#1f6f5c" maxlength="7">
                        @if ($form['button_color'] !== '') <button type="button" class="btn-ghost btn-sm" wire:click="$set('form.button_color', '')">Reset</button> @endif
                    </div>
                </x-field>
                <x-field label="Open the button link in" name="form.button_new_tab">
                    <select class="input" wire:model="form.button_new_tab">
                        <option value="1">A new tab</option>
                        <option value="0">The same tab</option>
                    </select>
                </x-field>
                <div class="md:col-span-2">
                    <p class="label">Preview</p>
                    <div class="flex items-center justify-end gap-4 rounded-md border border-stone-200 bg-white px-4 py-3">
                        @if ($form['nav_label'] !== '') <span class="text-sm font-medium text-stone-600">{{ $form['nav_label'] }}</span> @endif
                        <span class="inline-flex items-center px-4 py-2 text-sm font-semibold" style="{{ \App\Support\PublicSite::buttonStyle($form['button_style'], $form['button_shape'], $form['button_color']) }}">{{ $form['button_label'] !== '' ? $form['button_label'] : 'Button text' }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Footer</h2></div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <x-field label="Copyright line" name="form.footer_copyright" class="md:col-span-2" hint="Empty = “{{ '© '.date('Y').' '.\App\Support\PublicSite::companyName().'. All rights reserved.' }}”"><input class="input" type="text" wire:model="form.footer_copyright" maxlength="200"></x-field>
                <x-field label="Terms link text" name="form.terms_label"><input class="input" type="text" wire:model="form.terms_label" maxlength="40"></x-field>
                <x-field label="Terms page address" name="form.terms_url" hint="The link shows only when an address is filled in."><input class="input" type="text" wire:model="form.terms_url" placeholder="https://yourcompany.com/terms"></x-field>
                <x-field label="Privacy policy link text" name="form.privacy_label"><input class="input" type="text" wire:model="form.privacy_label" maxlength="40"></x-field>
                <x-field label="Privacy policy address" name="form.privacy_url"><input class="input" type="text" wire:model="form.privacy_url" placeholder="https://yourcompany.com/privacy"></x-field>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Social media</h2><span class="text-xs text-stone-500">Only the ones you fill in are shown</span></div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                @foreach (\App\Support\PublicSite::SOCIALS as $network => $label)
                    <x-field :label="$label" name="form.social_{{ $network }}">
                        <div class="flex items-center gap-2">
                            <span class="grid size-9 shrink-0 place-items-center rounded-md bg-stone-100 text-stone-600"><x-social-icon :name="$network" class="size-4" /></span>
                            <input class="input" type="url" wire:model="form.social_{{ $network }}" placeholder="{{ $network === 'whatsapp' ? 'https://wa.me/919876543210' : 'https://' }}">
                        </div>
                    </x-field>
                @endforeach
            </div>
        </section>

        <div class="flex justify-end pb-6"><button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,logo">Save changes</button></div>
    </form>
</div>
