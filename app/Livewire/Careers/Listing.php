<?php

namespace App\Livewire\Careers;

use App\Models\JobVacancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Public careers page (/careers): every vacancy that is open and not past its closing date. */
#[Layout('components.layouts.careers')]
#[Title('Open positions')]
class Listing extends Component
{
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $department = '';

    #[Url(except: '')]
    public string $type = '';

    public function render()
    {
        $open = JobVacancy::accepting();

        return view('livewire.careers.listing', [
            'vacancies' => (clone $open)
                ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('title', 'like', "%{$s}%")
                    ->orWhere('location', 'like', "%{$s}%")->orWhere('department', 'like', "%{$s}%")->orWhere('summary', 'like', "%{$s}%")))
                ->when($this->department, fn ($q, $v) => $q->where('department', $v))
                ->when($this->type, fn ($q, $v) => $q->where('employment_type', $v))
                ->latest()->get(),
            'totalOpen' => (clone $open)->count(),
            'departments' => (clone $open)->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'types' => (clone $open)->distinct()->pluck('employment_type'),
        ]);
    }
}
