<?php

namespace App\Livewire\Office;

use App\Models\RoadmapItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Timeline & Change log: what has been added to the CRM, what is being worked on and what is planned. Super Admins keep it. */
#[Title('Timeline & Change log')]
class Roadmap extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function edit(?int $id = null): void
    {
        Gate::authorize('admin');
        $this->resetValidation();
        $item = $id ? RoadmapItem::findOrFail($id) : null;
        $this->editingId = $item?->id;
        $this->form = [
            'title' => $item->title ?? '', 'details' => $item->details ?? '', 'status' => $item->status ?? 'planned', 'area' => $item->area ?? '',
            'happened_on' => $item?->happened_on?->format('Y-m-d') ?? '', 'sort_order' => (string) ($item->sort_order ?? ((int) RoadmapItem::max('sort_order') + 1)),
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('admin');
        $this->validate([
            'form.title' => ['required', 'string', 'max:255'],
            'form.details' => ['nullable', 'string', 'max:5000'],
            'form.status' => ['required', Rule::in(array_keys(RoadmapItem::STATUSES))],
            'form.area' => ['nullable', 'string', 'max:60'],
            'form.happened_on' => ['nullable', 'date'],
            'form.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ], [], ['form.title' => 'title', 'form.details' => 'details', 'form.status' => 'status', 'form.area' => 'area', 'form.happened_on' => 'date', 'form.sort_order' => 'priority']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        // Something marked done without a date was finished today.
        if ($data['status'] === 'done' && ! $data['happened_on']) {
            $data['happened_on'] = today_local()->toDateString();
        }
        $this->editingId ? RoadmapItem::findOrFail($this->editingId)->update($data) : RoadmapItem::create($data);

        $this->closeModal();
        $this->dispatch('toast', message: 'Saved.');
    }

    /** One click moves an item on: planned → in progress → done. */
    public function advance(int $id): void
    {
        Gate::authorize('admin');
        $item = RoadmapItem::findOrFail($id);
        $next = ['planned' => 'in_progress', 'in_progress' => 'done'][$item->status] ?? null;
        if ($next) {
            $item->update(['status' => $next, 'happened_on' => $next === 'done' ? today_local()->toDateString() : $item->happened_on]);
        }
    }

    public function delete(int $id): void
    {
        Gate::authorize('admin');
        RoadmapItem::findOrFail($id)->delete();
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function render()
    {
        $items = RoadmapItem::all();

        return view('livewire.office.roadmap', [
            'inProgress' => $items->where('status', 'in_progress')->sortBy('sort_order')->values(),
            'planned' => $items->where('status', 'planned')->sortBy('sort_order')->values(),
            // The change log: newest first, grouped by day.
            'done' => $items->where('status', 'done')->sortByDesc(fn ($i) => $i->happened_on?->format('Y-m-d').sprintf('%010d', $i->id))->groupBy(fn ($i) => fmt_date($i->happened_on, 'Earlier')),
            'canManage' => Gate::allows('admin'),
        ]);
    }
}
