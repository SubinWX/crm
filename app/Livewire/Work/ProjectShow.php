<?php

namespace App\Livewire\Work;

use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/** One project: description, owner and team, its tasks, deadline, and the hours logged against the estimate. */
class ProjectShow extends Component
{
    public Project $project;

    public function booted(): void
    {
        Gate::authorize('workspace');
        Gate::authorize('client.access', $this->project->client);
    }

    public function render()
    {
        $project = $this->project->load(['client', 'owner', 'quote', 'members']);
        $manager = Gate::allows('team.manage');
        $entries = TimeEntry::where('project_id', $project->id);
        $logged = (int) (clone $entries)->sum('minutes');

        return view('livewire.work.project-show', [
            'project' => $project,
            'logged' => $logged,
            'billable' => (int) (clone $entries)->where('is_billable', true)->sum('minutes'),
            'used' => $project->progress($logged),
            // Everyone sees the totals; the line-by-line entries of other people are for managers.
            'byPerson' => (clone $entries)->toBase()->join('users', 'users.id', '=', 'time_entries.user_id')
                ->selectRaw('users.name as name, sum(time_entries.minutes) as minutes, max(time_entries.work_date) as last_date')->groupBy('users.id', 'users.name')->orderByDesc('minutes')->get(),
            'recent' => (clone $entries)->with('user')->when(! $manager, fn ($q) => $q->where('user_id', auth()->id()))->latest('work_date')->latest('id')->limit(30)->get(),
            'tasksOpen' => $project->tasks()->open()->count(),
            'tasksDone' => $project->tasks()->where('status', 'done')->count(),
            'canTasks' => Gate::allows('tasks.view'),
            'manager' => $manager,
            'canManage' => Gate::allows('clients.manage'),
            'canLog' => Project::loggableBy(auth()->user())->whereKey($project->id)->exists(),
        ])->title($project->name);
    }
}
