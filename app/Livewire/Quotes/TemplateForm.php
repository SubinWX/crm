<?php

namespace App\Livewire\Quotes;

use App\Livewire\Concerns\EditsQuoteContent;
use App\Models\QuoteFile;
use App\Models\QuoteTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class TemplateForm extends Component
{
    use EditsQuoteContent, WithFileUploads;

    public ?QuoteTemplate $template = null;

    public string $name = '';

    public string $category = '';

    public string $description = '';

    public string $validity_days = '30';

    /** New reference attachments (source proposal, portfolio PDFs…). */
    public array $uploads = [];

    public function boot(): void
    {
        Gate::authorize('admin');
    }

    public function mount(?QuoteTemplate $template = null): void
    {
        if ($template?->exists) {
            $this->template = $template;
            $this->name = $template->name;
            $this->category = (string) $template->category;
            $this->description = (string) $template->description;
            $this->validity_days = (string) $template->validity_days;
            $this->sections = $template->sections->map(fn ($s) => ['id' => $s->id, 'title' => $s->title, 'body' => (string) $s->body, 'is_hidden' => $s->is_hidden])->all();
            $this->items = $template->items->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name, 'description' => (string) $i->description, 'quantity' => (string) (float) $i->quantity,
                'unit_price' => (string) (float) $i->unit_price, 'billing_frequency' => $i->billing_frequency,
            ])->all();
        } else {
            $this->template = null;
            $this->addStarterSections();
        }
    }

    /** Clearly labelled placeholders an admin replaces with real wording. */
    public function addStarterSections(): void
    {
        $existing = array_column($this->sections, 'title');
        foreach (QuoteTemplate::STARTER_SECTIONS as $title) {
            if (! in_array($title, $existing, true)) {
                $this->sections[] = ['id' => null, 'title' => $title, 'body' => "[Starter text — replace with your own {$title} wording]", 'is_hidden' => false];
            }
        }
    }

    public function removeFile(int $id): void
    {
        $file = $this->template?->files()->findOrFail($id);
        Storage::disk('local')->delete($file->path);
        $file->delete();
    }

    public function save()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'validity_days' => ['required', 'integer', 'between:1,365'],
            'uploads' => ['array', 'max:10'],
            'uploads.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,png,jpg,jpeg,webp'],
        ] + $this->contentRules(), [], ['validity_days' => 'validity', 'uploads.*' => 'attachment'] + $this->contentAttributes());

        $template = DB::transaction(function () {
            $data = ['name' => $this->name, 'category' => $this->category ?: null, 'description' => $this->description ?: null, 'validity_days' => (int) $this->validity_days];
            $template = $this->template ?? new QuoteTemplate;
            $template->fill($data)->save();

            // Replace the content wholesale. Saved quotes hold their own copies, so nothing else is affected.
            $template->sections()->delete();
            foreach ($this->sections as $order => $section) {
                $template->sections()->create(['title' => $section['title'], 'body' => $section['body'] ?: null, 'is_hidden' => (bool) $section['is_hidden'], 'sort_order' => $order]);
            }
            $template->items()->delete();
            foreach ($this->items as $order => $item) {
                $template->items()->create([
                    'name' => $item['name'], 'description' => $item['description'] ?: null, 'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'], 'billing_frequency' => $item['billing_frequency'], 'sort_order' => $order,
                ]);
            }
            foreach ($this->uploads as $file) {
                QuoteFile::create([
                    'owner_type' => 'template', 'owner_id' => $template->id, 'name' => $file->getClientOriginalName(),
                    'path' => $file->store("quote-templates/{$template->id}", 'local'), 'mime' => $file->getMimeType(), 'size' => $file->getSize(),
                ]);
            }

            return $template;
        });

        session()->flash('status', "Template “{$template->name}” saved.");

        return $this->redirectRoute('quote-templates.index');
    }

    public function render()
    {
        return view('livewire.quotes.template-form', [
            'files' => $this->template ? $this->template->files()->get() : collect(),
        ])->title($this->template ? 'Edit template' : 'New template');
    }
}
