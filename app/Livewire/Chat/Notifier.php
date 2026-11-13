<?php

namespace App\Livewire\Chat;

use App\Models\ChatMessage;
use Livewire\Component;

/**
 * Sits on every page and checks for new chat messages every few seconds. When one arrives it
 * raises a popup (who, the first words, a link to the conversation) and refreshes the menu badge.
 * On the Chat page itself only the badge is kept up to date: the message is already on screen.
 */
class Notifier extends Component
{
    /** The newest message already known about; only later ones raise a popup. */
    public int $lastId = 0;

    public bool $onChat = false;

    public function mount(): void
    {
        $this->lastId = (int) ChatMessage::max('id');
        $this->onChat = request()->routeIs('chat');
    }

    public function check(): void
    {
        $me = auth()->user();
        $new = ChatMessage::with('sender')->where('id', '>', $this->lastId)->where('sender_id', '!=', $me->id)
            ->where(fn ($q) => $q->whereNull('recipient_id')->orWhere('recipient_id', $me->id))
            ->orderBy('id')->limit(5)->get();
        $this->lastId = max($this->lastId, (int) ChatMessage::max('id'));

        if (! $this->onChat) {
            foreach ($new as $message) {
                $this->dispatch('chat-popup',
                    name: $message->sender?->name ?? 'Someone',
                    room: $message->recipient_id === null ? 'Team room' : 'Private message',
                    text: $message->preview(),
                    url: route('chat', $message->recipient_id === null ? [] : ['with' => $message->sender_id]),
                );
            }
        }
        $this->dispatch('chat-unread', count: $this->onChat ? 0 : ChatMessage::unreadFor($me->fresh()));
    }

    public function render()
    {
        return <<<'HTML'
            <div wire:poll.10s="check" class="hidden"></div>
            HTML;
    }
}
