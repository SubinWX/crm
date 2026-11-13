<?php

namespace App\Livewire\Assistant;

use App\Models\AssistantMessage;
use App\Support\Assistant;
use App\Support\MailSettings;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * AI assistant: a private conversation with Claude for drafting and everyday questions.
 * Each person has their own conversation. It works once a Super Admin has saved an API key here.
 */
#[Title('AI Assistant')]
class Index extends Component
{
    /** How many earlier messages are sent along with a new question. */
    protected const HISTORY = 40;

    public string $question = '';

    public ?string $error = null;

    /** Only filled while typing a new key; the saved key is never sent back to the browser. */
    public string $apiKey = '';

    public bool $showSetup = false;

    protected function history()
    {
        return AssistantMessage::where('user_id', auth()->id());
    }

    /** Step 1: keep the question and show it straight away, then ask for the answer. */
    public function ask(): void
    {
        abort_unless(Assistant::enabled(), 403);
        $this->question = trim($this->question);
        $this->validate(['question' => ['required', 'string', 'max:20000']], [], ['question' => 'message']);

        $this->error = null;
        // A question that never got its answer (an error, a closed tab) is replaced rather than sent twice.
        $last = $this->history()->latest('id')->first();
        if ($last?->role === 'user') {
            $last->delete();
        }
        AssistantMessage::create(['user_id' => auth()->id(), 'role' => 'user', 'content' => $this->question]);
        $this->question = '';
        $this->dispatch('assistant-scroll');
        $this->js('$wire.answer()');
    }

    /** Step 2: send the conversation to the assistant and save its answer. */
    public function answer(Assistant $assistant): void
    {
        abort_unless(Assistant::enabled(), 403);
        $messages = $this->history()->latest('id')->limit(self::HISTORY)->get()->reverse()->values();
        if ($messages->isEmpty() || $messages->last()->role !== 'user') {
            return;
        }
        // The conversation sent must start with a message from the person.
        while ($messages->isNotEmpty() && $messages->first()->role !== 'user') {
            $messages->shift();
        }

        @set_time_limit(200);
        try {
            $reply = $assistant->reply($messages->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->all());
            AssistantMessage::create(['user_id' => auth()->id(), 'role' => 'assistant', 'content' => $reply]);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
        }
        $this->dispatch('assistant-scroll');
    }

    public function clear(): void
    {
        $this->history()->delete();
        $this->error = null;
    }

    public function saveKey(): void
    {
        Gate::authorize('admin');
        $this->apiKey = trim($this->apiKey);
        $this->validate(['apiKey' => ['required', 'string', 'min:20', 'max:300', 'starts_with:sk-ant-']],
            ['apiKey.starts_with' => 'An Anthropic API key starts with “sk-ant-”.'], ['apiKey' => 'API key']);
        MailSettings::putSecret('assistant_api_key', $this->apiKey);
        activity('assistant_key_saved', auth()->user()->name.' saved the AI assistant API key');
        $this->reset(['apiKey', 'showSetup', 'error']);
        $this->dispatch('toast', message: 'API key saved. The assistant is on.');
    }

    public function removeKey(): void
    {
        Gate::authorize('admin');
        MailSettings::putSecret('assistant_api_key', null);
        activity('assistant_key_removed', auth()->user()->name.' removed the AI assistant API key');
        $this->reset(['apiKey', 'showSetup']);
        $this->dispatch('toast', message: 'API key removed. The assistant is off.');
    }

    public function render()
    {
        $messages = $this->history()->orderBy('id')->get();

        return view('livewire.assistant.index', [
            'enabled' => Assistant::enabled(),
            'messages' => $messages,
            'waiting' => $messages->isNotEmpty() && $messages->last()->role === 'user' && ! $this->error,
            'canSetup' => Gate::allows('admin'),
        ]);
    }
}
