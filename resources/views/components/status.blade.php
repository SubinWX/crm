@props(['value', 'label' => null])
@php
    $class = match ($value) {
        'active', 'paid', 'done' => 'badge-green',
        'sent', 'in_progress', 'lead' => 'badge-blue',
        'partially_paid', 'on_hold', 'suspended', 'pending' => 'badge-amber',
        'overdue' => 'badge-red-solid',
        default => 'badge-gray',
    };
@endphp
<span class="{{ $class }}">{{ $label ?? str($value)->replace('_', ' ')->title() }}</span>
