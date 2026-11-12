<div>
    <x-public.banner page="careers">
        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3.5 py-1.5 text-sm font-medium text-white ring-1 ring-white/25 backdrop-blur-sm">
            <span @class(['size-2 rounded-full', 'bg-emerald-400' => $totalOpen, 'bg-white/50' => ! $totalOpen])></span>
            {{ $totalOpen ? $totalOpen.' open '.($totalOpen === 1 ? 'position' : 'positions') : 'No open positions right now' }}
        </span>
    </x-public.banner>

    {{-- The list is pulled up so it overlaps the bottom of the banner. --}}
    <div class="relative z-10 mx-auto -mt-14 max-w-6xl px-4 sm:-mt-16 sm:px-6">
        @if ($totalOpen > 3 || $search !== '' || $department !== '' || $type !== '')
            <div class="card mb-4 grid gap-3 p-3 shadow-sm sm:grid-cols-3">
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                    <input type="search" class="input pl-9" placeholder="Search job title or location…" wire:model.live.debounce.300ms="search" aria-label="Search positions">
                </div>
                <select class="input" wire:model.live="department" aria-label="Department">
                    <option value="">All departments</option>
                    @foreach ($departments as $d) <option value="{{ $d }}">{{ $d }}</option> @endforeach
                </select>
                <select class="input" wire:model.live="type" aria-label="Employment type">
                    <option value="">All types</option>
                    @foreach ($types as $t) <option value="{{ $t }}">{{ \App\Models\JobVacancy::EMPLOYMENT_TYPES[$t] ?? $t }}</option> @endforeach
                </select>
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($vacancies as $v)
                <a href="{{ $v->publicUrl() }}" wire:key="vacancy-{{ $v->id }}"
                   @class(['group card flex flex-col p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lg sm:p-6', 'md:col-span-2' => $vacancies->count() === 1])>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($v->department) <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-800">{{ $v->department }}</span> @endif
                        <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-700">{{ \App\Models\JobVacancy::EMPLOYMENT_TYPES[$v->employment_type] ?? $v->employment_type }}</span>
                        @if ($v->created_at->gt(now()->subDays(7))) <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 ring-inset">New</span> @endif
                    </div>
                    <h2 class="mt-3 text-xl font-semibold text-stone-900 group-hover:text-brand-700">{{ $v->title }}</h2>
                    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-stone-600">
                        @foreach (array_unique(array_filter([$v->location, \App\Models\JobVacancy::WORK_MODES[$v->work_mode] ?? null, $v->experience ? $v->experience.' experience' : null, $v->salary_range])) as $part)
                            <li class="flex items-center gap-1.5"><span class="size-1 rounded-full bg-stone-400"></span>{{ $part }}</li>
                        @endforeach
                    </ul>
                    @if ($v->summary) <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-stone-700">{{ $v->summary }}</p> @endif
                    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 pt-4 text-xs text-stone-500 [margin-block-start:1.25rem]">
                        <span>Posted {{ fmt_date($v->created_at->timezone(crm_tz())) }}@if ($v->closes_on) · Apply by {{ fmt_date($v->closes_on) }}@endif</span>
                        <span class="inline-flex items-center gap-1 text-sm font-semibold text-brand-700">View and apply <span class="transition-transform group-hover:translate-x-1" aria-hidden="true">→</span></span>
                    </div>
                </a>
            @empty
                <div class="card p-10 text-center shadow-sm md:col-span-2">
                    <p class="text-base font-semibold text-stone-900">{{ $totalOpen ? 'No positions match your search' : 'No open positions right now' }}</p>
                    <p class="mt-1 text-sm text-stone-600">{{ $totalOpen ? 'Try a different word or clear the filters.' : 'You can still send us your resume and we will keep it for future openings.' }}</p>
                </div>
            @endforelse
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-brand-100 bg-brand-50 p-5 sm:p-6">
            <div>
                <h2 class="text-base font-semibold text-stone-900">Don’t see a role that fits?</h2>
                <p class="mt-0.5 text-sm text-stone-600">Send us your resume anyway. We will contact you when something suitable opens.</p>
            </div>
            <a href="{{ route('careers.general') }}" class="btn-primary px-5 py-2.5">Send your resume</a>
        </div>
    </div>
</div>
