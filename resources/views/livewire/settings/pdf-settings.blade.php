<div>
    <x-page-header title="PDF Settings" subtitle="The logo, header, footer and style used on every PDF: invoices, quotes and proposals, and reports.">
        <a href="{{ route('settings.pdf.sample') }}" target="_blank" rel="noopener" class="btn-secondary" title="Opens a sample invoice using the saved settings">View sample PDF</a>
        <button type="submit" form="pdf-settings-form" class="btn-primary" wire:loading.attr="disabled" wire:target="save,logo">Save changes</button>
    </x-page-header>

    <form id="pdf-settings-form" wire:submit="save" class="max-w-4xl space-y-5">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Logo</h2></div>
            <div class="p-4 sm:p-5">
                <x-field label="Logo for PDFs" name="logo" hint="PNG or JPG prints best (SVG also accepted), up to 1 MB. Leave empty to use the app logo from Settings → Branding. It is scaled to fit the header whatever its shape.">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex h-16 min-w-40 items-center justify-center rounded-md border border-stone-200 bg-white px-3">
                            @if ($logo && ! $errors->has('logo') && $logo->isPreviewable() && ! str_contains((string) $logo->getMimeType(), 'svg'))
                                <img src="{{ $logo->temporaryUrl() }}" alt="New logo preview" class="max-h-12 max-w-48 object-contain">
                            @elseif ($logoPreview)
                                <img src="{{ $logoPreview['src'] }}" alt="Logo used on PDFs" class="max-h-12 max-w-48 object-contain">
                            @else
                                <span class="text-xs text-stone-400">No logo: the company name is printed</span>
                            @endif
                        </div>
                        <input type="file" class="input max-w-xs" wire:model="logo" accept=".png,.jpg,.jpeg,.svg" aria-label="PDF logo">
                        @if ($ownLogo) <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removeLogo" wire:confirm="Remove the PDF logo and use the app logo instead?">Remove</button> @endif
                        <span wire:loading wire:target="logo" class="text-xs text-stone-500">Uploading…</span>
                    </div>
                    <p class="mt-1 text-xs text-stone-500">{{ $ownLogo ? 'Using the logo uploaded here.' : ($logoPreview ? 'Using the app logo.' : '') }}</p>
                </x-field>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Header (top of every page)</h2></div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <x-field label="Layout" name="form.header_style" class="md:col-span-2">
                    <select class="input" wire:model="form.header_style">
                        @foreach (\App\Support\PdfDesign::HEADER_STYLES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Company name" name="form.header_title" hint="Empty = {{ setting('company_name') }} (from Settings → General)."><input class="input" type="text" wire:model="form.header_title" maxlength="80"></x-field>
                <x-field label="Tagline" name="form.header_tagline" hint="Optional small line, e.g. Web Design · Digital Marketing."><input class="input" type="text" wire:model="form.header_tagline" maxlength="80"></x-field>
                <x-field label="Address and contact lines" name="form.header_details" class="md:col-span-2" hint="Up to four lines. Empty = your address, email, phone and GSTIN from Settings → General.">
                    <textarea class="input" rows="4" wire:model="form.header_details" placeholder="{{ implode("\n", \App\Support\PdfDesign::headerLines()) }}"></textarea>
                </x-field>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Footer (bottom of every page)</h2></div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <x-field label="Footer text" name="form.footer_text" class="md:col-span-2" hint="Up to two lines, e.g. a thank-you line, bank details or “This is a computer-generated document”. Empty = the invoice footer note from Settings → General.">
                    <textarea class="input" rows="2" wire:model="form.footer_text" maxlength="300" placeholder="{{ setting('invoice_footer') }}"></textarea>
                </x-field>
                <x-field label="Contact line" name="form.footer_contact" hint="Empty = your email and phone."><input class="input" type="text" wire:model="form.footer_contact" maxlength="160" placeholder="www.yourcompany.com  ·  hello@yourcompany.com"></x-field>
                <x-field label="Page numbers" name="form.page_numbers">
                    <select class="input" wire:model="form.page_numbers"><option value="1">Show “Page 1”, “Page 2”…</option><option value="0">Hide</option></select>
                </x-field>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Style</h2></div>
            <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                <x-field label="Border around each page" name="form.page_border">
                    <select class="input" wire:model="form.page_border"><option value="1">Show</option><option value="0">Hide</option></select>
                </x-field>
                <x-field label="Tables" name="form.table_style">
                    <select class="input" wire:model="form.table_style">
                        @foreach (\App\Support\PdfDesign::TABLE_STYLES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Accent colour" name="form.accent_color" hint="Headings, borders and table headers. Empty = your brand colour.">
                    <div class="flex items-center gap-2">
                        <input type="color" class="h-9 w-12 cursor-pointer rounded border border-stone-300 bg-white p-0.5" value="{{ \App\Support\Branding::validHex($form['accent_color']) ?: \App\Support\Branding::shade(700) }}" x-on:input="$wire.set('form.accent_color', $event.target.value)" aria-label="Pick accent colour">
                        <input class="input" type="text" wire:model.live.debounce.400ms="form.accent_color" placeholder="{{ \App\Support\Branding::shade(700) }}" maxlength="7">
                        @if ($form['accent_color'] !== '') <button type="button" class="btn-ghost btn-sm" wire:click="$set('form.accent_color', '')">Reset</button> @endif
                    </div>
                </x-field>
            </div>
        </section>

        <div class="flex justify-end gap-2 pb-6">
            <a href="{{ route('settings.pdf.sample') }}" target="_blank" rel="noopener" class="btn-secondary">View sample PDF</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,logo">Save changes</button>
        </div>
    </form>
</div>
