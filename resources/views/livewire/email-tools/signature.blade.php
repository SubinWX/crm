<div>
    <x-page-header title="Email Signatures" subtitle="Fill in your details, pick a layout, then copy the signature into Gmail, Outlook or any mail program.">
        <button type="button" class="btn-secondary" wire:click="startOver" wire:confirm="Refill every field from your CRM profile and company settings? Your photo is kept.">Refill from my profile</button>
        <button type="button" class="btn-secondary" wire:click="startNew" wire:confirm="Start a new signature? Unsaved changes to this one are lost.">New signature</button>
    </x-page-header>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,34rem)]">
        <div class="space-y-5">
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ $loadedId ? 'Editing a saved signature' : 'New signature' }}</h2>
                    <a href="{{ route('email-tools.saved-signatures') }}" class="link text-sm">Saved signatures ({{ $savedCount }}) →</a>
                </div>
                <div class="flex flex-wrap items-start gap-2 p-4">
                    <div class="min-w-56 flex-1"><input class="input" type="text" wire:model="name" maxlength="120" placeholder="Name this signature, e.g. Reply, Sales" aria-label="Signature name">@error('name') <p class="field-error">{{ $message }}</p> @enderror</div>
                    <button type="button" class="btn-primary" wire:click="save">{{ $loadedId ? 'Save changes' : 'Save signature' }}</button>
                    @if ($loadedId) <button type="button" class="btn-secondary" wire:click="save(true)">Save as new</button> @endif
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Layout</h2></div>
                <div class="grid gap-2 p-4 sm:grid-cols-2 2xl:grid-cols-4">
                    @foreach (\App\Support\EmailBuilder::SIGNATURE_LAYOUTS as $key => $label)
                        <label class="cursor-pointer rounded-lg border border-stone-300 bg-white p-3 has-checked:border-brand-500 has-checked:bg-brand-50" wire:key="layout-{{ $key }}">
                            <span class="flex items-center gap-2 text-sm font-semibold text-stone-900"><input type="radio" name="layout" value="{{ $key }}" wire:model.live="form.layout" class="text-brand-600"> {{ str($label)->before(':') }}</span>
                            <span class="mt-1 block text-xs text-stone-500">{{ str($label)->after(': ')->ucfirst() }}</span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Your details</h2></div>
                <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                    <x-field label="Full name" name="form.name" required><input class="input" type="text" wire:model.live.debounce.400ms="form.name" maxlength="80"></x-field>
                    <x-field label="Designation" name="form.title"><input class="input" type="text" wire:model.live.debounce.400ms="form.title" maxlength="80" placeholder="e.g. Creative Director"></x-field>
                    <x-field label="Company" name="form.company"><input class="input" type="text" wire:model.live.debounce.400ms="form.company" maxlength="80"></x-field>
                    <x-field label="Email" name="form.email"><input class="input" type="email" wire:model.live.debounce.400ms="form.email" maxlength="120"></x-field>
                    <x-field label="Office phone" name="form.phone"><input class="input" type="tel" wire:model.live.debounce.400ms="form.phone" maxlength="30"></x-field>
                    <x-field label="Mobile" name="form.mobile"><input class="input" type="tel" wire:model.live.debounce.400ms="form.mobile" maxlength="30"></x-field>
                    <x-field label="Website" name="form.website"><input class="input" type="text" wire:model.live.debounce.400ms="form.website" placeholder="www.yourcompany.com"></x-field>
                    <x-field label="Address" name="form.address"><input class="input" type="text" wire:model.live.debounce.400ms="form.address" maxlength="200"></x-field>
                    <x-field label="Small print" name="form.note" class="md:col-span-2" hint="Optional line under the signature, such as a confidentiality note."><textarea class="input" rows="2" wire:model.live.debounce.500ms="form.note" maxlength="400"></textarea></x-field>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Photo and logo</h2></div>
                <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                    <x-field label="Headshot" name="photo" class="md:col-span-2" hint="JPG or PNG, up to 4 MB. It is cut to a square automatically. Not used in the Compact layout.">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="size-16 shrink-0 overflow-hidden rounded-full border border-stone-200 bg-stone-100">
                                @if ($form['photo_url'] !== '') <img src="{{ $form['photo_url'] }}" alt="Current headshot" class="size-full object-cover"> @endif
                            </div>
                            <input type="file" class="input max-w-xs" wire:model="photo" accept=".jpg,.jpeg,.png,.webp" aria-label="Headshot">
                            <button type="button" class="btn-secondary btn-sm" wire:click="useProfilePicture">Use my CRM picture</button>
                            @if ($form['photo_url'] !== '') <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removePhoto">Remove</button> @endif
                            <span wire:loading wire:target="photo" class="text-xs text-stone-500">Uploading…</span>
                        </div>
                    </x-field>
                    <x-field label="Or a photo already online" name="form.photo_url" hint="The web address of an image, if you prefer."><input class="input" type="text" wire:model.live.debounce.500ms="form.photo_url" placeholder="https://"></x-field>
                    <x-field label="Photo shape" name="form.photo_shape">
                        <select class="input" wire:model.live="form.photo_shape">
                            @foreach (\App\Support\EmailBuilder::PHOTO_SHAPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                    @if ($form['layout'] === 'showcase')
                        <x-field label="Colour behind the photo" name="form.photo_bg" class="md:col-span-2" hint="For a cut-out photo with a transparent background. Pick the colour first, then upload the photo.">
                            <div class="flex max-w-xs items-center gap-2">
                                <input type="color" class="h-9 w-12 cursor-pointer rounded border border-stone-300 bg-white p-0.5" value="{{ \App\Support\Branding::validHex($form['photo_bg']) ?: '#ffffff' }}" x-on:change="$wire.set('form.photo_bg', $event.target.value)" aria-label="Pick the colour behind the photo">
                                <input class="input" type="text" wire:model.live.debounce.500ms="form.photo_bg" maxlength="7" placeholder="#6d3df5">
                            </div>
                        </x-field>
                    @endif
                    <x-field label="Company logo address" name="form.logo_url" class="md:col-span-2" hint="A PNG or JPG that is online. SVG logos are not shown by most email programs. Clear it to leave the logo out."><input class="input" type="text" wire:model.live.debounce.500ms="form.logo_url" placeholder="https://yourcompany.com/logo.png"></x-field>
                </div>
            </section>

            @if ($form['layout'] === 'showcase')
                <section class="card" wire:key="showcase-extras">
                    <div class="card-header"><h2 class="card-title">Highlight line and banner</h2><span class="text-xs text-stone-500">Showcase layout</span></div>
                    <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                        <x-field label="Highlight label" name="form.highlight_label" hint="Shown in bold after a gold star."><input class="input" type="text" wire:model.live.debounce.400ms="form.highlight_label" maxlength="40" placeholder="Rate us"></x-field>
                        <x-field label="Highlight link address" name="form.highlight_url"><input class="input" type="text" wire:model.live.debounce.500ms="form.highlight_url" placeholder="https://"></x-field>
                        <x-field label="Highlight link text" name="form.highlight_text" class="md:col-span-2"><input class="input" type="text" wire:model.live.debounce.400ms="form.highlight_text" maxlength="120" placeholder="How happy are you with our services?"></x-field>
                        <x-field label="Banner image address" name="form.banner_url" hint="A wide image that is online, about 1200 × 180 pixels. Design it with your own text and button drawn in."><input class="input" type="text" wire:model.live.debounce.500ms="form.banner_url" placeholder="https://yourcompany.com/email-banner.png"></x-field>
                        <x-field label="Banner opens" name="form.banner_link" hint="Where a click on the banner goes."><input class="input" type="text" wire:model.live.debounce.500ms="form.banner_link" placeholder="https://yourcompany.com"></x-field>
                    </div>
                </section>
            @endif

            <section class="card">
                <div class="card-header"><h2 class="card-title">Colours and font</h2></div>
                <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-3">
                    @foreach (['accent' => ['Accent colour', \App\Support\Branding::shade(600)], 'text_color' => ['Text colour', '#1c1917']] as $key => [$label, $fallback])
                        <x-field :label="$label" name="form.{{ $key }}">
                            <div class="flex items-center gap-2">
                                <input type="color" class="h-9 w-12 cursor-pointer rounded border border-stone-300 bg-white p-0.5" value="{{ \App\Support\Branding::validHex($form[$key]) ?: $fallback }}" x-on:change="$wire.set('form.{{ $key }}', $event.target.value)" aria-label="Pick {{ strtolower($label) }}">
                                <input class="input" type="text" wire:model.live.debounce.500ms="form.{{ $key }}" maxlength="7" placeholder="{{ $fallback }}">
                            </div>
                        </x-field>
                    @endforeach
                    <x-field label="Font" name="form.font" hint="Only fonts every computer and phone has.">
                        <select class="input" wire:model.live="form.font">
                            @foreach (\App\Support\EmailBuilder::FONTS as $key => [$label]) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Social media links</h2><span class="text-xs text-stone-500">Only the ones you fill in are shown</span></div>
                <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                    <x-field label="Show them as" name="form.social_style">
                        <select class="input" wire:model.live="form.social_style">
                            @foreach (\App\Support\EmailBuilder::SOCIAL_STYLES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Badge colours" name="form.social_brand_colors">
                        <select class="input" wire:model.live="form.social_brand_colors"><option value="1">Each network’s own colour</option><option value="0">My accent colour</option></select>
                    </x-field>
                    @foreach (\App\Support\EmailBuilder::SOCIALS as $network => [$label])
                        <x-field :label="$label" name="form.{{ $network }}"><input class="input" type="text" wire:model.live.debounce.500ms="form.{{ $network }}" placeholder="{{ $network === 'whatsapp' ? 'https://wa.me/919876543210' : 'https://' }}"></x-field>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="space-y-4">
            @include('livewire.email-tools._preview', [
                'html' => $html, 'filename' => 'email-signature.html', 'richLabel' => 'Copy signature',
                'document' => '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:16px;background:#ffffff;">'.$html.'</body></html>',
            ])

            @if ($localImages)
                <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">
                    <p class="font-semibold">The photo or logo will not show for the people you email yet.</p>
                    <p>This CRM is running only on this computer, so nobody else can load its images. Once it is on a public web address they will work. Until then, paste the address of an image that is already online.</p>
                </div>
            @endif

            <section class="card p-4 text-sm leading-relaxed text-stone-700">
                <h2 class="card-title mb-2">How to add it to your email</h2>
                <ol class="list-decimal space-y-1.5 pl-5">
                    <li>Press <strong>Copy signature</strong>.</li>
                    <li><strong>Gmail:</strong> Settings (gear) → See all settings → General → Signature → Create new, then paste.</li>
                    <li><strong>Outlook:</strong> Settings → Mail → Compose and reply → Email signature, then paste.</li>
                    <li><strong>Apple Mail and phone apps:</strong> paste into the signature box in the mail settings.</li>
                </ol>
                <p class="mt-2 text-xs text-stone-500">If a mail program asks for code, use <strong>Copy HTML code</strong>. The signature is built with tables and plain styles, which is what email programs understand.</p>
            </section>
        </div>
    </div>
</div>
