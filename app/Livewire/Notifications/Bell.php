<?php

namespace App\Livewire\Notifications;

use Livewire\Attributes\On;
use Livewire\Component;

class Bell extends Component
{
    #[On('notifications-changed')]
    public function refresh(): void {}

    public function open(string $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->redirect($notification->data['url'] ?? route('notifications'));
    }

    /** Delete one notification straight from the dropdown. Only ever the signed-in person's own. */
    public function delete(string $id): void
    {
        (auth()->user()->notifications()->find($id) ?? abort(404))->delete();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render()
    {
        return view('livewire.notifications.bell', [
            'unread' => auth()->user()->unreadNotifications()->count(),
            'recent' => auth()->user()->notifications()->limit(8)->get(),
        ]);
    }
}
