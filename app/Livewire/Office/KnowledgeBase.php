<?php

namespace App\Livewire\Office;

use App\Models\KbArticle;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Office → Knowledge Base: how-to articles and process documents for the team. Everyone reads and can
 * add an article; the writer and Super Admins can change or delete it.
 */
#[Title('Knowledge Base')]
class KnowledgeBase extends Component
{
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    /** The article being read. */
    #[Url(as: 'article', except: null)]
    public ?int $openId = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function open(?int $id): void
    {
        $this->openId = $id;
    }

    public function edit(?int $id = null): void
    {
        $this->resetValidation();
        $article = $id ? KbArticle::findOrFail($id) : null;
        abort_if($article && ! $article->canBeEditedBy(auth()->user()), 403);
        $this->editingId = $article?->id;
        $this->form = ['title' => $article->title ?? '', 'category' => $article->category ?? ($this->category ?: ''), 'body' => $article->body ?? ''];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.title' => ['required', 'string', 'max:255'],
            'form.category' => ['nullable', 'string', 'max:60'],
            'form.body' => ['required', 'string', 'max:200000'],
        ], [], ['form.title' => 'title', 'form.category' => 'category', 'form.body' => 'text']);

        $data = ['title' => trim($this->form['title']), 'category' => trim((string) $this->form['category']) ?: null, 'body' => $this->form['body'], 'updated_by' => auth()->id()];
        if ($this->editingId) {
            $article = KbArticle::findOrFail($this->editingId);
            abort_unless($article->canBeEditedBy(auth()->user()), 403);
            $article->update($data);
        } else {
            $article = KbArticle::create($data + ['created_by' => auth()->id()]);
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->openId = $article->id;
        $this->dispatch('toast', message: 'Article saved.');
    }

    public function delete(int $id): void
    {
        $article = KbArticle::findOrFail($id);
        abort_unless($article->canBeEditedBy(auth()->user()), 403);
        $article->delete();
        $this->openId = null;
        $this->dispatch('toast', message: 'Article deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function render()
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($this->search)).'%';

        return view('livewire.office.knowledge-base', [
            'articles' => KbArticle::with('author')
                ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
                ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('body', 'like', $like)))
                ->orderBy('category')->orderBy('title')->get(),
            'categories' => KbArticle::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'open' => $this->openId ? KbArticle::with(['author', 'editor'])->find($this->openId) : null,
        ]);
    }
}
