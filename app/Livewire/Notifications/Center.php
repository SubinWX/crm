<?php

namespace App\Livewire\Notifications;

use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notification Center')]
class Center extends Component
{
    use WithPagination;

    public const CATEGORIES = ['client' => 'Clients', 'hosting' => 'Hosting', 'invoice' => 'Invoices', 'support' => 'Support', 'renewal' => 'Renewals'];

    /** '' | client | hosting | invoice */
    #[Url(except: '')]
    public string $category = '';

    #[Url(except: false)]
    public bool $unreadOnly = false;

    #[Url(except: false)]
    public bool $importantOnly = false;

    public function updated(): void
    {
        $this->resetPage();
    }

    public function setCategory(string $category): void
    {
        $this->category = array_key_exists($category, self::CATEGORIES) ? $category : '';
        $this->resetPage();
    }

    /** Only ever the signed-in person's own notification; anything else is a 404. */
    protected function mine(string $id)
    {
        return auth()->user()->notifications()->find($id) ?? abort(404);
    }

    public function open(string $id)
    {
        $notification = $this->mine($id);
        $notification->markAsRead();

        return $this->redirect($notification->data['url'] ?? route('notifications'));
    }

    public function toggleRead(string $id): void
    {
        $notification = $this->mine($id);
        $notification->read_at ? $notification->markAsUnread() : $notification->markAsRead();
        $this->dispatch('notifications-changed');
    }

    /** Star a notification so it stays at the top and survives "Delete read". */
    public function toggleImportant(string $id): void
    {
        $notification = $this->mine($id);
        $notification->forceFill(['is_important' => ! $notification->is_important])->save();
        $this->dispatch('notifications-changed');
    }

    public function delete(string $id): void
    {
        $this->mine($id)->delete();
        $this->dispatch('notifications-changed');
        $this->dispatch('toast', message: 'Notification deleted.');
    }

    /** Clear out everything already read in the current view. Important and unread ones are kept. */
    public function deleteRead(): void
    {
        $deleted = auth()->user()->notifications()
            ->whereNotNull('read_at')->where('is_important', false)
            ->when($this->category, fn ($q) => $q->where('data->category', $this->category))
            ->delete();
        $this->resetPage();
        $this->dispatch('notifications-changed');
        $this->dispatch('toast', message: $deleted ? "{$deleted} read ".str('notification')->plural($deleted).' deleted.' : 'Nothing to delete.');
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()
            ->when($this->category, fn ($q) => $q->where('data->category', $this->category))
            ->update(['read_at' => now()]);
        $this->dispatch('notifications-changed');
    }

    public function render()
    {
        $user = auth()->user();

        $counts = [];
        foreach (array_keys(self::CATEGORIES) as $key) {
            $counts[$key] = $user->unreadNotifications()->where('data->category', $key)->count();
        }

        // Important first, then newest. (reorder() replaces the relation's default "newest first".)
        $notifications = $user->notifications()->reorder()
            ->when($this->category, fn ($q) => $q->where('data->category', $this->category))
            ->when($this->unreadOnly, fn ($q) => $q->whereNull('read_at'))
            ->when($this->importantOnly, fn ($q) => $q->where('is_important', true))
            ->orderByDesc('is_important')->orderByDesc('created_at')
            ->paginate(30);

        return view('livewire.notifications.center', [
            'notifications' => $notifications,
            // Grouped by client / hosting / invoice when viewing everything.
            'groups' => $notifications->getCollection()->groupBy(fn ($n) => $n->data['category'] ?? 'other'),
            'counts' => $counts,
            'totalUnread' => array_sum($counts),
            'importantCount' => $user->notifications()->where('is_important', true)->count(),
            'readCount' => $user->notifications()->whereNotNull('read_at')->where('is_important', false)
                ->when($this->category, fn ($q) => $q->where('data->category', $this->category))->count(),
        ]);
    }
}
