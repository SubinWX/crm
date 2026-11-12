{{-- Rows of applications, shared by the overview (last 3 days) and the full list. --}}
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Applied on</th><th>Applicant</th><th>Applied for</th><th>Experience</th><th>Status</th><th>Rating</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
            @forelse ($rows as $a)
                <tr wire:key="application-{{ $a->id }}">
                    <td class="whitespace-nowrap">{{ fmt_date($a->created_at->timezone(crm_tz())) }}<p class="text-xs text-stone-500">{{ $a->created_at->timezone(crm_tz())->format('g:i A') }}</p></td>
                    <td class="max-w-56">
                        <button type="button" class="text-left font-medium text-stone-900 hover:underline" wire:click="view({{ $a->id }})">{{ $a->name }}</button>
                        <p class="truncate text-xs text-stone-500">{{ $a->email }} · {{ $a->phone }}</p>
                    </td>
                    <td class="max-w-48">
                        <p class="truncate">{{ $a->vacancy_title }}</p>
                        @if ($a->location) <p class="truncate text-xs text-stone-500">{{ $a->location }}</p> @endif
                    </td>
                    <td class="whitespace-nowrap">{{ $a->experience_years !== null ? (float) $a->experience_years.' yrs' : '—' }}</td>
                    <td>
                        <span class="{{ \App\Models\JobApplication::STATUS_BADGES[$a->status] ?? 'badge-gray' }}">{{ \App\Models\JobApplication::STATUSES[$a->status] ?? $a->status }}</span>
                        @if ($a->interview_at) <p class="mt-0.5 text-xs text-stone-500">Interview {{ fmt_datetime($a->interview_at) }}</p> @endif
                    </td>
                    <td class="whitespace-nowrap">{{ $a->rating ? str_repeat('★', $a->rating).str_repeat('☆', 5 - $a->rating) : '—' }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($a->resumeIsPdf())
                            <a href="{{ route('careers.resume.view', $a) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm" title="View {{ $a->resume_name }} in a new tab"><x-icon name="document" /> View</a>
                            <a href="{{ route('careers.resume', $a) }}" class="btn-ghost btn-sm" title="Download {{ $a->resume_name }}" aria-label="Download resume"><x-icon name="download" /></a>
                        @else
                            <a href="{{ route('careers.resume', $a) }}" class="btn-ghost btn-sm" title="Word file: download {{ $a->resume_name }} to open it"><x-icon name="download" /> Resume</a>
                        @endif
                        <button type="button" class="btn-ghost btn-sm" wire:click="view({{ $a->id }})">Open</button>
                    </td>
                </tr>
            @empty
                <x-empty colspan="7">{{ $empty }}</x-empty>
            @endforelse
        </tbody>
    </table>
</div>
