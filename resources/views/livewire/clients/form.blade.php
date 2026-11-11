<div>
    <x-page-header :title="$client ? 'Edit '.$client->name : 'New Client'" :subtitle="$client ? null : 'Every active Super Admin is notified in-app and by email when the client is saved.'">
        <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}" class="btn-secondary">Cancel</a>
    </x-page-header>

    <form wire:submit="save" class="space-y-5">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Client details</h2></div>
            <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
                <x-field label="Brand logo" name="logo" hint="JPG, PNG or WebP, up to 2 MB." class="md:col-span-2 xl:col-span-3">
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($logo && ! $errors->has('logo'))
                            <img src="{{ $logo->temporaryUrl() }}" alt="New logo preview" class="size-16 rounded-lg border border-stone-200 bg-white object-contain p-1">
                        @elseif ($client?->logo_path && ! $removeLogo)
                            <img src="{{ $client->logoUrl() }}" alt="{{ $client->name }} logo" class="size-16 rounded-lg border border-stone-200 bg-white object-contain p-1">
                        @else
                            <span class="grid size-16 place-items-center rounded-lg border border-dashed border-stone-300 bg-stone-50 text-xs text-stone-400">No logo</span>
                        @endif
                        <div class="min-w-64 flex-1">
                            <input type="file" class="input" accept="image/png,image/jpeg,image/webp" wire:model="logo" aria-label="Brand logo">
                            <p wire:loading wire:target="logo" class="mt-1 text-xs text-stone-500">Uploading…</p>
                        </div>
                        @if ($client?->logo_path && ! $logo)
                            <label class="flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" class="rounded border-stone-300" wire:model.live="removeLogo"> Remove current logo</label>
                        @endif
                    </div>
                </x-field>
                <x-field label="Client / company name" name="form.name" required><input class="input" type="text" wire:model="form.name" autofocus></x-field>
                <x-field label="Primary contact name" name="form.contact_name"><input class="input" type="text" wire:model="form.contact_name"></x-field>
                <x-field label="Email" name="form.email"><input class="input" type="email" wire:model="form.email"></x-field>
                <x-field label="Phone 1" name="form.phone1"><input class="input" type="tel" wire:model="form.phone1"></x-field>
                <x-field label="Phone 2" name="form.phone2"><input class="input" type="tel" wire:model="form.phone2"></x-field>
                <x-field label="Website URL" name="form.website"><input class="input" type="text" wire:model="form.website" placeholder="https://"></x-field>
                <x-field label="Location" name="form.location"><input class="input" type="text" wire:model="form.location" placeholder="City, State"></x-field>
                <x-field label="Preferred communication channel" name="form.preferred_channel">
                    <select class="input" wire:model="form.preferred_channel">
                        <option value="">Not set</option>
                        @foreach (\App\Models\Client::CHANNELS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Billing address" name="form.billing_address" class="md:col-span-2 xl:col-span-3"><textarea class="input" rows="2" wire:model="form.billing_address"></textarea></x-field>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Business details</h2></div>
            <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                <x-field label="Industry" name="form.industry_id">
                    <select class="input" wire:model="form.industry_id">
                        <option value="">Select…</option>
                        @foreach ($industries as $o) <option value="{{ $o->id }}">{{ $o->name }}{{ $o->is_active ? '' : ' (inactive)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Join date" name="form.join_date"><input class="input" type="date" wire:model="form.join_date"></x-field>
                <x-field label="Client status" name="form.status" required>
                    <select class="input" wire:model="form.status">
                        @foreach (\App\Models\Client::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Assigned account manager" name="form.account_manager_id">
                    <select class="input" wire:model="form.account_manager_id">
                        <option value="">Unassigned</option>
                        @foreach ($managers as $o) <option value="{{ $o->id }}">{{ $o->name }}{{ $o->is_active ? '' : ' (inactive)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Work types" name="workTypeIds" hint="Select every service this client takes." class="md:col-span-2 xl:col-span-4">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($workTypes as $o)
                            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm has-checked:border-brand-500 has-checked:bg-brand-50">
                                <input type="checkbox" class="rounded border-stone-300" value="{{ $o->id }}" wire:model="workTypeIds"> {{ $o->name }}{{ $o->is_active ? '' : ' (inactive)' }}
                            </label>
                        @endforeach
                    </div>
                </x-field>
                <x-field label="Notes" name="form.notes" class="md:col-span-2 xl:col-span-4"><textarea class="input" rows="3" wire:model="form.notes"></textarea></x-field>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Acquisition</h2></div>
                <div class="grid gap-4 p-4 md:grid-cols-2">
                    <x-field label="Client By" name="form.acquisition_source_id" hint="How the client was acquired.">
                        <select class="input" wire:model="form.acquisition_source_id">
                            <option value="">Select…</option>
                            @foreach ($sources as $o) <option value="{{ $o->id }}">{{ $o->name }}{{ $o->is_active ? '' : ' (inactive)' }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Referred By" name="form.referrer_id" hint="Person or partner who referred them.">
                        <select class="input" wire:model="form.referrer_id">
                            <option value="">No referrer</option>
                            @foreach ($referrers as $o) <option value="{{ $o->id }}">{{ $o->name }}{{ $o->is_active ? '' : ' (inactive)' }}</option> @endforeach
                        </select>
                    </x-field>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Tax</h2></div>
                <div class="grid gap-4 p-4 md:grid-cols-2">
                    <x-field label="Tax ID / GSTIN" name="form.tax_id"><input class="input" type="text" wire:model="form.tax_id"></x-field>
                    <p class="self-end pb-2 text-xs text-stone-500">Billing, approval and day-to-day contacts are kept on the client's <strong>Contacts</strong> tab{{ $client ? '' : ' after you save' }}.</p>
                </div>
            </section>
        </div>

        @unless ($client)
            <section class="card">
                <div class="card-header">
                    <label class="flex cursor-pointer items-center gap-2 card-title">
                        <input type="checkbox" class="rounded border-stone-300" wire:model.live="addHosting"> Add a hosting record now
                    </label>
                    <span class="text-xs text-stone-500">More can be added from the Hosting tab later.</span>
                </div>
                @if ($addHosting)
                    <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                        <x-field label="Hosted website / domain" name="hosting.domain" required><input class="input" type="text" wire:model="hosting.domain" placeholder="example.com"></x-field>
                        <x-field label="Server Type" name="hosting.server_type_id" required>
                            <select class="input" wire:model="hosting.server_type_id">
                                <option value="">Select…</option>
                                @foreach ($serverTypes as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
                            </select>
                        </x-field>
                        <x-field label="Server Chosen" name="hosting.server_provider_id" hint="Provider or plan.">
                            <select class="input" wire:model="hosting.server_provider_id">
                                <option value="">Select…</option>
                                @foreach ($providers as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
                            </select>
                        </x-field>
                        <x-field label="Billing frequency" name="hosting.billing_frequency">
                            <select class="input" wire:model="hosting.billing_frequency">
                                @foreach (\App\Models\HostingRecord::FREQUENCIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                            </select>
                        </x-field>
                        <x-field label="Billing start date" name="hosting.billing_start"><input class="input" type="date" wire:model="hosting.billing_start"></x-field>
                        <x-field label="Billing end date" name="hosting.billing_end"><input class="input" type="date" wire:model="hosting.billing_end"></x-field>
                        <x-field label="Next server payment date" name="hosting.next_payment_date"><input class="input" type="date" wire:model="hosting.next_payment_date"></x-field>
                        <x-field label="Renewal amount ({{ setting('currency', 'INR') }})" name="hosting.renewal_amount"><input class="input" type="number" step="0.01" min="0" wire:model="hosting.renewal_amount"></x-field>
                    </div>
                @endif
            </section>

            @can('finance')
                <section class="card">
                    <div class="card-header">
                        <label class="flex cursor-pointer items-center gap-2 card-title">
                            <input type="checkbox" class="rounded border-stone-300" wire:model.live="addPayment"> Record a payment already received
                        </label>
                        <span class="text-xs text-stone-500">Stays unallocated until applied to an invoice.</span>
                    </div>
                    @if ($addPayment)
                        <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-5">
                            <x-field label="Payment type" name="payment.payment_method_id" required>
                                <select class="input" wire:model="payment.payment_method_id">
                                    <option value="">Select…</option>
                                    @foreach ($paymentMethods as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
                                </select>
                            </x-field>
                            <x-field label="Amount received" name="payment.amount" required><input class="input" type="number" step="0.01" min="0" wire:model="payment.amount"></x-field>
                            <x-field label="Payment date" name="payment.payment_date" required><input class="input" type="date" wire:model="payment.payment_date"></x-field>
                            <x-field label="Payment To" name="payment.received_by" required>
                                <select class="input" wire:model="payment.received_by">
                                    <option value="">Select Super Admin…</option>
                                    @foreach ($superAdmins as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
                                </select>
                            </x-field>
                            <x-field label="Transaction reference" name="payment.reference"><input class="input" type="text" wire:model="payment.reference"></x-field>
                        </div>
                    @endif
                </section>
            @endcan
        @endunless

        <div class="flex items-center justify-end gap-2">
            <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="logo,save">{{ $client ? 'Save changes' : 'Add client' }}</button>
        </div>
    </form>
</div>
