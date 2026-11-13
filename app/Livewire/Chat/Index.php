<?php

namespace App\Livewire\Chat;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Internal chat: one team room everyone reads, and private messages between two people.
 * The open conversation refreshes itself every few seconds.
 */
#[Title('Chat')]
class Index extends Component
{
    use WithFileUploads;

    /** '' = the team room, otherwise the id of the person the private conversation is with. */
    #[Url(as: 'with', except: '')]
    public string $with = '';

    public string $body = '';

    /** A picture or file chosen, dropped or pasted into the message box, waiting to be sent. */
    public $file;

    public function mount(): void
    {
        if ($this->with !== '' && ! $this->partner()) {
            $this->with = '';
        }
    }

    protected function partner(): ?User
    {
        return ctype_digit($this->with) && (int) $this->with !== auth()->id() ? User::active()->find((int) $this->with) : null;
    }

    public function open(string $with = ''): void
    {
        $this->with = $with;
        if ($with !== '' && ! $this->partner()) {
            $this->with = '';
        }
        $this->body = '';
        $this->reset('file');
        $this->resetValidation();
        $this->dispatch('chat-scroll');
    }

    public function send(): void
    {
        $partner = $this->partner();
        abort_if($this->with !== '' && ! $partner, 404);
        $this->body = trim($this->body);
        $this->validate([
            'body' => [$this->file ? 'nullable' : 'required', 'string', 'max:4000'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
        ], ['body.required' => 'Type a message or attach a file.', 'file.mimes' => 'Attach a picture, PDF, Word, Excel, CSV, text or ZIP file.', 'file.max' => 'The file may be up to 10 MB.'],
            ['body' => 'message', 'file' => 'attachment']);

        ChatMessage::create([
            'sender_id' => auth()->id(), 'recipient_id' => $partner?->id, 'body' => $this->body !== '' ? $this->body : null,
        ] + ($this->file ? [
            'attachment_path' => $this->file->store('chat', 'local'), 'attachment_name' => $this->file->getClientOriginalName(),
            'attachment_mime' => $this->file->getMimeType(), 'attachment_size' => $this->file->getSize(),
        ] : []));
        $this->body = '';
        $this->reset('file');
        $this->dispatch('chat-scroll');
    }

    public function removeFile(): void
    {
        $this->reset('file');
        $this->resetValidation('file');
    }

    /** Opening or refreshing a conversation marks what is in it as read. */
    protected function markRead(?User $partner): void
    {
        if ($partner) {
            ChatMessage::where('sender_id', $partner->id)->where('recipient_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
        } else {
            // Straight to the table: reading the chat is not a change to the account worth logging.
            DB::table('users')->where('id', auth()->id())->update(['team_chat_read_at' => now()]);
        }
    }

    public function render()
    {
        $me = auth()->user();
        $partner = $this->partner();
        $teamUnread = $partner ? ChatMessage::whereNull('recipient_id')->where('sender_id', '!=', $me->id)
            ->when($me->team_chat_read_at, fn ($q, $at) => $q->where('created_at', '>', $at))->count() : 0;
        $this->markRead($partner);

        $messages = ChatMessage::query()->with('sender')
            ->when($partner, fn ($q) => $q->between($me->id, $partner->id), fn ($q) => $q->whereNull('recipient_id'))
            ->latest('id')->limit(150)->get()->reverse()->values();

        // Unread private messages per sender, and when each private conversation was last active.
        $unread = ChatMessage::where('recipient_id', $me->id)->whereNull('read_at')->selectRaw('sender_id, count(*) as n')->groupBy('sender_id')->pluck('n', 'sender_id');
        $last = ChatMessage::whereNotNull('recipient_id')->where(fn ($q) => $q->where('sender_id', $me->id)->orWhere('recipient_id', $me->id))
            ->selectRaw('case when sender_id = ? then recipient_id else sender_id end as other, max(id) as last_id', [$me->id])->groupBy('other')->pluck('last_id', 'other');
        $people = User::active()->where('id', '!=', $me->id)->orderBy('name')->get()
            ->sortByDesc(fn ($u) => $last[$u->id] ?? 0)->values();

        return view('livewire.chat.index', [
            'partner' => $partner, 'messages' => $messages, 'people' => $people, 'unread' => $unread, 'teamUnread' => $teamUnread,
        ]);
    }
}
