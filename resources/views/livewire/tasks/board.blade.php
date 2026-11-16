<div>
    <x-page-header title="Task Board" subtitle="Drag a card to another column to change its status.">
        <div class="inline-flex overflow-hidden rounded-md border border-stone-300 text-sm" role="group" aria-label="Whose tasks">
            <button type="button" wire:click="$set('show', 'mine')" @class(['cursor-pointer px-3 py-1.5', 'bg-ink-800 text-white' => $show !== 'all', 'bg-white text-stone-700' => $show === 'all'])>My tasks</button>
            <button type="button" wire:click="$set('show', 'all')" @class(['cursor-pointer px-3 py-1.5', 'bg-ink-800 text-white' => $show === 'all', 'bg-white text-stone-700' => $show !== 'all'])>Everyone’s</button>
        </div>
        @if ($projectOptions->isNotEmpty())
            <select class="input w-auto" wire:model.live="project" aria-label="Project">
                <option value="">All projects</option>
                @foreach ($projectOptions as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
            </select>
        @endif
        <a href="{{ route('tasks.index') }}" class="btn-secondary">List view</a>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-3" x-data="{ dragging: null, over: null }">
        @foreach ($columns as $status => $column)
            <section wire:key="col-{{ $status }}" class="rounded-lg border border-stone-200 bg-stone-100/70 transition" :class="over === '{{ $status }}' && 'ring-2 ring-brand-400'"
                     @dragover.prevent="over = '{{ $status }}'" @dragleave="if (over === '{{ $status }}') over = null"
                     @drop.prevent="over = null; if (dragging) { $wire.move(dragging, '{{ $status }}'); dragging = null }">
                <h2 class="flex items-center justify-between px-3 pt-3 pb-2 text-sm font-semibold text-stone-900">{{ $column['label'] }} <span class="badge-gray">{{ $column['tasks']->count() }}</span></h2>
                <div class="min-h-32 space-y-2 px-2 pb-3">
                    @forelse ($column['tasks'] as $task)
                        @php $movable = $canManage || $task->isAssignedTo(auth()->id()); @endphp
                        <article wire:key="card-{{ $task->id }}" @class(['card p-3 text-sm', 'cursor-grab active:cursor-grabbing' => $movable])
                                 @if ($movable) draggable="true" @dragstart="dragging = {{ $task->id }}; $event.dataTransfer.effectAllowed = 'move'" @dragend="dragging = null; over = null" @endif>
                            <p class="font-medium text-stone-900">{{ $task->title }}</p>
                            <p class="mt-0.5 truncate text-xs text-stone-500">{{ $task->project?->name ?? $task->client?->name ?? $task->lead?->name ?? 'No client' }}</p>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="flex -space-x-1.5">
                                    @foreach ($task->assignees->take(4) as $person) <x-avatar :src="$person->avatarUrl()" :name="$person->name" size="size-6" class="text-[9px] ring-2 ring-white" title="{{ $person->name }}" /> @endforeach
                                </span>
                                <span class="flex-1"></span>
                                @if ($task->due_date) <span @class(['text-xs', 'font-semibold text-red-700' => $task->isOverdue(), 'text-stone-500' => ! $task->isOverdue()])>{{ $task->isOverdue() ? 'Overdue · ' : '' }}{{ fmt_date($task->due_date) }}</span> @endif
                            </div>
                            @if ($movable)
                                {{-- For keyboards and phones, where dragging is awkward. --}}
                                <label class="mt-2 block"><span class="sr-only">Move “{{ $task->title }}” to</span>
                                    <select class="input py-1 text-xs" wire:change="move({{ $task->id }}, $event.target.value)">
                                        @foreach (\App\Models\Task::STATUSES as $key => $label) <option value="{{ $key }}" @selected($task->status === $key)>{{ $label }}</option> @endforeach
                                    </select>
                                </label>
                            @endif
                        </article>
                    @empty
                        <p class="px-2 py-8 text-center text-xs text-stone-500">Nothing here.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-stone-500">Finished tasks stay in Done for two weeks. To add or edit a task, use the <a href="{{ route('tasks.index') }}" class="link">list view</a>.</p>
</div>
