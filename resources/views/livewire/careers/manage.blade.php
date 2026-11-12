<div>
    <x-page-header :title="['overview' => 'Careers', 'applications' => 'Job Applications', 'vacancies' => 'Job Vacancies'][$tab]"
                   :subtitle="['overview' => 'What came in over the last 3 days, and how applications are trending.', 'applications' => 'Every application received, with filters, sorting and export.', 'vacancies' => 'The jobs shown on your public careers page.'][$tab]">
        <a href="{{ route('careers.index') }}" target="_blank" rel="noopener" class="btn-secondary" title="Opens the page job seekers see">View careers page</a>
        @if ($tab === 'applications')
            <a href="{{ route('careers.export', array_filter($this->filters() + ['sort' => $sort])) }}" class="btn-secondary" title="Every application matching the filters below"><x-icon name="download" /> Export CSV</a>
        @endif
        <button type="button" class="btn-primary" wire:click="createVacancy"><x-icon name="plus" /> Add Vacancy</button>
    </x-page-header>

    @if ($tab === 'overview')
        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Open vacancies" :value="$totals['open']" hint="Visible on the careers page" :href="route('careers.vacancies')" />
            <x-stat label="New applications" :value="$totals['new']" hint="Not opened yet" :tone="$totals['new'] ? 'danger' : 'default'" :href="route('careers.applications', ['status' => 'new'])" />
            <x-stat label="Applications this month" :value="$totals['month']" :hint="today_local()->format('F Y')" :href="route('careers.applications')" />
            <x-stat label="Upcoming interviews" :value="$totals['interviews']" hint="Scheduled from now on" :href="route('careers.applications', ['status' => 'interview', 'sort' => 'interview'])" />
        </div>

        <div class="mb-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Applications received <span class="font-normal text-stone-500">· {{ $chart['total'] }} in this period</span></h2>
                    <div class="flex gap-1" role="group" aria-label="Chart period">
                        @foreach (\App\Livewire\Careers\Manage::CHART_RANGES as $key => $label)
                            <button type="button" wire:click="$set('chartRange', '{{ $key }}')" @class(['btn btn-sm border', 'border-ink-900 bg-ink-900 text-white' => $chartRange === (string) $key, 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' => $chartRange !== (string) $key])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                @php $max = max(1, $chart['bars']->max('count')); @endphp
                <div class="p-4">
                    <div class="flex h-44 items-end gap-px sm:gap-1" role="img" aria-label="Applications received per {{ $chartRange === '12m' ? 'month' : 'day' }}: {{ $chart['bars']->map(fn ($b) => $b['title'].' '.$b['count'])->implode(', ') }}">
                        @foreach ($chart['bars'] as $bar)
                            <div class="group relative flex h-full flex-1 flex-col justify-end" title="{{ $bar['title'] }}: {{ $bar['count'] }} {{ $bar['count'] === 1 ? 'application' : 'applications' }}">
                                @if ($bar['count'] && $chart['bars']->count() <= 14) <span class="mb-1 text-center text-[11px] font-semibold tabular-nums text-stone-700">{{ $bar['count'] }}</span> @endif
                                <div @class(['w-full rounded-t', 'bg-brand-500 group-hover:bg-brand-600' => $bar['count'], 'bg-stone-200' => ! $bar['count']]) style="height: {{ $bar['count'] ? max(4, round($bar['count'] / $max * 100)) : 1 }}%"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-1.5 flex gap-px border-t border-stone-200 pt-1.5 sm:gap-1">
                        @foreach ($chart['bars'] as $bar)
                            <span class="flex min-w-0 flex-1 justify-center text-[10px] whitespace-nowrap text-stone-500">{{ $bar['label'] }}</span>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-stone-500">Highest: {{ $chart['bars']->max('count') }} in one {{ $chartRange === '12m' ? 'month' : 'day' }}. Hover a bar for its exact count.</p>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">By vacancy</h2><span class="text-xs text-stone-500">Same period</span></div>
                @php $maxVacancy = max(1, $chart['byVacancy']->max()); @endphp
                <div class="space-y-3 p-4">
                    @forelse ($chart['byVacancy'] as $title => $count)
                        <div class="text-sm">
                            <div class="flex items-baseline justify-between gap-3"><span class="truncate text-stone-700">{{ $title }}</span><span class="font-medium tabular-nums text-stone-900">{{ $count }}</span></div>
                            <div class="mt-1 h-2 rounded-full bg-stone-100"><div class="h-2 rounded-full bg-brand-500" style="width: {{ max(4, round($count / $maxVacancy * 100)) }}%"></div></div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-stone-500">No applications in this period.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Last 3 days <span class="font-normal text-stone-500">· {{ $recent->count() }} {{ $recent->count() === 1 ? 'application' : 'applications' }} since {{ fmt_date(today_local()->subDays(2)) }}</span></h2>
                <a href="{{ route('careers.applications') }}" class="link text-sm">View all applications →</a>
            </div>
            @include('livewire.careers._applications-table', ['rows' => $recent, 'empty' => 'Nothing new in the last 3 days. Older applications are under Careers → All Applications.'])
        </section>
    @endif

    @if ($tab === 'applications')
        <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Filter by status">
            <button type="button" wire:click="$set('status', '')" @class(['btn btn-sm border', 'border-ink-900 bg-ink-900 text-white' => $status === '', 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' => $status !== ''])>
                All <span class="rounded-full bg-stone-200 px-1.5 text-[11px] font-bold text-stone-700">{{ $statusCounts->sum() }}</span>
            </button>
            @foreach (\App\Models\JobApplication::STATUSES as $key => $label)
                <button type="button" wire:key="status-{{ $key }}" wire:click="$set('status', '{{ $key }}')" @class(['btn btn-sm border', 'border-ink-900 bg-ink-900 text-white' => $status === $key, 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' => $status !== $key])>
                    {{ $label }} <span class="rounded-full bg-stone-200 px-1.5 text-[11px] font-bold text-stone-700">{{ $statusCounts[$key] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div class="card">
            <div class="grid gap-3 border-b border-stone-200 p-4 md:grid-cols-3">
                <input type="search" class="input" placeholder="Search name, email, phone, company…" wire:model.live.debounce.300ms="search" aria-label="Search applications">
                <select class="input" wire:model.live="vacancy" aria-label="Vacancy">
                    <option value="">All vacancies</option>
                    @foreach ($vacancies as $v) <option value="{{ $v->id }}">{{ $v->title }}{{ $v->status === 'open' ? '' : ' ('.strtolower(\App\Models\JobVacancy::STATUSES[$v->status]).')' }}</option> @endforeach
                    @if ($hasGeneral) <option value="general">General applications</option> @endif
                </select>
                <select class="input" wire:model.live="sort" aria-label="Sort by">
                    @foreach (\App\Support\ApplicationFilters::SORTS as $key => $label) <option value="{{ $key }}">Sort: {{ $label }}</option> @endforeach
                </select>
                <label class="flex items-center gap-2 text-xs font-semibold text-stone-500 uppercase">Applied from <input type="date" class="input normal-case" wire:model.live="from"></label>
                <label class="flex items-center gap-2 text-xs font-semibold text-stone-500 uppercase">To <input type="date" class="input normal-case" wire:model.live="to"></label>
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach (['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'This month', 'all' => 'All time'] as $key => $label)
                        <button type="button" class="btn-ghost btn-sm border border-stone-200" wire:click="setRange('{{ $key }}')">{{ $label }}</button>
                    @endforeach
                    @if ($search !== '' || $vacancy !== '' || $status !== '' || $from !== '' || $to !== '' || $sort !== 'newest')
                        <button type="button" class="link text-xs" wire:click="clearFilters">Clear all</button>
                    @endif
                </div>
            </div>
            @include('livewire.careers._applications-table', ['rows' => $applications, 'empty' => 'No applications match. New ones appear here as soon as someone applies on the careers page.'])
            @if ($applications->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $applications->links() }}</div> @endif
        </div>
    @elseif ($tab === 'vacancies')
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Vacancy</th><th>Type</th><th>Status</th><th>Closes on</th><th class="num">Applications</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($vacancies as $v)
                            @php $shown = $v->displayStatus(); @endphp
                            <tr wire:key="vacancy-{{ $v->id }}">
                                <td class="max-w-96">
                                    <p class="font-medium text-stone-900">{{ $v->title }}</p>
                                    <p class="truncate text-xs text-stone-500">{{ collect([$v->department, $v->location, $v->experience])->filter()->implode(' · ') ?: '—' }}</p>
                                    @if ($v->status === 'open') <a href="{{ $v->publicUrl() }}" target="_blank" rel="noopener" class="link text-xs">{{ str($v->publicUrl())->after('://') }}</a> @endif
                                </td>
                                <td class="whitespace-nowrap">{{ \App\Models\JobVacancy::EMPLOYMENT_TYPES[$v->employment_type] ?? $v->employment_type }}<p class="text-xs text-stone-500">{{ \App\Models\JobVacancy::WORK_MODES[$v->work_mode] ?? $v->work_mode }} · {{ $v->openings }} {{ $v->openings === 1 ? 'opening' : 'openings' }}</p></td>
                                <td><span class="{{ ['open' => 'badge-green', 'draft' => 'badge-gray', 'closed' => 'badge-gray', 'expired' => 'badge-amber'][$shown] }}">{{ $shown === 'expired' ? 'Closing date passed' : \App\Models\JobVacancy::STATUSES[$shown] }}</span></td>
                                <td class="whitespace-nowrap">{{ fmt_date($v->closes_on, 'No closing date') }}</td>
                                <td class="num">
                                    <a class="link" href="{{ route('careers.applications', ['vacancy' => $v->id]) }}" title="See the applications for this vacancy">{{ $v->applications_count }}</a>
                                    @if ($v->new_count) <span class="badge-blue ml-1">{{ $v->new_count }} new</span> @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($v->status === 'open')
                                        <button type="button" class="btn-ghost btn-sm" x-data="{ done: false }" x-on:click="navigator.clipboard.writeText(@js($v->publicUrl())); done = true; setTimeout(() => done = false, 1500)" x-text="done ? 'Copied' : 'Copy link'">Copy link</button>
                                        <button type="button" class="btn-ghost btn-sm" wire:click="setVacancyStatus({{ $v->id }}, 'closed')">Close</button>
                                    @else
                                        <button type="button" class="btn-ghost btn-sm" wire:click="setVacancyStatus({{ $v->id }}, 'open')">{{ $v->status === 'draft' ? 'Publish' : 'Reopen' }}</button>
                                    @endif
                                    <button type="button" class="btn-ghost btn-sm" wire:click="editVacancy({{ $v->id }})">Edit</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="deleteVacancy({{ $v->id }})"
                                            wire:confirm="Delete the vacancy “{{ $v->title }}”?{{ $v->applications_count ? ' Its '.$v->applications_count.' application(s) will be kept.' : '' }}">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <x-empty colspan="6">No vacancies yet. Add the first one and it appears on the careers page as soon as it is marked open.</x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($showVacancyForm)
        <x-modal :title="$editingVacancyId ? 'Edit vacancy' : 'Add vacancy'" size="max-w-3xl">
            <form wire:submit="saveVacancy" class="grid gap-4 md:grid-cols-2">
                <x-field label="Job title" name="form.title" required class="md:col-span-2"><input class="input" type="text" wire:model="form.title" maxlength="150" placeholder="e.g. Laravel Developer"></x-field>
                <x-field label="Department" name="form.department">
                    <select class="input" wire:model.live="form.department">
                        <option value="">Select…</option>
                        @foreach ($departments as $d) <option value="{{ $d }}">{{ $d }}</option> @endforeach
                        <option value="__new">+ Add a new department…</option>
                    </select>
                    @if ($form['department'] === '__new')
                        <input class="input mt-2" type="text" wire:model="newDepartment" maxlength="100" placeholder="New department name" aria-label="New department name">
                        <p class="mt-1 text-xs text-stone-500">Saved to the list for next time. Manage the list in Settings → Dropdown values.</p>
                        @error('newDepartment') <p class="field-error">{{ $message }}</p> @enderror
                    @endif
                </x-field>
                <x-field label="Location" name="form.location"><input class="input" type="text" wire:model="form.location" placeholder="e.g. Kochi, Kerala"></x-field>
                <x-field label="Employment type" name="form.employment_type" required>
                    <select class="input" wire:model="form.employment_type">
                        @foreach (\App\Models\JobVacancy::EMPLOYMENT_TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Work mode" name="form.work_mode" required>
                    <select class="input" wire:model="form.work_mode">
                        @foreach (\App\Models\JobVacancy::WORK_MODES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Experience needed" name="form.experience"><input class="input" type="text" wire:model="form.experience" placeholder="e.g. 2–4 years"></x-field>
                <x-field label="Salary range" name="form.salary_range" hint="Optional. Shown on the careers page if filled."><input class="input" type="text" wire:model="form.salary_range" placeholder="e.g. ₹3–5 LPA"></x-field>
                <x-field label="Number of openings" name="form.openings" required><input class="input" type="number" min="1" max="999" wire:model="form.openings"></x-field>
                <x-field label="Closing date" name="form.closes_on" hint="Optional. Applications stop after this date."><input class="input" type="date" wire:model="form.closes_on"></x-field>
                <x-field label="Short summary" name="form.summary" class="md:col-span-2" hint="One or two lines shown in the list of vacancies."><input class="input" type="text" wire:model="form.summary" maxlength="500"></x-field>
                <x-field label="Job description" name="form.description" required class="md:col-span-2"><x-editor model="form.description" :value="$form['description']" placeholder="What the role involves, day to day." label="Job description" /></x-field>
                <x-field label="Requirements" name="form.requirements" class="md:col-span-2" hint="Use the bullet list button for one requirement per line."><x-editor model="form.requirements" :value="$form['requirements']" placeholder="Skills, qualifications and anything else you expect." label="Requirements" /></x-field>
                <x-field label="Status" name="form.status" required hint="Only open vacancies are visible to the public.">
                    <select class="input" wire:model="form.status">
                        @foreach (\App\Models\JobVacancy::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <div class="flex items-end justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save vacancy</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($viewing)
        <x-modal :title="$viewing->name" size="max-w-3xl">
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-stone-600">
                <span>Applied for <strong class="text-stone-900">{{ $viewing->vacancy_title }}</strong></span>
                <span>· {{ fmt_datetime($viewing->created_at) }}</span>
                @if (! $viewing->vacancy && $viewing->vacancy_title !== \App\Livewire\Careers\Apply::GENERAL_TITLE) <span class="badge-gray">Vacancy deleted</span> @endif
            </div>

            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                @foreach ([
                    'Email' => $viewing->email, 'Phone' => $viewing->phone, 'Current location' => $viewing->location,
                    'Total experience' => $viewing->experience_years !== null ? (float) $viewing->experience_years.' years' : null,
                    'Current company' => $viewing->current_company, 'Notice period' => $viewing->notice_period,
                    'Current salary' => $viewing->current_salary, 'Expected salary' => $viewing->expected_salary,
                ] as $label => $value)
                    <div><dt class="label">{{ $label }}</dt><dd class="break-words text-stone-900">
                        @if ($label === 'Email' && $value) <a href="mailto:{{ $value }}" class="link">{{ $value }}</a>
                        @elseif ($label === 'Phone' && $value) <a href="tel:{{ preg_replace('/[^0-9+]/', '', $value) }}" class="link">{{ $value }}</a>
                        @else {{ $value ?: '—' }} @endif
                    </dd></div>
                @endforeach
                @foreach (['Portfolio / website' => $viewing->portfolio_url, 'LinkedIn' => $viewing->linkedin_url] as $label => $value)
                    <div><dt class="label">{{ $label }}</dt><dd class="break-all text-stone-900">@if ($value) <a href="{{ $value }}" target="_blank" rel="noopener noreferrer nofollow" class="link">{{ $value }}</a> @else — @endif</dd></div>
                @endforeach
            </dl>

            @if ($viewing->cover_letter)
                <div class="mt-4"><p class="label">Message from the applicant</p><div class="rounded-md bg-stone-50 p-3 text-sm whitespace-pre-line text-stone-800">{{ $viewing->cover_letter }}</div></div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-3 rounded-md border border-stone-200 p-3">
                <x-icon name="document" class="size-5 text-stone-500" />
                <span class="min-w-0 flex-1 truncate text-sm text-stone-800">{{ $viewing->resume_name }}</span>
                @if ($viewing->resumeIsPdf()) <a href="{{ route('careers.resume.view', $viewing) }}" target="_blank" rel="noopener" class="btn-secondary btn-sm"><x-icon name="document" /> View resume</a> @endif
                <a href="{{ route('careers.resume', $viewing) }}" class="btn-secondary btn-sm"><x-icon name="download" /> Download resume</a>
            </div>
            @unless ($viewing->resumeIsPdf()) <p class="mt-1 text-xs text-stone-500">This is a Word file. Browsers cannot show Word files, so download it to open it.</p> @endunless

            <form wire:submit="saveReview" class="mt-5 grid gap-4 border-t border-stone-200 pt-5 md:grid-cols-3">
                <x-field label="Status" name="review.status" required>
                    <select class="input" wire:model="review.status">
                        @foreach (\App\Models\JobApplication::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Rating" name="review.rating">
                    <select class="input" wire:model="review.rating">
                        <option value="">Not rated</option>
                        @foreach ([5 => '★★★★★ Excellent', 4 => '★★★★☆ Good', 3 => '★★★☆☆ Average', 2 => '★★☆☆☆ Weak', 1 => '★☆☆☆☆ Poor'] as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Interview date & time" name="review.interview_at" hint="Also shown on the Calendar."><input class="input" type="datetime-local" wire:model="review.interview_at"></x-field>
                <x-field label="Internal notes" name="review.notes" class="md:col-span-3" hint="Only your team sees these."><textarea class="input" rows="3" wire:model="review.notes"></textarea></x-field>
                <div class="flex items-center justify-between gap-2 md:col-span-3">
                    <button type="button" class="btn-ghost text-red-700" wire:click="deleteApplication({{ $viewing->id }})" wire:confirm="Delete this application and its resume? This cannot be undone.">Delete application</button>
                    <div class="flex gap-2">
                        <button type="button" class="btn-secondary" wire:click="closeModal">Close</button>
                        <button type="submit" class="btn-primary">Save</button>
                    </div>
                </div>
                @if ($viewing->reviewer) <p class="text-xs text-stone-500 md:col-span-3">Last updated by {{ $viewing->reviewer->name }} on {{ fmt_datetime($viewing->updated_at) }}.</p> @endif
            </form>
        </x-modal>
    @endif
</div>
