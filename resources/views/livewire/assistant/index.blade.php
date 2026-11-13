<div>
    <x-page-header title="AI Assistant" subtitle="Draft emails and proposals, reword or summarise text, and ask everyday questions.">
        @if ($enabled && $messages->isNotEmpty()) <button type="button" class="btn-secondary" wire:click="clear" wire:confirm="Clear this conversation? This cannot be undone.">New conversation</button> @endif
        @if ($canSetup) <button type="button" class="btn-secondary" wire:click="$toggle('showSetup')"><x-icon name="cog" /> Setup</button> @endif
    </x-page-header>

    @if ($canSetup && ($showSetup || ! $enabled))
        <div class="card mb-5 p-5">
            <h2 class="card-title">Setup @if ($enabled) <span class="badge-green ml-1">On</span> @else <span class="badge-gray ml-1">Off</span> @endif</h2>
            <p class="mt-1 text-sm text-stone-600">The assistant uses Claude, made by Anthropic. It needs an API key from your own Anthropic account (console.anthropic.com → API keys). Anthropic bills that account for what the team uses. The key is stored encrypted and is never shown again.</p>
            <form wire:submit="saveKey" class="mt-3 flex flex-wrap items-start gap-2">
                <div class="min-w-0 flex-1 basis-72">
                    <input class="input" type="password" wire:model="apiKey" autocomplete="off" placeholder="{{ $enabled ? 'A key is saved. Paste a new one to replace it.' : 'Paste the API key (starts with sk-ant-)' }}" aria-label="Anthropic API key">
                    @error('apiKey') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary">Save key</button>
                @if ($enabled) <button type="button" class="btn-ghost text-red-700" wire:click="removeKey" wire:confirm="Remove the API key? The assistant stops working for everyone.">Remove key</button> @endif
            </form>
        </div>
    @endif

    @if (! $enabled)
        @unless ($canSetup)
            <div class="card p-8 text-center text-sm text-stone-500">The assistant is not set up yet. Ask a Super Admin to switch it on.</div>
        @endunless
    @else
        <div class="card flex h-[calc(100vh-15rem)] min-h-[24rem] flex-col overflow-hidden">
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-6"
                 x-data="{ bottom() { this.$nextTick(() => this.$el.scrollTop = this.$el.scrollHeight) } }" x-init="bottom()" x-on:assistant-scroll.window="bottom()">
                @forelse ($messages as $m)
                    @if ($m->role === 'user')
                        <div class="flex justify-end" wire:key="a-{{ $m->id }}">
                            <p class="max-w-[80%] rounded-lg bg-brand-600 px-3.5 py-2 text-sm break-words whitespace-pre-wrap text-white">{{ $m->content }}</p>
                        </div>
                    @else
                        <div class="flex gap-3" wire:key="a-{{ $m->id }}">
                            <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-brand-100 text-brand-800"><x-icon name="star" /></span>
                            <div class="rich min-w-0 max-w-[85%] text-sm text-stone-800">{!! \Illuminate\Support\Str::markdown($m->content, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
                        </div>
                    @endif
                @empty
                    <div class="mx-auto max-w-md py-10 text-center">
                        <p class="text-sm font-semibold text-stone-900">How can I help?</p>
                        <p class="mt-1 text-sm text-stone-500">Try: “Write a polite payment reminder for an invoice 15 days overdue”, or paste a long email and ask for a short summary.</p>
                    </div>
                @endforelse
                @if ($waiting)
                    <div class="flex items-center gap-3 text-sm text-stone-500"><span class="grid size-7 place-items-center rounded-full bg-brand-100 text-brand-800"><x-icon name="star" /></span> Thinking…</div>
                @endif
                @if ($error)
                    <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $error }}</p>
                @endif
            </div>
            <form wire:submit="ask" class="flex items-end gap-2 border-t border-stone-200 p-3">
                <div class="min-w-0 flex-1">
                    <textarea class="input resize-none" rows="2" wire:model="question" placeholder="Ask anything, or paste text to work on" aria-label="Message to the assistant"
                              x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.ask() }" @disabled($waiting)></textarea>
                    @error('question') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary" @disabled($waiting) wire:loading.attr="disabled" wire:target="ask,answer">Send</button>
            </form>
        </div>
        <p class="mt-2 text-xs text-stone-500">What you type here is sent to Anthropic to get the answer, so do not paste passwords or card details. The assistant cannot see the CRM’s records, and this conversation is visible only to you. Answers can be wrong; check anything important.</p>
    @endif
</div>
