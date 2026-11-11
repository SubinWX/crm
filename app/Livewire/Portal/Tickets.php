<?php

namespace App\Livewire\Portal;

use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.portal')]
#[Title('My tickets')]
class Tickets extends Component
{
    use WithPagination;

    /** active | closed | all */
    #[Url(except: 'active')]
    public string $show = 'active';

    #[Url(except: '')]
    public string $search = '';

    public function boot(): void
    {
        abort_unless(Auth::guard('portal')->user()?->email_verified_at, 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $mine = fn () => SupportTicket::where('portal_user_id', Auth::guard('portal')->id());

        return view('livewire.portal.tickets', [
            'tickets' => $mine()
                ->when($this->show === 'active', fn ($q) => $q->active())
                ->when($this->show === 'closed', fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
                ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', "%{$s}%")->orWhere('subject', 'like', "%{$s}%")))
                ->latest('last_activity_at')->latest('id')->paginate(15),
            'counts' => ['active' => $mine()->active()->count(), 'all' => $mine()->count()],
        ]);
    }
}
