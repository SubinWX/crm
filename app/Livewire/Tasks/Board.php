<?php

namespace App\Livewire\Tasks;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Task Board: the same tasks as the list, laid out in three columns (To do, In progress, Done).
 * Drag a card to another column to change its status. People with "Create and update tasks" can move any
 * card; everyone else can move the tasks assigned to them.
 */
#[Title('Task Board')]
class Board extends Component
{
    /** mine | all */
    #[Url(except: 'mine')]
    public string $show = 'mine';

    #[Url(except: '')]
    public string $project = '';

    public function booted(): void
    {
        Gate::authorize('tasks.view');
    }

    public function move(int $id, string $status): void
    {
        abort_unless(array_key_exists($status, Task::STATUSES), 422);
        $task = Task::findOrFail($id);
        abort_unless(Gate::allows('tasks.manage') || $task->isAssignedTo(auth()->id()), 403);
        if ($task->status !== $status) {
            $task->update(['status' => $status]);
        }
    }

    public function render()
    {
        $projects = Gate::allows('projects.view');
        $tasks = Task::query()->with(['client', 'lead', 'project', 'assignees'])
            ->when($this->show !== 'all', fn ($q) => $q->assignedTo(auth()->id()))
            ->when($projects && ctype_digit($this->project), fn ($q) => $q->where('project_id', $this->project))
            // Finished work stays on the board for two weeks, then only in the list.
            ->where(fn ($q) => $q->where('status', '!=', 'done')->orWhere('updated_at', '>=', now()->subDays(14)))
            ->orderByRaw('due_date is null')->orderBy('due_date')->latest('id')->limit(300)->get();

        return view('livewire.tasks.board', [
            'columns' => collect(Task::STATUSES)->map(fn ($label, $key) => ['label' => $label, 'tasks' => $tasks->where('status', $key)->values()]),
            'canManage' => Gate::allows('tasks.manage'),
            'projectOptions' => $projects ? Project::query()->open()->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }
}
