<div>
    <x-page-header title="Calendar" :subtitle="$monthEvents->count().' '.str('item')->plural($monthEvents->count()).' in '.$first->format('F Y')">
        <div class="flex items-center gap-1.5">
            <button type="button" class="btn-secondary" wire:click="move(-1)" aria-label="Previous month">←</button>
            <span class="w-40 text-center text-sm font-semibold text-stone-900">{{ $first->format('F Y') }}</span>
            <button type="button" class="btn-secondary" wire:click="move(1)" aria-label="Next month">→</button>
        </div>
        <button type="button" class="btn-secondary" wire:click="goToday">Today</button>
        @if ($canLeave) <a href="{{ route('leave', array_filter(['apply' => 1, 'date' => $day && $day >= $today ? $day : null])) }}" class="btn-primary"><x-icon name="plus" /> Apply for leave</a> @endif
    </x-page-header>

    {{-- Legend doubles as the filter: click a kind to hide or show it. --}}
    <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Show on calendar">
        @foreach ($allowed as $type)
            @php [$label, , $dot] = \App\Support\CalendarEvents::TYPES[$type]; $off = in_array($type, $hidden, true); @endphp
            <button type="button" wire:click="toggleType('{{ $type }}')" aria-pressed="{{ $off ? 'false' : 'true' }}"
                    @class(['flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium', 'border-stone-300 bg-white text-stone-800' => ! $off, 'border-dashed border-stone-300 bg-transparent text-stone-400 line-through' => $off])>
                <span class="size-2.5 rounded-full {{ $off ? 'bg-stone-300' : $dot }}"></span>{{ $label }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="card overflow-hidden">
            <div class="grid grid-cols-7 border-b border-stone-200 bg-stone-50 text-center text-xs font-semibold text-stone-500 uppercase">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $name) <div class="py-2">{{ $name }}</div> @endforeach
            </div>
            @foreach ($weeks as $week)
                <div class="grid grid-cols-7 border-b border-stone-200 last:border-b-0">
                    @foreach ($week as $date)
                        @php
                            $key = $date->toDateString();
                            $items = $byDay->get($key, collect());
                            $inMonth = $date->format('Y-m') === $month;
                        @endphp
                        <button type="button" wire:key="day-{{ $key }}" wire:click="selectDay('{{ $key }}')"
                                aria-label="{{ $date->format('l j F') }}, {{ $items->count() }} {{ str('item')->plural($items->count()) }}" aria-pressed="{{ $day === $key ? 'true' : 'false' }}"
                                @class(['flex min-h-24 cursor-pointer flex-col border-r border-stone-200 p-1.5 text-left align-top last:border-r-0 hover:bg-stone-50 md:min-h-28',
                                    'bg-stone-50/70' => ! $inMonth, 'ring-2 ring-brand-500 ring-inset' => $day === $key])>
                            <span @class(['mb-1 grid size-6 place-items-center rounded-full text-xs font-semibold', 'bg-brand-600 text-white' => $key === $today, 'text-stone-900' => $key !== $today && $inMonth, 'text-stone-400' => ! $inMonth])>{{ $date->day }}</span>
                            <span class="w-full space-y-0.5">
                                @foreach ($items->take(3) as $event)
                                    <span class="block truncate rounded px-1 py-0.5 text-[11px] leading-tight font-medium {{ $event['alert'] ? 'bg-red-600 text-white' : \App\Support\CalendarEvents::TYPES[$event['type']][1] }}">{{ $event['title'] }}</span>
                                @endforeach
                                @if ($items->count() > 3) <span class="block px-1 text-[11px] font-medium text-stone-500">+ {{ $items->count() - 3 }} more</span> @endif
                            </span>
                        </button>
                    @endforeach
                </div>
            @endforeach
        </div>

        <section class="card h-fit">
            @php $list = $day ? $byDay->get($day, collect()) : $monthEvents; @endphp
            <div class="card-header">
                <h2 class="card-title">{{ $day ? \Illuminate\Support\Carbon::parse($day)->format('l, d F Y') : 'Everything in '.$first->format('F Y') }}</h2>
                @if ($day) <button type="button" class="link text-xs" wire:click="selectDay('{{ $day }}')">Show whole month</button> @endif
            </div>
            @if ($canLeave && $day && $day >= $today)
                <div class="border-b border-stone-100 px-4 py-2.5"><a href="{{ route('leave', ['apply' => 1, 'date' => $day]) }}" class="link text-sm">+ Apply for leave on this day</a></div>
            @endif
            <div class="max-h-[40rem] overflow-y-auto">
                @forelse ($list as $event)
                    @php [, $colour, , $single] = \App\Support\CalendarEvents::TYPES[$event['type']]; @endphp
                    <div class="flex items-start gap-3 border-b border-stone-100 px-4 py-3 text-sm last:border-b-0">
                        @unless ($day) <span class="w-12 shrink-0 text-xs font-semibold text-stone-500">{{ \Illuminate\Support\Carbon::parse($event['date'])->format('d M') }}</span> @endunless
                        <div class="min-w-0 flex-1">
                            @if ($event['url']) <a href="{{ $event['url'] }}" class="font-medium text-stone-900 hover:underline">{{ $event['title'] }}</a>
                            @else <span class="font-medium text-stone-900">{{ $event['title'] }}</span> @endif
                            @if ($event['detail']) <p class="text-xs text-stone-500">{{ $event['detail'] }}</p> @endif
                        </div>
                        <span class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-semibold whitespace-nowrap {{ $event['alert'] ? 'bg-red-600 text-white' : $colour }}">{{ $event['alert'] ? 'Overdue · ' : '' }}{{ $single }}</span>
                    </div>
                @empty
                    <x-empty>{{ $day ? 'Nothing on this day.' : 'Nothing this month.' }}</x-empty>
                @endforelse
            </div>
        </section>
    </div>
</div>
