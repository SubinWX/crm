<div>
    <div class="mb-1 text-sm"><a href="{{ $quote ? route('quotes.show', $quote) : route('quotes.index') }}" class="link">← {{ $quote ? $quote->number : 'Quotes & Proposals' }}</a></div>
    <x-page-header :title="$quote ? 'Edit '.$quote->number.($quote->revision > 1 ? ' · revision '.$quote->revision : '') : 'New Quote'" subtitle="Saved as a draft. Everything you choose here is stored with the quote.">
        <a href="{{ $quote ? route('quotes.show', $quote) : route('quotes.index') }}" class="btn-secondary">Cancel</a>
    </x-page-header>

    <form wire:submit="save" class="space-y-5">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Customer and recipient</h2></div>
            <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                <x-field label="Quote for" name="party">
                    <div class="flex gap-2">
                        @foreach (['client' => 'Client', 'lead' => 'Lead'] as $key => $label)
                            <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm has-checked:border-brand-500 has-checked:bg-brand-50">
                                <input type="radio" name="party" value="{{ $key }}" wire:model.live="party"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </x-field>
                @if ($party === 'client')
                    <x-field label="Client" name="client_id" required>
                        <select class="input" wire:model.live="client_id"><option value="">Select client…</option>@foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach</select>
                    </x-field>
                    <x-field label="Recipient" name="contact_id" hint="{{ $client_id && $contacts->isEmpty() ? 'No saved contacts. Type the recipient below.' : 'Pick a saved contact or type below.' }}" class="xl:col-span-2">
                        <select class="input" wire:model.live="contact_id" @disabled($contacts->isEmpty())><option value="">Other (type below)</option>@foreach ($contacts as $contact) <option value="{{ $contact->id }}">{{ $contact->label() }}</option> @endforeach</select>
                    </x-field>
                @else
                    <x-field label="Lead" name="lead_id" required class="xl:col-span-3">
                        <select class="input" wire:model.live="lead_id"><option value="">Select lead…</option>@foreach ($leads as $l) <option value="{{ $l->id }}">{{ $l->name }} · {{ \App\Models\Lead::STAGES[$l->stage] ?? $l->stage }}</option> @endforeach</select>
                    </x-field>
                @endif
                <x-field label="Recipient name" name="form.recipient_name" required><input class="input" type="text" wire:model="form.recipient_name"></x-field>
                <x-field label="Recipient email" name="form.recipient_email"><input class="input" type="email" wire:model="form.recipient_email"></x-field>
                <x-field label="Company" name="form.recipient_company"><input class="input" type="text" wire:model="form.recipient_company"></x-field>
                <x-field label="Address" name="form.recipient_address"><input class="input" type="text" wire:model="form.recipient_address"></x-field>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Quote details</h2></div>
            <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                <x-field label="Title" name="form.title" required class="md:col-span-2"><input class="input" type="text" wire:model="form.title" placeholder="e.g. Website build, hosting and maintenance"></x-field>
                <x-field label="Quote number" name="form.number" required><input class="input" type="text" wire:model="form.number"></x-field>
                <x-field label="Currency" name="form.currency" required>
                    <select class="input" wire:model.live="form.currency">@foreach (\App\Livewire\Settings\Index::CURRENCIES as $code => $label) <option value="{{ $code }}">{{ $code }}</option> @endforeach</select>
                </x-field>
                <x-field label="Issue date" name="form.issue_date" required><input class="input" type="date" wire:model="form.issue_date"></x-field>
                <x-field label="Expiry date" name="form.expiry_date"><input class="input" type="date" wire:model="form.expiry_date"></x-field>
                <x-field label="Follow-up date" name="form.follow_up_date" hint="Shows on the dashboard and under Tasks & Follow-ups when due."><input class="input" type="date" wire:model="form.follow_up_date"></x-field>
            </div>
        </section>

        <section class="card border-brand-200 bg-brand-50/50 p-4">
            <div class="flex flex-wrap items-end gap-3">
                <x-field label="Add a template" class="min-w-64 flex-1" hint="Add one or more, e.g. a website build plus annual hosting plus monthly maintenance.">
                    <select class="input" wire:model="templateToAdd" aria-label="Template">
                        <option value="">Choose a template…</option>
                        @foreach ($templates as $t) <option value="{{ $t->id }}">{{ $t->name }}{{ $t->category ? ' · '.$t->category : '' }}</option> @endforeach
                    </select>
                </x-field>
                <button type="button" class="btn-primary mb-6" wire:click="addTemplate"><x-icon name="plus" /> Add template</button>
            </div>
            @if ($templateNames) <p class="text-xs text-stone-600">In this quote: @foreach (array_unique($templateNames) as $name) <span class="badge-gray">{{ $name }}</span> @endforeach</p> @endif
            @if ($templates->isEmpty()) <p class="text-xs text-stone-600">No templates yet. @can('admin') <a class="link" href="{{ route('quote-templates.index') }}">Create one in Settings → Quote Templates</a>, or @endcan build the quote by hand below.</p> @endif
        </section>

        @include('livewire.quotes._sections')
        @include('livewire.quotes._items', ['currency' => $form['currency']])

        <section class="card grid gap-5 p-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <div class="space-y-4">
                <x-field label="Discount (%)" name="form.discount_percent" hint="Applied to every line."><input class="input" type="number" step="0.01" min="0" max="100" wire:model.live.debounce.400ms="form.discount_percent"></x-field>
                <x-field label="Tax / GST (%)" name="form.tax_rate"><input class="input" type="number" step="0.01" min="0" max="100" wire:model.live.debounce.400ms="form.tax_rate"></x-field>
            </div>
            @include('livewire.quotes._totals', ['currency' => $form['currency'], 'discountPercent' => $form['discount_percent'] ?: 0, 'taxRate' => $form['tax_rate'] ?: 0])
        </section>

        <section class="card p-4">
            <x-field label="Internal notes (not printed)" name="form.notes"><textarea class="input" rows="2" wire:model="form.notes"></textarea></x-field>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ $quote ? route('quotes.show', $quote) : route('quotes.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save draft</button>
        </div>
    </form>
</div>
