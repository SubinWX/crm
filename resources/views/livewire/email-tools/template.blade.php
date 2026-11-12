<div>
    <x-page-header title="Email Templates" subtitle="Build a branded email with a header and footer, then copy it into your mail program or email service.">
        <button type="button" class="btn-secondary" wire:click="startNew" wire:confirm="Start a new template? Unsaved changes to this one are lost.">New template</button>
    </x-page-header>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,40rem)]">
        <div class="space-y-5">
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ $loadedId ? 'Editing a saved template' : 'New template' }}</h2>
                    <a href="{{ route('email-tools.saved-templates') }}" class="link text-sm">Saved templates ({{ $savedCount }}) →</a>
                </div>
                <div class="flex flex-wrap items-start gap-2 p-4">
                    <div class="min-w-56 flex-1"><input class="input" type="text" wire:model="name" maxlength="120" placeholder="Name this template, e.g. Project update" aria-label="Template name">@error('name') <p class="field-error">{{ $message }}</p> @enderror</div>
                    <button type="button" class="btn-primary" wire:click="save">{{ $loadedId ? 'Save changes' : 'Save template' }}</button>
                    @if ($loadedId) <button type="button" class="btn-secondary" wire:click="save(true)">Save as new</button> @endif
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Layout and style</h2></div>
                <div class="space-y-4 p-4 sm:p-5">
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach (\App\Support\EmailBuilder::TEMPLATE_LAYOUTS as $key => $label)
                            <label class="cursor-pointer rounded-lg border border-stone-300 bg-white p-3 has-checked:border-brand-500 has-checked:bg-brand-50" wire:key="layout-{{ $key }}">
                                <span class="flex items-center gap-2 text-sm font-semibold text-stone-900"><input type="radio" name="layout" value="{{ $key }}" wire:model.live="form.layout" class="text-brand-600"> {{ str($label)->before(':') }}</span>
                                <span class="mt-1 block text-xs text-stone-500">{{ str($label)->after(': ')->ucfirst() }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach (['accent' => ['Accent colour', \App\Support\Branding::shade(600)], 'background' => ['Page background', '#f3f2ef']] as $key => [$label, $fallback])
                            <x-field :label="$label" name="form.{{ $key }}">
                                <div class="flex items-center gap-2">
                                    <input type="color" class="h-9 w-12 cursor-pointer rounded border border-stone-300 bg-white p-0.5" value="{{ \App\Support\Branding::validHex($form[$key]) ?: $fallback }}" x-on:change="$wire.set('form.{{ $key }}', $event.target.value)" aria-label="Pick {{ strtolower($label) }}">
                                    <input class="input" type="text" wire:model.live.debounce.500ms="form.{{ $key }}" maxlength="7" placeholder="{{ $fallback }}">
                                </div>
                            </x-field>
                        @endforeach
                        <x-field label="Font" name="form.font">
                            <select class="input" wire:model.live="form.font">
                                @foreach (\App\Support\EmailBuilder::FONTS as $key => [$label]) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                            </select>
                        </x-field>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Header</h2></div>
                <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                    <x-field label="Company name" name="form.company" hint="Shown when there is no logo, and used in the footer."><input class="input" type="text" wire:model.live.debounce.400ms="form.company" maxlength="80"></x-field>
                    <x-field label="Website" name="form.website" hint="The logo links here; also shown in the footer."><input class="input" type="text" wire:model.live.debounce.400ms="form.website" placeholder="www.yourcompany.com"></x-field>
                    <x-field label="Logo address" name="form.logo_url" class="md:col-span-2" hint="A PNG or JPG that is online. On the Band layout use a light or white logo. Clear it to show the company name instead."><input class="input" type="text" wire:model.live.debounce.500ms="form.logo_url" placeholder="https://yourcompany.com/logo.png"></x-field>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Banner image</h2><span class="text-xs text-stone-500">Optional</span></div>
                <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                    <x-field label="Banner image address" name="form.hero_url" class="md:col-span-2" hint="A picture that is already online, about 1200 pixels wide. It is placed as a plain image across the full width and scales down on phones. Leave empty for no banner.">
                        <input class="input" type="text" wire:model.live.debounce.500ms="form.hero_url" placeholder="https://yourcompany.com/email-banner.jpg">
                    </x-field>
                    <x-field label="Where it goes" name="form.banner_position">
                        <select class="input" wire:model.live="form.banner_position">
                            @foreach (\App\Support\EmailBuilder::BANNER_POSITIONS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Clicking it opens" name="form.hero_link" hint="Optional link."><input class="input" type="text" wire:model.live.debounce.500ms="form.hero_link" placeholder="https://"></x-field>
                    <x-field label="Description of the picture" name="form.banner_alt" class="md:col-span-2" hint="Shown when images are switched off, and read aloud by screen readers."><input class="input" type="text" wire:model.live.debounce.500ms="form.banner_alt" maxlength="120" placeholder="e.g. Festive offer: 20% off website packages"></x-field>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Message</h2><button type="button" class="btn-secondary btn-sm" wire:click="$refresh">Update preview</button></div>
                <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                    <x-field label="Heading" name="form.heading" class="md:col-span-2"><input class="input" type="text" wire:model.live.debounce.400ms="form.heading" maxlength="120"></x-field>
                    <x-field label="Message" name="form.body" class="md:col-span-2" hint="Type or paste your message, then press Update preview. Bold, bullets, headings and links are kept.">
                        <x-editor model="form.body" :value="$form['body']" min-height="11rem" label="Message" />
                    </x-field>
                    <x-field label="Button text" name="form.button_label" hint="Leave empty for no button."><input class="input" type="text" wire:model.live.debounce.400ms="form.button_label" maxlength="40" placeholder="e.g. View the design"></x-field>
                    <x-field label="Button address" name="form.button_url"><input class="input" type="text" wire:model.live.debounce.500ms="form.button_url" placeholder="https://"></x-field>
                    <x-field label="Sign-off" name="form.signoff"><textarea class="input" rows="2" wire:model.live.debounce.500ms="form.signoff" maxlength="200"></textarea></x-field>
                    <x-field label="Inbox preview line" name="form.preheader" hint="The grey text mail apps show after the subject. Not visible inside the email."><textarea class="input" rows="2" wire:model.live.debounce.500ms="form.preheader" maxlength="150"></textarea></x-field>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Footer</h2></div>
                <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                    <x-field label="Company name" name="form.footer_text" hint="Shown in bold at the top of the footer."><input class="input" type="text" wire:model.live.debounce.500ms="form.footer_text" maxlength="300"></x-field>
                    <x-field label="Address" name="form.footer_address" hint="Each line you type is a line in the footer. Clear it to leave the address out."><textarea class="input" rows="3" wire:model.live.debounce.500ms="form.footer_address" maxlength="300" placeholder="Building, street&#10;City, State PIN"></textarea></x-field>
                    <x-field label="Phone" name="form.footer_phone"><input class="input" type="tel" wire:model.live.debounce.500ms="form.footer_phone" maxlength="30"></x-field>
                    <x-field label="Email" name="form.footer_email"><input class="input" type="email" wire:model.live.debounce.500ms="form.footer_email" maxlength="120"></x-field>
                    <x-field label="Small print" name="form.footer_note" class="md:col-span-2" hint="Why they received this email, or how to stop receiving it."><textarea class="input" rows="2" wire:model.live.debounce.500ms="form.footer_note" maxlength="300"></textarea></x-field>
                    <x-field label="Social links shown as" name="form.social_style">
                        <select class="input" wire:model.live="form.social_style">
                            @foreach (\App\Support\EmailBuilder::SOCIAL_STYLES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Badge colours" name="form.social_brand_colors">
                        <select class="input" wire:model.live="form.social_brand_colors"><option value="0">My accent colour</option><option value="1">Each network’s own colour</option></select>
                    </x-field>
                    @foreach (\App\Support\EmailBuilder::SOCIALS as $network => [$label])
                        <x-field :label="$label" name="form.{{ $network }}"><input class="input" type="text" wire:model.live.debounce.500ms="form.{{ $network }}" placeholder="https://"></x-field>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="space-y-4">
            @include('livewire.email-tools._preview', ['html' => $html, 'document' => $html, 'filename' => (str($name)->slug()->toString() ?: 'email-template').'.html', 'richLabel' => 'Copy email'])

            @if ($localImages)
                <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">
                    <p class="font-semibold">The logo will not show for the people you email yet.</p>
                    <p>This CRM is running only on this computer, so nobody else can load its images. Once it is on a public web address they will work. Until then, paste the address of a logo that is already online.</p>
                </div>
            @endif

            <section class="card p-4 text-sm leading-relaxed text-stone-700">
                <h2 class="card-title mb-2">How to use it</h2>
                <ul class="list-disc space-y-1.5 pl-5">
                    <li><strong>Gmail or Outlook:</strong> press <strong>Copy email</strong> and paste into a new message.</li>
                    <li><strong>Mailchimp, Brevo, Zoho Campaigns and similar:</strong> choose “paste your own code” there and use <strong>Copy HTML code</strong>.</li>
                    <li><strong>Keep a file:</strong> <strong>Download .html</strong> saves it to open or share later.</li>
                </ul>
                <p class="mt-2 text-xs text-stone-500">The email is 600 pixels wide on a computer and fills the screen on a phone. Use the Phone button above the preview to check.</p>
            </section>
        </div>
    </div>
</div>
