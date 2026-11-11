@props(['record'])
{{-- Hosting payment urgency. Red in three strengths, always with text so it never relies on colour alone. --}}
@php $u = $record->urgency(); @endphp
@if ($u === 'overdue')
    <span class="badge-red-solid"><x-icon name="alert" class="size-3.5" /> {{ $record->urgencyLabel() }}</span>
@elseif ($u === 'week')
    <span class="badge-red-strong">{{ $record->urgencyLabel() }}</span>
@elseif ($u === 'two_months')
    <span class="badge-red">{{ $record->urgencyLabel() }}</span>
@elseif ($u === 'later')
    <span class="badge-gray">{{ $record->urgencyLabel() }}</span>
@else
    <span class="text-stone-400">—</span>
@endif
