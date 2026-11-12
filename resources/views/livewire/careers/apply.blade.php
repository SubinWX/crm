<div>
    @php $back = '<a href="'.e(route('careers.index')).'" class="inline-flex items-center gap-1 font-medium text-white/85 hover:text-white">← All open positions</a>'; @endphp
    @if ($vacancy)
        <x-public.banner page="careers" compact width="max-w-3xl" :heading="$vacancy->title" :text="$vacancy->summary ?: ''">
            <x-slot:eyebrow>{!! $back !!}</x-slot:eyebrow>
            @foreach (array_unique(array_filter([
                $vacancy->department, \App\Models\JobVacancy::EMPLOYMENT_TYPES[$vacancy->employment_type] ?? null, $vacancy->location,
                \App\Models\JobVacancy::WORK_MODES[$vacancy->work_mode] ?? null,
            ])) as $chip)
                <span class="rounded-full bg-white/15 px-3 py-1 text-sm font-medium text-white ring-1 ring-white/25 backdrop-blur-sm">{{ $chip }}</span>
            @endforeach
        </x-public.banner>
    @else
        <x-public.banner page="careers" compact width="max-w-3xl" heading="Send your resume" text="No specific role in mind? Tell us about yourself and we will contact you when something suitable opens.">
            <x-slot:eyebrow>{!! $back !!}</x-slot:eyebrow>
        </x-public.banner>
    @endif

    <div class="relative z-10 mx-auto -mt-14 max-w-3xl px-4 sm:-mt-16 sm:px-6">
    @if ($vacancy)
        <article class="card p-5 shadow-sm sm:p-7">
            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                @foreach (array_filter([
                    'Department' => $vacancy->department, 'Location' => $vacancy->location,
                    'Work mode' => \App\Models\JobVacancy::WORK_MODES[$vacancy->work_mode] ?? null, 'Experience' => $vacancy->experience,
                    'Salary' => $vacancy->salary_range, 'Openings' => (string) $vacancy->openings,
                    'Apply by' => $vacancy->closes_on ? fmt_date($vacancy->closes_on) : null,
                ]) as $label => $value)
                    <div><dt class="label">{{ $label }}</dt><dd class="font-medium text-stone-900">{{ $value }}</dd></div>
                @endforeach
            </dl>
            <h2 class="mt-6 mb-2 border-t border-stone-100 pt-6 text-base font-semibold text-stone-900">About the role</h2>
            <div class="rich text-sm text-stone-700">{{ rich($vacancy->description) }}</div>
            @if ($vacancy->requirements)
                <h2 class="mt-6 mb-2 text-base font-semibold text-stone-900">What we are looking for</h2>
                <div class="rich text-sm text-stone-700">{{ rich($vacancy->requirements) }}</div>
            @endif
            @if ($accepting && ! $submitted) <a href="#apply" class="btn-primary mt-6 px-5 py-2.5">Apply for this position</a> @endif
        </article>
    @endif

    @if ($submitted)
        <div @class(['card p-6 text-center shadow-sm sm:p-8', 'mt-5' => $vacancy]) role="status">
            <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"><x-icon name="check" class="size-6" /></div>
            <h2 class="text-lg font-semibold text-stone-900">Application received</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-stone-600">Thank you. We have your details and resume, and have sent a confirmation to your email. We will contact you if your profile matches.</p>
            <a href="{{ route('careers.index') }}" class="btn-secondary mt-5">Back to open positions</a>
        </div>
    @elseif (! $accepting)
        <div class="card mt-5 p-6 text-center shadow-sm">
            <h2 class="text-lg font-semibold text-stone-900">This position is no longer open</h2>
            <p class="mt-1 text-sm text-stone-600">Applications for this role have closed. Have a look at our other openings.</p>
            <a href="{{ route('careers.index') }}" class="btn-primary mt-5">See open positions</a>
        </div>
    @else
        <form wire:submit="submit" id="apply" @class(['card scroll-mt-20 space-y-5 p-5 shadow-sm sm:p-7', 'mt-5' => $vacancy]) novalidate>
            <div>
                <h2 class="text-lg font-semibold text-stone-900">{{ $vacancy ? 'Apply for this position' : 'Your details' }}</h2>
                <p class="mt-0.5 text-sm text-stone-500">Fields marked <span class="text-red-600">*</span> are required.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Full name" name="form.name" required><input class="input" type="text" wire:model="form.name" maxlength="120" autocomplete="name"></x-field>
                <x-field label="Email" name="form.email" required><input class="input" type="email" wire:model="form.email" maxlength="190" autocomplete="email"></x-field>
                <x-field label="Phone" name="form.phone" required><input class="input" type="tel" wire:model="form.phone" maxlength="25" autocomplete="tel" placeholder="+91 98765 43210"></x-field>
                <x-field label="Current location" name="form.location"><input class="input" type="text" wire:model="form.location" maxlength="150" placeholder="City"></x-field>
                <x-field label="Total experience (years)" name="form.experience_years" hint="Enter 0 if you are a fresher."><input class="input" type="number" min="0" max="60" step="0.5" wire:model="form.experience_years"></x-field>
                <x-field label="Current company" name="form.current_company"><input class="input" type="text" wire:model="form.current_company" maxlength="150"></x-field>
                <x-field label="Current salary" name="form.current_salary"><input class="input" type="text" wire:model="form.current_salary" maxlength="60" placeholder="e.g. ₹3.6 LPA"></x-field>
                <x-field label="Expected salary" name="form.expected_salary"><input class="input" type="text" wire:model="form.expected_salary" maxlength="60"></x-field>
                <x-field label="Notice period" name="form.notice_period"><input class="input" type="text" wire:model="form.notice_period" maxlength="60" placeholder="e.g. 30 days, immediate"></x-field>
                <x-field label="LinkedIn profile" name="form.linkedin_url"><input class="input" type="url" wire:model="form.linkedin_url" maxlength="500" placeholder="https://"></x-field>
                <x-field label="Portfolio or website" name="form.portfolio_url" class="sm:col-span-2"><input class="input" type="url" wire:model="form.portfolio_url" maxlength="500" placeholder="https://"></x-field>
            </div>

            <x-field label="Resume" name="resume" required hint="PDF or Word file, up to 5 MB.">
                <input type="file" class="input" wire:model="resume" accept=".pdf,.doc,.docx" aria-label="Resume">
                <p wire:loading wire:target="resume" class="mt-1 text-xs text-stone-500">Uploading…</p>
            </x-field>

            <x-field label="Why are you a good fit?" name="form.cover_letter" hint="Optional. A few lines is enough.">
                <textarea class="input" rows="5" wire:model="form.cover_letter" maxlength="5000"></textarea>
            </x-field>

            {{-- Left empty by people; automated form-fillers complete it. --}}
            <div class="hidden" aria-hidden="true">
                <label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div>
                <label class="flex items-start gap-2 text-sm text-stone-700">
                    <input type="checkbox" class="mt-0.5 rounded border-stone-300" wire:model="consent">
                    <span>I agree that {{ setting('company_name') ?: app_name() }} may store these details and my resume to consider me for this and future roles. <span class="text-red-600">*</span></span>
                </label>
                @error('consent') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end border-t border-stone-200 pt-5">
                <button type="submit" class="btn-primary px-5 py-2.5" wire:loading.attr="disabled" wire:target="resume,submit">
                    <span wire:loading.remove wire:target="submit">Submit application</span>
                    <span wire:loading wire:target="submit">Sending…</span>
                </button>
            </div>
        </form>
    @endif
    </div>
</div>
