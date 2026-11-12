<?php

namespace App\Livewire\EmailTools;

use App\Models\EmailSignature;
use App\Support\EmailBuilder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Email Tools → Saved Signatures: the list of signatures that have been saved. Everyone sees
 * their own; Super Admins also see their team's. Open one to edit it, copy its HTML, or delete it.
 */
#[Title('Saved Signatures')]
class SavedSignatures extends Component
{
    #[Url(except: '')]
    public string $search = '';

    public ?int $previewId = null;

    protected function visible()
    {
        return EmailSignature::query()->with('user')
            ->when(! auth()->user()->isSuperAdmin(), fn ($q) => $q->where('user_id', auth()->id()));
    }

    /** One of the signatures this person may see; anything else is refused. */
    protected function allowed(int $id): EmailSignature
    {
        return $this->visible()->find($id) ?? abort(403);
    }

    public function preview(int $id): void
    {
        $this->previewId = $this->allowed($id)->id;
    }

    public function closeModal(): void
    {
        $this->previewId = null;
    }

    public function duplicate(int $id): void
    {
        $original = $this->allowed($id);
        EmailSignature::create(['user_id' => auth()->id(), 'name' => mb_substr($original->name.' (copy)', 0, 120), 'data' => $original->data]);
        $this->dispatch('toast', message: 'Copied. The copy is yours to change.');
    }

    public function delete(int $id): void
    {
        $signature = $this->allowed($id);
        $photo = (string) ($signature->data['photo_url'] ?? '');
        $signature->delete();

        // Remove the uploaded headshot too, unless another saved signature still uses it.
        $prefix = url('/e').'/';
        if (str_starts_with($photo, $prefix) && preg_match('/^[A-Za-z0-9]{40}\.jpg$/', $name = substr($photo, strlen($prefix)))
            && ! EmailSignature::where('data->photo_url', $photo)->exists()) {
            Storage::disk('local')->delete('email-assets/'.$name);
        }
        if ($this->previewId === $id) {
            $this->previewId = null;
        }
        $this->dispatch('toast', message: 'Signature deleted.');
    }

    public function render()
    {
        $signatures = $this->visible()
            // Text inside the saved details is compared in lower case, because MySQL matches JSON values case-sensitively.
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                ->orWhereRaw("lower(json_unquote(json_extract(data, '$.name'))) like ?", ['%'.mb_strtolower($s).'%'])
                ->orWhereRaw("lower(json_unquote(json_extract(data, '$.title'))) like ?", ['%'.mb_strtolower($s).'%'])))
            ->orderByRaw('user_id = ? desc', [auth()->id()])->latest('updated_at')->get();
        $previewing = $this->previewId ? $this->visible()->find($this->previewId) : null;

        return view('livewire.email-tools.saved-signatures', [
            'signatures' => $signatures,
            'html' => $signatures->mapWithKeys(fn ($s) => [$s->id => EmailBuilder::signature($s->data ?? [])]),
            'previewing' => $previewing,
        ]);
    }
}
