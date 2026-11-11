<?php

namespace App\Livewire\Quotes;

use App\Models\QuoteTemplate;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Quote Templates')]
class Templates extends Component
{
    #[Url(except: false)]
    public bool $archived = false;

    public function boot(): void
    {
        Gate::authorize('admin');
    }

    public function duplicate(int $id)
    {
        $copy = QuoteTemplate::findOrFail($id)->duplicate();
        session()->flash('status', 'Template duplicated. Rename it and adjust the content.');

        return $this->redirectRoute('quote-templates.edit', $copy);
    }

    public function toggleArchive(int $id): void
    {
        $template = QuoteTemplate::findOrFail($id);
        $template->update(['archived_at' => $template->archived_at ? null : now()]);
        $this->dispatch('toast', message: $template->archived_at ? 'Template archived. Saved quotes are not affected.' : 'Template restored.');
    }

    public function render()
    {
        return view('livewire.quotes.templates', [
            'templates' => QuoteTemplate::query()->withCount(['sections', 'items'])
                ->when($this->archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
                ->orderBy('name')->get(),
        ]);
    }
}
