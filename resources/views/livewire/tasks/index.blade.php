<div>
    @unless ($embedded)
        <x-page-header title="Tasks & Follow-ups" subtitle="Work to do and things to chase, for clients and leads, in one list.">
            @if ($canManage) <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> New Task</button> @endif
        </x-page-header>

        @if ($alsoDue->isNotEmpty())
            <section class="card mb-5">
                <div class="card-header"><h2 class="card-title">Sales follow-ups due</h2><span class="badge-red-strong">{{ $alsoDue->count() }}</span></div>
                @foreach ($alsoDue as $item)
                    <a href="{{ $item['url'] }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-stone-100 px-4 py-2.5 text-sm last:border-b-0 hover:bg-stone-50">
                        <span class="badge-gray w-32 justify-center">{{ $item['kind'] }}</span>
                        <span class="min-w-0 flex-1"><span class="block truncate font-medium text-stone-900">{{ $item['title'] }}</span><span class="block truncate text-xs text-stone-500">{{ $item['about'] }}{{ $item['who'] ? ' · '.$item['who'] : '' }}</span></span>
                        <span class="text-xs font-semibold whitespace-nowrap text-red-700">{{ fmt_date($item['date']) }}{{ days_until($item['date']) < 0 ? ' · overdue' : ' · today' }}</span>
                    </a>
                @endforeach
            </section>
        @endif
    @endunless

    <div class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            @if ($embedded) <h2 class="card-title">Tasks &amp; follow-ups</h2> @endif
            <div class="relative w-full md:w-64">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input type="search" class="input pl-9" placeholder="Search tasks…" wire:model.live.debounce.300ms="search" aria-label="Search tasks">
            </div>
            <select class="input w-auto" wire:model.live="status" aria-label="Status">
                <option value="open">Open</option><option value="due">Due today or overdue</option><option value="overdue">Overdue</option>
                @foreach (\App\Models\Task::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                <option value="all">All</option>
            </select>
            <select class="input w-auto" wire:model.live="assignee" aria-label="Assignee">
                <option value="">Anyone</option><option value="me">Assigned to me</option>
                @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
            </select>
            @if ($embedded && $canManage) <button type="button" class="btn-primary btn-sm ml-auto" wire:click="create"><x-icon name="plus" /> New Task</button> @endif
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Task</th>@unless ($embedded)<th>For</th>@endunless<th>Assignee</th><th>Due date</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($tasks as $task)
                        <tr wire:key="task-{{ $task->id }}">
                            <td class="max-w-96">
                                <p @class(['font-medium', 'text-stone-400 line-through' => $task->status === 'done', 'text-stone-900' => $task->status !== 'done'])>{{ $task->title }}</p>
                                @if ($task->notes) <p class="line-clamp-2 text-xs text-stone-500">{{ $task->notes }}</p> @endif
                            </td>
                            @unless ($embedded)
                                <td>
                                    @if ($task->client_id)
                                        @can('clients.view')<a class="link" href="{{ route('clients.show', ['client' => $task->client_id, 'tab' => 'tasks']) }}">{{ $task->subjectName() }}</a>@else{{ $task->subjectName() }}@endcan
                                    @elseif ($task->lead_id)
                                        @if ($canSales)<a class="link" href="{{ route('leads.show', $task->lead_id) }}">{{ $task->subjectName() }}</a>@else{{ $task->subjectName() }}@endif <span class="badge-blue">Lead</span>
                                    @else — @endif
                                </td>
                            @endunless
                            <td>{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="whitespace-nowrap">
                                @if ($task->isOverdue()) <span class="badge-red-solid">Overdue · {{ fmt_date($task->due_date) }}</span>
                                @elseif ($task->status !== 'done' && $task->due_date && days_until($task->due_date) === 0) <span class="badge-red-strong">Today</span>
                                @else {{ fmt_date($task->due_date) }} @endif
                            </td>
                            <td>
                                @if ($canManage || $task->assignee_id === auth()->id())
                                    <select class="input w-auto py-1 text-xs" aria-label="Status for {{ $task->title }}" wire:change="setStatus({{ $task->id }}, $event.target.value)">
                                        @foreach (\App\Models\Task::STATUSES as $key => $label) <option value="{{ $key }}" @selected($task->status === $key)>{{ $label }}</option> @endforeach
                                    </select>
                                @else
                                    <x-status :value="$task->status" :label="\App\Models\Task::STATUSES[$task->status]" />
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if ($canManage)
                                    <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $task->id }})">Edit</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $task->id }})" wire:confirm="Delete this task?">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="$embedded ? 5 : 6">No tasks here.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tasks->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $tasks->links() }}</div> @endif
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit task' : 'New task or follow-up'" size="max-w-xl">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                @unless ($embedded)
                    @if ($canSales)
                        <x-field label="This is for a" name="form.for" class="md:col-span-2">
                            <div class="flex gap-2">
                                @foreach (['client' => 'Client', 'lead' => 'Lead'] as $key => $label)
                                    <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm has-checked:border-brand-500 has-checked:bg-brand-50"><input type="radio" name="for" value="{{ $key }}" wire:model.live="form.for"> {{ $label }}</label>
                                @endforeach
                            </div>
                        </x-field>
                    @endif
                    @if ($form['for'] === 'lead')
                        <x-field label="Lead" name="form.lead_id" required class="md:col-span-2"><select class="input" wire:model="form.lead_id"><option value="">Select lead…</option>@foreach ($leads as $l) <option value="{{ $l->id }}">{{ $l->name }}</option> @endforeach</select></x-field>
                    @else
                        <x-field label="Client" name="form.client_id" required class="md:col-span-2"><select class="input" wire:model="form.client_id"><option value="">Select client…</option>@foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach</select></x-field>
                    @endif
                @endunless
                <x-field label="Title" name="form.title" required class="md:col-span-2"><input class="input" type="text" wire:model="form.title" placeholder="e.g. Send revised scope, or Update homepage banner"></x-field>
                <x-field label="Assignee" name="form.assignee_id">
                    <select class="input" wire:model="form.assignee_id"><option value="">Unassigned</option>@foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach</select>
                </x-field>
                <x-field label="Due date" name="form.due_date"><input class="input" type="date" wire:model="form.due_date"></x-field>
                <x-field label="Status" name="form.status">
                    <select class="input" wire:model="form.status">@foreach (\App\Models\Task::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
                </x-field>
                <x-field label="Notes" name="form.notes" class="md:col-span-2"><textarea class="input" rows="3" wire:model="form.notes"></textarea></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save task</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
