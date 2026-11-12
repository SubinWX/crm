<div>
    <x-page-header :title="$design->name().' Settings'" subtitle="The banner at the top of this page and the call-to-action band above its footer.">
        <a href="{{ $design->url() }}" target="_blank" rel="noopener" class="btn-secondary">View page</a>
        <button type="submit" form="page-settings-form" class="btn-primary" wire:loading.attr="disabled" wire:target="save,banner">Save changes</button>
    </x-page-header>

    <p class="mb-5 max-w-4xl rounded-md bg-stone-50 p-3 text-sm text-stone-600">
        The logo, the header link and button, the footer and the social media links are shared by all public pages.
        @can('admin') Change them in <a class="link" href="{{ route('settings.public-pages') }}">Administration → Public Pages</a>. @else A Super Admin changes them in Administration → Public Pages. @endcan
    </p>

    <form id="page-settings-form" wire:submit="save" class="max-w-4xl space-y-5">
        {{-- Banner --}}
        <section class="card">
            <div class="card-header"><h2 class="card-title">Banner (top of the page)</h2></div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <x-field label="Background image" name="banner" class="md:col-span-2" hint="JPG, PNG or WebP, up to 4 MB. A wide photo (about 1920 × 600) works best. Without one, the banner uses your brand colour.">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="h-20 w-56 overflow-hidden rounded-md border border-stone-200 bg-brand-700">
                            @if ($banner && ! $errors->has('banner') && $banner->isPreviewable()) <img src="{{ $banner->temporaryUrl() }}" alt="New banner preview" class="h-full w-full object-cover">
                            @elseif ($bannerUrl) <img src="{{ $bannerUrl }}" alt="Current banner" class="h-full w-full object-cover"> @endif
                        </div>
                        <input type="file" class="input max-w-xs" wire:model="banner" accept=".png,.jpg,.jpeg,.webp" aria-label="Banner image">
                        @if ($bannerUrl) <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removeBanner" wire:confirm="Remove the banner image?">Remove</button> @endif
                        <span wire:loading wire:target="banner" class="text-xs text-stone-500">Uploading…</span>
                    </div>
                </x-field>
                <x-field label="Heading" name="form.banner_heading" hint="Empty = “{{ $design->default('banner_heading') }}”."><input class="input" type="text" wire:model="form.banner_heading" maxlength="80"></x-field>
                <x-field label="Small line beside the logo" name="form.tagline" hint="Clear it to hide the line."><input class="input" type="text" wire:model="form.tagline" maxlength="60"></x-field>
                <x-field label="Short description" name="form.banner_text" class="md:col-span-2" hint="Clear it to show the heading only."><textarea class="input" rows="2" wire:model="form.banner_text" maxlength="300"></textarea></x-field>
                <x-field label="Text alignment" name="form.banner_align">
                    <select class="input" wire:model="form.banner_align">
                        @foreach (\App\Support\PublicPage::ALIGNMENTS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Banner height" name="form.banner_height">
                    <select class="input" wire:model="form.banner_height">
                        @foreach (\App\Support\PublicPage::HEIGHTS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Darken the image: {{ $form['banner_overlay'] }}%" name="form.banner_overlay" class="md:col-span-2" hint="Higher makes the white text easier to read over a bright photo.">
                    <input type="range" min="0" max="90" step="5" class="w-full max-w-md accent-brand-600" wire:model.live="form.banner_overlay" aria-label="Darken the banner image">
                </x-field>
            </div>
        </section>

        {{-- Call to action --}}
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Call-to-action banner (bottom of the page)</h2>
                <span class="{{ $form['cta_enabled'] === '1' ? 'badge-green' : 'badge-gray' }}">{{ $form['cta_enabled'] === '1' ? 'Shown' : 'Hidden' }}</span>
            </div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <x-field label="Show this banner" name="form.cta_enabled" hint="A coloured band above the footer with a heading and one button.">
                    <select class="input" wire:model.live="form.cta_enabled"><option value="0">No</option><option value="1">Yes</option></select>
                </x-field>
                <x-field label="Colour" name="form.cta_style">
                    <select class="input" wire:model.live="form.cta_style">
                        @foreach (\App\Support\PublicPage::CTA_STYLES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Heading" name="form.cta_heading" class="md:col-span-2" :required="$form['cta_enabled'] === '1'"><input class="input" type="text" wire:model.live.debounce.400ms="form.cta_heading" maxlength="100"></x-field>
                <x-field label="Text under the heading" name="form.cta_text" class="md:col-span-2" hint="Clear it to show the heading only."><textarea class="input" rows="2" wire:model.live.debounce.400ms="form.cta_text" maxlength="300"></textarea></x-field>
                <x-field label="Button text" name="form.cta_button_label" :required="$form['cta_enabled'] === '1'"><input class="input" type="text" wire:model.live.debounce.400ms="form.cta_button_label" maxlength="40"></x-field>
                <x-field label="Button address" name="form.cta_button_url" :required="$form['cta_enabled'] === '1'" hint="A web address, or mailto:you@company.com, or tel:+919876543210."><input class="input" type="text" wire:model="form.cta_button_url" placeholder="https://yourcompany.com/contact"></x-field>
                <x-field label="Open the button link in" name="form.cta_new_tab">
                    <select class="input" wire:model="form.cta_new_tab"><option value="1">A new tab</option><option value="0">The same tab</option></select>
                </x-field>

                @php
                    [$band, $headingColor, $textColor, $buttonClass] = [
                        'brand' => ['bg-brand-700', 'text-white', 'text-white/85', 'bg-white text-brand-800'],
                        'dark' => ['bg-ink-900', 'text-white', 'text-white/75', 'bg-brand-500 text-white'],
                        'light' => ['bg-brand-50 border border-brand-100', 'text-stone-900', 'text-stone-600', 'bg-brand-600 text-white'],
                    ][$form['cta_style']] ?? ['bg-brand-700', 'text-white', 'text-white/85', 'bg-white text-brand-800'];
                @endphp
                <div class="md:col-span-2">
                    <p class="label">Preview</p>
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-md p-5 {{ $band }} {{ $form['cta_enabled'] === '1' ? '' : 'opacity-50' }}">
                        <div class="max-w-xl">
                            <p class="text-lg font-semibold {{ $headingColor }}">{{ $form['cta_heading'] !== '' ? $form['cta_heading'] : 'Heading' }}</p>
                            @if ($form['cta_text'] !== '') <p class="mt-1 text-sm {{ $textColor }}">{{ $form['cta_text'] }}</p> @endif
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold {{ $buttonClass }}">{{ $form['cta_button_label'] !== '' ? $form['cta_button_label'] : 'Button' }} →</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2 pb-6">
            <a href="{{ $design->url() }}" target="_blank" rel="noopener" class="btn-secondary">View page</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,banner">Save changes</button>
        </div>
    </form>
</div>
