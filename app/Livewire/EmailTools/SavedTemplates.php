<?php

namespace App\Livewire\EmailTools;

use App\Models\EmailTemplate;
use App\Support\EmailBuilder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Email Tools → Saved Email Templates: every saved template, shared with the whole team.
 * Open one to edit it, preview it, copy its HTML, make a copy, or delete it.
 */
#[Title('Saved Email Templates')]
class SavedTemplates extends Component
{
    #[Url(except: '')]
    public string $search = '';

    public ?int $previewId = null;

    public function preview(int $id): void
    {
        $this->previewId = EmailTemplate::findOrFail($id)->id;
    }

    public function closeModal(): void
    {
        $this->previewId = null;
    }

    public function duplicate(int $id): void
    {
        $original = EmailTemplate::findOrFail($id);
        EmailTemplate::create(['name' => mb_substr($original->name.' (copy)', 0, 120), 'data' => $original->data, 'created_by' => auth()->id()]);
        $this->dispatch('toast', message: 'Template copied.');
    }

    /** Only the person who made a template, or a Super Admin, can delete it. */
    public function delete(int $id): void
    {
        $template = EmailTemplate::findOrFail($id);
        abort_unless(auth()->user()->isSuperAdmin() || (int) $template->created_by === auth()->id(), 403);
        $template->delete();
        if ($this->previewId === $id) {
            $this->previewId = null;
        }
        $this->dispatch('toast', message: 'Template deleted.');
    }

    public function render()
    {
        $templates = EmailTemplate::with('creator')
            // Text inside the saved details is compared in lower case, because MySQL matches JSON values case-sensitively.
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                ->orWhereRaw("lower(json_unquote(json_extract(data, '$.heading'))) like ?", ['%'.mb_strtolower($s).'%'])))
            ->latest('updated_at')->get();
        $previewing = $this->previewId ? EmailTemplate::find($this->previewId) : null;

        return view('livewire.email-tools.saved-templates', [
            'templates' => $templates,
            'html' => $templates->mapWithKeys(fn ($t) => [$t->id => EmailBuilder::template($t->data ?? [])]),
            'previewing' => $previewing,
        ]);
    }
}
