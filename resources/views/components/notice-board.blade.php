{{-- Dashboard: current announcements, and the birthdays and work anniversaries of the next 7 days. Nothing is drawn when there is nothing to show. --}}
@php
    $notices = \App\Models\Announcement::query()->current()->limit(3)->get();
    $today = today_local();
    $occasions = collect();
    foreach (\App\Models\User::active()->get(['id', 'name', 'date_of_birth', 'joined_on', 'avatar_path', 'updated_at']) as $person) {
        foreach ([['date_of_birth', 'Birthday'], ['joined_on', 'Work anniversary']] as [$field, $label]) {
            if (! $person->{$field}) { continue; }
            // This year's date; 29 February falls on the 28th in other years.
            $day = $person->{$field}->copy()->year($today->year);
            if ($day->month !== $person->{$field}->month) { $day = $day->subDay(); }
            if ($day->lt($today)) { $day = $day->addYear(); }
            $in = (int) $today->diffInDays($day);
            $years = $day->year - $person->{$field}->year;
            if ($in <= 7 && ($field === 'date_of_birth' || $years >= 1)) {
                $occasions->push(['person' => $person, 'label' => $field === 'joined_on' ? $years.' '.str('year')->plural($years).' with us' : $label, 'in' => $in, 'day' => $day]);
            }
        }
    }
    $occasions = $occasions->sortBy('in')->values();
@endphp
@if ($notices->isNotEmpty() || $occasions->isNotEmpty())
    <section class="card mb-5" aria-label="Notice board">
        <div class="card-header"><h2 class="card-title">Notice board</h2><a href="{{ route('announcements') }}" class="link text-xs">All announcements</a></div>
        <div @class(['grid divide-y divide-stone-200', 'lg:grid-cols-[1fr_20rem] lg:divide-x lg:divide-y-0' => $notices->isNotEmpty() && $occasions->isNotEmpty()])>
            @if ($notices->isNotEmpty())
                <ul class="divide-y divide-stone-100">
                    @foreach ($notices as $n)
                        <li class="px-4 py-3">
                            <p class="text-sm font-semibold text-stone-900">@if ($n->pinned)<span class="badge-blue mr-1">Pinned</span>@endif{{ $n->title }}</p>
                            @if (filled($n->body)) <p class="mt-0.5 line-clamp-2 text-sm text-stone-600">{{ \App\Support\RichText::plain($n->body) }}</p> @endif
                            <p class="mt-0.5 text-xs text-stone-500">{{ fmt_date($n->created_at) }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($occasions->isNotEmpty())
                <ul class="divide-y divide-stone-100">
                    @foreach ($occasions as $o)
                        <li class="flex items-center gap-3 px-4 py-2.5">
                            <x-avatar :src="$o['person']->avatarUrl()" :name="$o['person']->name" size="size-8" />
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-stone-900">{{ $o['person']->name }}</span><span class="block text-xs text-stone-500">{{ $o['label'] }}</span></span>
                            <span @class(['text-xs font-semibold', 'text-brand-700' => $o['in'] === 0, 'text-stone-500' => $o['in'] > 0])>{{ $o['in'] === 0 ? 'Today' : ($o['in'] === 1 ? 'Tomorrow' : $o['day']->format('D, d M')) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
