<?php

namespace App\Livewire\Activity;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Activity Log')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $user = '';

    /** '' | auth | records */
    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $search = '';

    public const AUTH_ACTIONS = ['login', 'logout', 'login_failed', 'password_reset', 'password_changed', 'password_set', 'invited', 'invitation_accepted'];

    public function boot(): void
    {
        Gate::authorize('admin');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.activity.index', [
            'entries' => ActivityLog::with('user')
                ->when($this->user, fn ($q, $v) => $q->where('user_id', $v))
                ->when($this->type === 'auth', fn ($q) => $q->whereIn('action', self::AUTH_ACTIONS))
                ->when($this->type === 'records', fn ($q) => $q->whereNotIn('action', self::AUTH_ACTIONS))
                ->when($this->search, fn ($q, $s) => $q->where('description', 'like', "%{$s}%"))
                ->latest('created_at')->latest('id')->paginate(40),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
