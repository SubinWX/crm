@php
    $val = fn ($v) => filled($v) ? $v : '—';
    $personal = [
        'Name' => $person->name,
        'Work email' => $person->email,
        'Personal email' => $val($person->personal_email),
        'Phone' => $val($person->phone),
        'Alternate phone' => $val($person->alt_phone),
        'Date of birth' => fmt_date($person->date_of_birth),
        'Gender' => $val(\App\Models\User::GENDERS[$person->gender] ?? null),
        'Address' => $val($person->address),
        'City' => $val($person->city),
        'State' => $val($person->state),
        'Pincode' => $val($person->pincode),
        'Nationality' => $val($person->nationality),
        'Blood group' => $val($person->blood_group),
        'Joined on' => fmt_date($person->joined_on, 'Not set'),
        'Role' => $person->role_label,
    ];
    $emergency = [
        'Contact name' => $val($person->emergency_name),
        'Relationship' => $val($person->emergency_relation),
        'Phone' => $val($person->emergency_phone),
        'Alternate phone' => $val($person->emergency_alt_phone),
    ];
@endphp
<div>
    <x-page-header :title="$own ? 'My Profile' : $person->name" :subtitle="$own ? 'Your details, the company rules, your meeting notes and the devices issued to you.' : 'Profile of '.$person->name.' · '.$person->role_label">
        @unless ($own)
            @if ($person->role === 'staff' && auth()->user()->can('staff.manage')) <a href="{{ route('staff.index') }}" class="btn-secondary">Back to Staff</a>
            @elseif ($person->role !== 'staff' && auth()->user()->can('admin')) <a href="{{ route('users.index', ['role' => $person->role]) }}" class="btn-secondary">Back to Admins</a>
            @elseif (auth()->user()->can('team.manage')) <a href="{{ route('leave', ['tab' => 'balances']) }}" class="btn-secondary">Back to Leave</a> @endif
        @endunless
    </x-page-header>

    <div class="grid items-start gap-5 lg:grid-cols-[18rem_1fr]">
        {{-- Who it is, and their leave this year --}}
        <div class="card p-5 text-center">
            <x-avatar :src="$person->avatarUrl()" :name="$person->name" size="size-28" class="mx-auto text-3xl" />
            <p class="mt-3 text-lg font-semibold text-stone-900">{{ $person->name }}</p>
            <p class="text-sm text-stone-500">{{ $person->role_label }}@unless ($person->is_active) · <span class="text-red-700">Deactivated</span>@endunless</p>
            <p class="mt-0.5 truncate text-xs text-stone-500">{{ $person->email }}</p>

            @if ($balances->isNotEmpty())
                <p class="mt-4 rounded-md bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-900">{{ fmt_days($leaveTaken) }} {{ $leaveTaken == 1 ? 'day' : 'days' }} of leave in {{ $year }}</p>
                @if ($own && auth()->user()->can('workspace'))
                    <a href="{{ route('leave', ['apply' => 1]) }}" class="btn-primary mt-2 w-full justify-center">Apply for leave</a>
                @elseif (auth()->user()->can('team.manage'))
                    <a href="{{ route('leave', ['tab' => 'balances']) }}" class="btn-secondary mt-2 w-full justify-center">Leave balances</a>
                @endif
                <div class="mt-3 grid grid-cols-3 gap-1.5">
                    @foreach ($balances as $b)
                        <div class="rounded-md border border-stone-200 px-1 py-2" title="{{ $b['type']->name }}: {{ $b['left'] === null ? fmt_days($b['used']).' taken, not paid' : fmt_days($b['left']).' left of '.fmt_days($b['allowance']) }}">
                            <p @class(['text-base font-semibold tabular-nums', 'text-red-700' => $b['left'] !== null && $b['left'] <= 0, 'text-stone-900' => $b['left'] === null || $b['left'] > 0])>{{ fmt_days($b['left'] ?? $b['used']) }}</p>
                            <p class="text-[11px] font-semibold text-stone-500">{{ $b['code'] }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-stone-500">Days left of each leave type. Loss of pay shows days taken.</p>
            @endif

            @if ($own && auth()->user()->can('workspace'))
                <div class="mt-4 flex justify-center gap-4 border-t border-stone-200 pt-3 text-sm">
                    <a href="{{ route('calendar') }}" class="font-medium text-brand-700 hover:underline">Leave calendar</a>
                    <a href="{{ route('timesheet') }}" class="font-medium text-brand-700 hover:underline">Timesheet</a>
                </div>
            @endif
        </div>

        <div class="min-w-0">
            <div class="mb-5 flex gap-6 overflow-x-auto border-b border-stone-200" role="tablist">
                @foreach (\App\Livewire\Account\Profile::TABS as $key => $label)
                    <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === $key]) wire:click="$set('tab', '{{ $key }}')">
                        {{ $label }}@if ($key === 'devices' && $deviceCount) <span class="ml-1 text-xs text-stone-400">{{ $deviceCount }}</span>@endif
                    </button>
                @endforeach
            </div>

            @if ($tab === 'details')
                <div class="space-y-5">
                    @foreach ([['Personal details', 'personal', $personal], ['Emergency contact', 'emergency', $emergency]] as [$heading, $section, $rows])
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">{{ $heading }}</h2>
                                <button type="button" class="btn-secondary btn-sm" wire:click="editDetails('{{ $section }}')">Edit</button>
                            </div>
                            <dl class="divide-y divide-stone-100 text-sm">
                                @foreach ($rows as $label => $value)
                                    <div class="grid grid-cols-[10rem_1fr] gap-3 px-4 py-2.5 sm:grid-cols-[14rem_1fr] sm:px-5">
                                        <dt class="text-stone-500">{{ $label }}</dt>
                                        <dd class="min-w-0 break-words text-stone-900">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                    <p class="text-xs text-stone-500">The name, work email and role are changed by a Super Admin in Administration.@unless ($canSetJoining) The joining date is set by a manager.@endunless</p>
                </div>
            @endif

            @if ($tab === 'policies')
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-stone-600">The company’s rules and policies. They are the same for everyone.</p>
                    @if ($canPolicies) <button type="button" class="btn-primary" wire:click="editPolicy"><x-icon name="plus" /> Add policy</button> @endif
                </div>
                <div class="space-y-3">
                    @forelse ($policies as $policy)
                        <div class="card" wire:key="policy-{{ $policy->id }}" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }">
                            <div class="flex items-center gap-3 px-4 py-3 sm:px-5">
                                <button type="button" class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-left" @click="open = !open" :aria-expanded="open">
                                    <x-icon name="chevron" class="size-4 text-stone-400 transition-transform" ::class="open && 'rotate-180'" />
                                    <span class="truncate text-sm font-semibold text-stone-900">{{ $policy->title }}</span>
                                </button>
                                <span class="hidden text-xs text-stone-500 sm:inline">Updated {{ fmt_date($policy->updated_at) }}</span>
                                @if ($canPolicies)
                                    <button type="button" class="btn-ghost btn-sm" wire:click="editPolicy({{ $policy->id }})">Edit</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="deletePolicy({{ $policy->id }})" wire:confirm="Delete the policy “{{ $policy->title }}”? This cannot be undone.">Delete</button>
                                @endif
                            </div>
                            <div x-show="open" x-collapse @unless ($loop->first) x-cloak @endunless>
                                <div class="rich border-t border-stone-100 px-4 py-4 text-sm text-stone-700 sm:px-5">{!! \App\Support\RichText::render($policy->body, autolink: true) !!}</div>
                            </div>
                        </div>
                    @empty
                        <div class="card p-8 text-center text-sm text-stone-500">No rules or policies have been added yet.@if ($canPolicies) Press <strong>Add policy</strong> to write the first one.@endif</div>
                    @endforelse
                </div>
            @endif

            @if ($tab === 'notes')
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-stone-600">{{ $own ? 'Notes of meetings held with you, such as reviews and one-to-ones.' : 'Notes of meetings held with '.$person->name.'.' }}</p>
                    @if ($manages) <button type="button" class="btn-primary" wire:click="editNote"><x-icon name="plus" /> Add meeting note</button> @endif
                </div>
                <div class="space-y-3">
                    @forelse ($notes as $note)
                        <div class="card" wire:key="note-{{ $note->id }}">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-stone-100 px-4 py-3 sm:px-5">
                                <p class="min-w-0 flex-1 text-sm font-semibold text-stone-900">{{ $note->title }}</p>
                                @unless ($note->shared) <span class="badge-amber" title="{{ $person->name }} does not see this note">Managers only</span> @endunless
                                <span class="text-xs text-stone-500">{{ fmt_date($note->held_on) }}@if ($note->author) · {{ $note->author->name }}@endif</span>
                                @if ($manages)
                                    <button type="button" class="btn-ghost btn-sm" wire:click="editNote({{ $note->id }})">Edit</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="deleteNote({{ $note->id }})" wire:confirm="Delete this meeting note? This cannot be undone.">Delete</button>
                                @endif
                            </div>
                            <div class="rich px-4 py-4 text-sm text-stone-700 sm:px-5">{!! \App\Support\RichText::render($note->notes, autolink: true) !!}</div>
                        </div>
                    @empty
                        <div class="card p-8 text-center text-sm text-stone-500">No meeting notes yet.@if ($own) They appear here when a manager adds one.@endif</div>
                    @endforelse
                </div>
            @endif

            @if ($tab === 'devices')
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">{{ $own ? 'Devices issued to you' : 'Devices issued to '.$person->name }}</h2>
                        @if (auth()->user()->isSuperAdmin()) <a href="{{ route('equipment.index', ['user' => $person->id]) }}" class="btn-secondary btn-sm">Manage in Equipment</a> @endif
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Item</th><th>Type</th><th>Brand / model</th><th>Serial number</th><th>Given on</th><th>Condition</th></tr></thead>
                            <tbody>
                                @forelse ($devices as $item)
                                    <tr wire:key="device-{{ $item->id }}">
                                        <td class="font-medium text-stone-900">{{ $item->name }}</td>
                                        <td>{{ $item->type?->name ?? '—' }}</td>
                                        <td>{{ $item->brand_model ?: '—' }}</td>
                                        <td class="font-mono text-xs">{{ $item->serial_number ?: '—' }}</td>
                                        <td class="whitespace-nowrap">{{ fmt_date($item->assigned_on) }}</td>
                                        <td>{{ \App\Models\Equipment::CONDITIONS[$item->condition] ?? $item->condition }}</td>
                                    </tr>
                                @empty
                                    <x-empty :colspan="6">No devices are issued {{ $own ? 'to you' : 'to '.$person->name }}.</x-empty>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($own) <p class="mt-2 text-xs text-stone-500">If something is wrong or missing, tell a Super Admin.</p> @endif
            @endif
        </div>
    </div>

    @if ($modal === 'personal')
        <x-modal title="Personal details">
            <form wire:submit="saveDetails" class="grid gap-4 md:grid-cols-2">
                <x-field label="Phone" name="form.phone"><input class="input" type="tel" wire:model="form.phone"></x-field>
                <x-field label="Alternate phone" name="form.alt_phone"><input class="input" type="tel" wire:model="form.alt_phone"></x-field>
                <x-field label="Personal email" name="form.personal_email"><input class="input" type="email" wire:model="form.personal_email"></x-field>
                <x-field label="Date of birth" name="form.date_of_birth"><input class="input" type="date" wire:model="form.date_of_birth"></x-field>
                <x-field label="Gender" name="form.gender">
                    <select class="input" wire:model="form.gender">
                        <option value="">Select…</option>
                        @foreach (\App\Models\User::GENDERS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Blood group" name="form.blood_group">
                    <select class="input" wire:model="form.blood_group">
                        <option value="">Select…</option>
                        @foreach (\App\Models\User::BLOOD_GROUPS as $group) <option value="{{ $group }}">{{ $group }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Address" name="form.address" class="md:col-span-2"><textarea class="input" rows="2" wire:model="form.address"></textarea></x-field>
                <x-field label="City" name="form.city"><input class="input" type="text" wire:model="form.city"></x-field>
                <x-field label="State" name="form.state"><input class="input" type="text" wire:model="form.state"></x-field>
                <x-field label="Pincode" name="form.pincode"><input class="input" type="text" wire:model="form.pincode"></x-field>
                <x-field label="Nationality" name="form.nationality"><input class="input" type="text" wire:model="form.nationality"></x-field>
                @if ($canSetJoining)
                    <x-field label="Joined on" name="form.joined_on" hint="Used for leave: casual leave goes up after 2 years."><input class="input" type="date" wire:model="form.joined_on"></x-field>
                @endif
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($modal === 'emergency')
        <x-modal title="Emergency contact" size="max-w-lg">
            <form wire:submit="saveDetails" class="grid gap-4 sm:grid-cols-2">
                <x-field label="Contact name" name="form.emergency_name"><input class="input" type="text" wire:model="form.emergency_name"></x-field>
                <x-field label="Relationship" name="form.emergency_relation"><input class="input" type="text" wire:model="form.emergency_relation" placeholder="e.g. Father, Spouse"></x-field>
                <x-field label="Phone" name="form.emergency_phone"><input class="input" type="tel" wire:model="form.emergency_phone"></x-field>
                <x-field label="Alternate phone" name="form.emergency_alt_phone"><input class="input" type="tel" wire:model="form.emergency_alt_phone"></x-field>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($modal === 'policy')
        <x-modal :title="$editingId ? 'Edit policy' : 'Add policy'" size="max-w-3xl">
            <form wire:submit="savePolicy" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                    <x-field label="Title" name="form.title" required><input class="input" type="text" wire:model="form.title" placeholder="e.g. Working hours, Leave policy"></x-field>
                    <x-field label="Position" name="form.sort_order" hint="1 shows first."><input class="input" type="number" min="0" wire:model="form.sort_order"></x-field>
                </div>
                <x-field label="Text" name="form.body" required>
                    <x-editor model="form.body" :value="$form['body'] ?? ''" min-height="14rem" label="Policy text" />
                </x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save policy</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($modal === 'note')
        <x-modal :title="($editingId ? 'Edit meeting note' : 'Add meeting note').' · '.$person->name" size="max-w-3xl">
            <form wire:submit="saveNote" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-[1fr_11rem]">
                    <x-field label="Title" name="form.title" required><input class="input" type="text" wire:model="form.title" placeholder="e.g. Quarterly review"></x-field>
                    <x-field label="Meeting date" name="form.held_on" required><input class="input" type="date" wire:model="form.held_on"></x-field>
                </div>
                <x-field label="Notes" name="form.notes" required>
                    <x-editor model="form.notes" :value="$form['notes'] ?? ''" min-height="12rem" label="Meeting notes" />
                </x-field>
                <label class="flex items-start gap-2 text-sm text-stone-700">
                    <input type="checkbox" class="mt-0.5" wire:model="form.shared">
                    <span>{{ $person->name }} can read this note on their profile. <span class="text-stone-500">Untick to keep it for managers only.</span></span>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save note</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
