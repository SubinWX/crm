<?php

namespace App\Livewire\Office;

use App\Models\MeetingNote;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Office → Meeting Notes: notes of reviews and one-to-one meetings. Everyone reads the notes shared
 * with them; Super Admins, Admins and leave managers write notes about the people they look after.
 */
#[Title('Meeting Notes')]
class MeetingNotes extends Component
{
    use WithPagination;

    /** '' | a user id (managers only) */
    #[Url(except: '')]
    public string $person = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    /** The people the signed-in person may write notes about. */
    protected function people()
    {
        return auth()->user()->managedPeople()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']);
    }

    /** Notes about the people I manage, and the shared notes about me. */
    protected function visible()
    {
        $me = auth()->user();

        return MeetingNote::query()->where(fn ($q) => $q->where(fn ($q) => $q->where('user_id', $me->id)->where('shared', true))
            ->orWhereIn('user_id', $me->managedPeople()->select('id')));
    }

    protected function editable(int $id): MeetingNote
    {
        return MeetingNote::whereIn('user_id', auth()->user()->managedPeople()->select('id'))->find($id) ?? abort(403);
    }

    public function updatedPerson(): void
    {
        $this->resetPage();
    }

    public function edit(?int $id = null): void
    {
        abort_unless($this->people()->isNotEmpty(), 403);
        $this->resetValidation();
        $note = $id ? $this->editable($id) : null;
        $this->editingId = $note?->id;
        $this->form = [
            'user_id' => (string) ($note->user_id ?? (ctype_digit($this->person) ? $this->person : '')), 'title' => $note->title ?? '',
            'held_on' => ($note?->held_on ?? today_local())->format('Y-m-d'), 'notes' => $note->notes ?? '', 'shared' => $note->shared ?? true,
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $people = $this->people();
        abort_unless($people->isNotEmpty(), 403);
        $this->validate([
            'form.user_id' => ['required', Rule::in($people->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'form.title' => ['required', 'string', 'max:255'],
            'form.held_on' => ['required', 'date'],
            'form.notes' => ['required', 'string', 'max:60000'],
            'form.shared' => ['boolean'],
        ], [], ['form.user_id' => 'person', 'form.title' => 'title', 'form.held_on' => 'meeting date', 'form.notes' => 'notes']);

        $data = ['user_id' => (int) $this->form['user_id'], 'title' => trim($this->form['title']), 'held_on' => $this->form['held_on'], 'notes' => $this->form['notes'], 'shared' => (bool) $this->form['shared']];
        $this->editingId ? $this->editable($this->editingId)->update($data) : MeetingNote::create($data + ['created_by' => auth()->id()]);

        $this->closeModal();
        $this->dispatch('toast', message: 'Meeting note saved.');
    }

    public function delete(int $id): void
    {
        $this->editable($id)->delete();
        $this->dispatch('toast', message: 'Meeting note deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function render()
    {
        $people = $this->people();
        $manager = $people->isNotEmpty();

        return view('livewire.office.meeting-notes', [
            'notes' => $this->visible()->with(['user', 'author'])
                ->when($manager && ctype_digit($this->person), fn ($q) => $q->where('user_id', $this->person))
                ->orderByDesc('held_on')->orderByDesc('id')->paginate(20),
            'people' => $people,
            'manager' => $manager,
            'managedIds' => $people->pluck('id')->all(),
        ]);
    }
}
